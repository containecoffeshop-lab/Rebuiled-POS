<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\MenuVariant;
use App\Models\MenuVariantRecipe;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\OptimizedImageUpload;
use App\Services\ProductInventory;
use App\Services\PosNotificationService;
use App\Services\PurchaseRecommendation;
use App\Services\RecipeInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosController extends Controller
{
    public function pendingOrderCount(): JsonResponse
    {
        return response()->json([
            'count' => Transaction::query()->where('status', 'pending')->count(),
        ]);
    }

    public function index(Request $request, RecipeInventory $inventory, ProductInventory $productInventory, PurchaseRecommendation $purchaseRecommendation): View
    {
        $filters = array_merge([
            'search' => '',
            'status' => 'all',
            'kitchen_status' => 'all',
            'date_from' => '',
            'date_to' => '',
        ], array_filter($request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,pending,paid,cancelled'],
            'kitchen_status' => ['nullable', 'in:all,pending,onprogress,success,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]), fn ($value) => $value !== null));
        $catalogFilters = $request->validate([
            'menu_q' => ['nullable', 'string', 'max:120'],
            'menu_category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $today = now()->toDateString();
        $activeShift = CashierShift::query()
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
        $activeShiftExpectedCash = $activeShift
            ? $activeShift->starting_cash + (int) Transaction::query()
                ->where('cashier_shift_id', $activeShift->id)
                ->where('status', 'paid')
                ->where('payment_method', 'cash')
                ->sum('grand_total')
            : null;

        $transactions = Transaction::query()
            ->with(['details', 'diningTable', 'coupon', 'charges', 'bankAccount'])
            ->where('transaction_kind', 'sale')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($query) use ($filters): void {
                    $search = '%'.$filters['search'].'%';
                    $query->where('invoice', 'like', $search)
                        ->orWhere('customer_name', 'like', $search)
                        ->orWhere('customer_phone', 'like', $search)
                        ->orWhereHas('diningTable', fn ($tables) => $tables->where('number', 'like', $search))
                        ->orWhereHas('details', fn ($details) => $details->where('item_name', 'like', $search));
                }),
            )
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['kitchen_status'] ?? 'all') !== 'all', fn ($query) => $query->where('kitchen_status', $filters['kitchen_status']))
            ->when($filters['date_from'] ?? null, fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] ?? null, fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->paginate(12);

        $summary = [
            'sales_today' => Transaction::query()->where('transaction_kind', 'sale')->whereDate('created_at', $today)->where('status', 'paid')->sum('grand_total'),
            'orders_today' => Transaction::query()->where('transaction_kind', 'sale')->whereDate('created_at', $today)->where('status', '!=', 'cancelled')->count(),
            'awaiting_payment' => Transaction::query()->where('status', 'pending')->count(),
            'in_kitchen' => Transaction::query()->whereIn('kitchen_status', ['pending', 'onprogress'])->where('status', '!=', 'cancelled')->count(),
        ];
        $selectedMonth = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('!Y-m', $selectedMonth);
        $month = $monthDate->month;
        $year = $monthDate->year;
        $monthStart = $monthDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();
        $ingredientMovementQuery = DB::table('stock_movements')
            ->join('transactions', 'transactions.id', '=', 'stock_movements.transaction_id')
            ->where('transactions.transaction_kind', 'sale')
            ->where('transactions.status', 'paid')
            ->where('stock_movements.type', 'out')
            ->whereDate('stock_movements.created_at', '>=', $monthStart)
            ->whereDate('stock_movements.created_at', '<=', $monthEnd);
        $ingredientCostIncomplete = (clone $ingredientMovementQuery)->whereNull('stock_movements.cost_amount')->exists();
        $ingredientCost = $ingredientCostIncomplete
            ? null
            : (int) (clone $ingredientMovementQuery)->sum('stock_movements.cost_amount');
        $employeeConsumptionCost = (int) Transaction::query()
            ->where('transaction_kind', 'employee_consumption')
            ->where('status', 'consumed')
            ->where('cogs_complete', true)
            ->whereBetween('created_at', [$monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth()])
            ->sum('cogs_total');
        $expenseTotal = (int) DB::table('expenses')
            ->whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd)
            ->sum('amount');
        $monthlySummary = [
            'income' => Transaction::query()
                ->where('transaction_kind', 'sale')
                ->where('status', 'paid')
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('grand_total'),
            'expenses' => auth()->user()->role === 'manager'
                ? DB::table('expense_payments')->whereYear('paid_at', $year)->whereMonth('paid_at', $month)->sum('amount')
                : null,
            'ingredient_cost' => auth()->user()->role === 'manager' ? $ingredientCost : null,
            'employee_consumption_cost' => auth()->user()->role === 'manager' ? $employeeConsumptionCost : null,
            'operational_cost' => auth()->user()->role === 'manager' ? $expenseTotal : null,
            'target_turnover' => auth()->user()->role === 'manager'
                ? ($ingredientCost === null ? null : (int) ceil(($ingredientCost + $expenseTotal) * 1.05 / $monthDate->daysInMonth))
                : null,
            'month' => $selectedMonth,
            'days_in_month' => $monthDate->daysInMonth,
        ];
        $menuSales = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->whereNotNull('transaction_details.menu_id')
            ->where('transactions.transaction_kind', 'sale')
            ->where('transactions.status', 'paid')
            ->whereBetween('transactions.paid_at', [$monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth()])
            ->groupBy('transaction_details.menu_id')
            ->selectRaw('transaction_details.menu_id, SUM(transaction_details.quantity) as quantity, SUM(transaction_details.line_total) as item_sales')
            ->get()
            ->keyBy('menu_id');
        $menuSalesSummary = Menu::query()
            ->where(function ($query) use ($menuSales): void {
                $query->where('is_available', true);
                if ($menuSales->isNotEmpty()) {
                    $query->orWhereIn('id', $menuSales->keys());
                }
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Menu $menu): array => [
                'name' => $menu->name,
                'quantity' => (int) ($menuSales->get($menu->id)->quantity ?? 0),
                'item_sales' => (int) ($menuSales->get($menu->id)->item_sales ?? 0),
            ]);
        $topMenus = $menuSalesSummary->filter(fn (array $menu): bool => $menu['quantity'] > 0)
            ->sortBy([['quantity', 'desc'], ['name', 'asc']])
            ->take(10)
            ->values();
        $leastMenus = $menuSalesSummary->sortBy([['quantity', 'asc'], ['name', 'asc']])
            ->take(10)
            ->values();
        $dailyIncome = Transaction::query()
            ->where('transaction_kind', 'sale')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth()])
            ->selectRaw('date(paid_at) as day, SUM(grand_total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $dailyExpenses = DB::table('expense_payments')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->selectRaw('paid_at as day, SUM(amount) as total')
            ->groupBy('paid_at')
            ->pluck('total', 'day');
        $dailySummary = collect(range(1, $monthDate->daysInMonth))
            ->map(function (int $day) use ($monthDate, $dailyIncome, $dailyExpenses, $monthlySummary): array {
                $date = $monthDate->copy()->day($day)->toDateString();
                $income = (int) ($dailyIncome->get($date) ?? 0);
                $expensesPaid = (int) ($dailyExpenses->get($date) ?? 0);
                $target = $monthlySummary['target_turnover'];

                return [
                    'date' => $date,
                    'day' => $day,
                    'income' => $income,
                    'expenses' => $monthlySummary['expenses'] !== null ? $expensesPaid : null,
                    'target' => $target,
                    'achievement' => $target ? round($income / $target * 100, 1) : null,
                ];
            });

        $menus = Menu::query()->with([
            'category',
            'recipes.material.unit',
            'variants' => fn ($query) => $query->with('recipes.material.unit')->orderBy('id'),
            'bundleItems.componentMenu',
        ])->orderBy('name')->get();
        $menuCostEstimates = $inventory->menuCostEstimates($menus);
        $availableMenus = $menus->filter(fn (Menu $menu) => $menu->is_available
            && (! $menu->is_bundle || ($menu->bundleItems->isNotEmpty()
                && $menu->bundleItems->every(fn ($item) => $item->componentMenu?->is_available)))
            && (! filled($catalogFilters['menu_q'] ?? null) || str_contains(mb_strtolower($menu->name), mb_strtolower($catalogFilters['menu_q'])))
            && (! filled($catalogFilters['menu_category'] ?? null) || $menu->category_id === (int) $catalogFilters['menu_category']))->values();
        $inventoryAvailability = $inventory->availableServings($menus);
        $variantAvailability = $inventory->availableVariantServings($menus);
        $materials = Material::query()->with(['unit', 'supplier'])->orderBy('name')->get();
        $stockOnHand = $inventory->onHandByMaterial($materials->pluck('id')->all());
        $materialUnitCosts = $inventory->materialUnitCostEstimates($materials->pluck('id')->all());
        $materials->each(function (Material $material) use ($stockOnHand, $materialUnitCosts): void {
            $material->setAttribute('stock_on_hand', $stockOnHand->get($material->id, 0));
            $material->setAttribute('is_below_minimum', $material->stock_on_hand < $material->minimum_quantity);
            $material->setAttribute('estimated_unit_cost', $materialUnitCosts->get($material->id));
        });
        $purchaseRecommendations = $purchaseRecommendation->forMaterials($materials);
        $lowStockMaterials = $materials->filter(fn (Material $material): bool => $material->is_below_minimum)->sortBy('stock_on_hand')->values();
        $openShiftCount = CashierShift::query()->whereNull('closed_at')->count();
        $menus->each(function (Menu $menu) use ($menuCostEstimates, $materialUnitCosts): void {
            $baseCost = $menuCostEstimates->get($menu->id)['cost'];
            $menu->variants->each(function (MenuVariant $variant) use ($baseCost, $materialUnitCosts): void {
                $extraCost = 0;
                $complete = $baseCost !== null;

                foreach ($variant->recipes as $recipe) {
                    $unitCost = $materialUnitCosts->get($recipe->material_id);
                    if ($unitCost === null) {
                        $complete = false;
                        break;
                    }
                    $extraCost += (int) round((float) $recipe->quantity * $unitCost);
                }

                $variant->setAttribute('estimated_hpp', $complete ? $baseCost + $extraCost : null);
            });
        });
        $productVariants = ProductVariant::query()
            ->with(['product.category', 'unit'])
            ->where('is_available', true)
            ->whereHas('product', fn ($query) => $query->where('is_available', true))
            ->when(filled($catalogFilters['menu_q'] ?? null), fn ($query) => $query->where(fn ($search) => $search
                ->whereHas('product', fn ($products) => $products->where('name', 'like', '%'.$catalogFilters['menu_q'].'%'))
                ->orWhere('label', 'like', '%'.$catalogFilters['menu_q'].'%')))
            ->when(filled($catalogFilters['menu_category'] ?? null), fn ($query) => $query->whereHas('product', fn ($products) => $products->where('category_id', $catalogFilters['menu_category'])))
            ->orderBy('product_id')
            ->orderBy('label')
            ->get();
        $productStock = $productInventory->onHandByVariant($productVariants->pluck('id')->all());
        $productVariants->each(function (ProductVariant $variant) use ($productStock): void {
            $variant->setAttribute('stock_on_hand', $productStock->get($variant->id, 0));
        });
        $oldItems = $request->session()->getOldInput('items', []);
        $oldItems = is_array($oldItems)
            ? collect($oldItems)->filter(fn ($item) => is_array($item)
                && (filter_var($item['menu_id'] ?? null, FILTER_VALIDATE_INT) || filter_var($item['product_variant_id'] ?? null, FILTER_VALIDATE_INT)))
            : collect();
        $oldMenuVariants = MenuVariant::query()->whereIn('id', $oldItems->pluck('menu_variant_id')->filter()->unique())->where('is_active', true)->get()->keyBy('id');
        $oldMenus = Menu::query()->with('variants')->whereIn('id', $oldItems->pluck('menu_id')->filter()->unique())->get()->keyBy('id');
        $oldVariants = ProductVariant::query()->with('product')->whereIn('id', $oldItems->pluck('product_variant_id')->filter()->unique())->get()->keyBy('id');
        $initialCart = $oldItems->map(function (array $item) use ($oldMenus, $oldVariants, $oldMenuVariants): ?array {
            $type = filled($item['product_variant_id'] ?? null) ? 'product' : 'menu';
            $id = (int) ($type === 'product' ? $item['product_variant_id'] : $item['menu_id']);
            $record = $type === 'product' ? $oldVariants->get($id) : $oldMenus->get($id);
            $menuVariant = $type === 'menu' && filled($item['menu_variant_id'] ?? null)
                ? $oldMenuVariants->get((int) $item['menu_variant_id'])
                : null;
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if (! $record || $quantity === false || $quantity < 1 || $quantity > 20) {
                return null;
            }
            if ($type === 'menu' && $record->variants->contains(fn (MenuVariant $variant) => $variant->is_active)
                && (! $menuVariant || $menuVariant->menu_id !== $record->id)) {
                return null;
            }

            return [
                'type' => $type,
                'id' => $id,
                'menu_variant_id' => $menuVariant?->id,
                'name' => $type === 'product'
                    ? $record->product->name.' · '.$record->label
                    : $record->name.($menuVariant ? ' · '.$menuVariant->name : ''),
                'price' => (int) $record->selling_price + (int) ($menuVariant?->additional_price ?? 0),
                'quantity' => $quantity,
            ];
        })->filter()->values()->all();

        return view('pos.index', [
            'activeShift' => $activeShift,
            'activeShiftExpectedCash' => $activeShiftExpectedCash,
            'categories' => Category::query()->withCount('menus')->orderBy('name')->get(),
            'menus' => $menus,
            'menuCostEstimates' => $menuCostEstimates,
            'menuVariantCostEstimates' => $menus->flatMap(fn (Menu $menu) => $menu->variants->mapWithKeys(fn (MenuVariant $variant) => [
                $variant->id => $variant->estimated_hpp,
            ])),
            'availableMenus' => $availableMenus,
            'productVariants' => $productVariants,
            'productStock' => $productStock,
            'inventoryAvailability' => $inventoryAvailability,
            'variantAvailability' => $variantAvailability,
            'materials' => $materials,
            'lowStockMaterials' => $lowStockMaterials,
            'purchaseRecommendations' => $purchaseRecommendations,
            'openShiftCount' => $openShiftCount,
            'materialUnitCosts' => $materialUnitCosts,
            'units' => Unit::query()->orderBy('name')->get(),
            'initialCart' => $initialCart,
            'transactions' => $transactions,
            'summary' => $summary,
            'monthlySummary' => $monthlySummary,
            'dailySummary' => $dailySummary,
            'topMenus' => $topMenus,
            'leastMenus' => $leastMenus,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'filters' => $filters,
            'catalogFilters' => array_merge(['menu_q' => '', 'menu_category' => ''], $catalogFilters),
            'diningTables' => DiningTable::query()->withCount('transactions')->orderBy('number')->get(),
            'customers' => Customer::query()->withCount('transactions')->latest()->limit(100)->get(),
            'coupons' => Coupon::query()->orderBy('code')->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->orderBy('account_name')->get(),
            'loyaltyPointValue' => (int) StoreSetting::query()->where('key', 'loyalty_point_value')->value('value'),
            'employees' => User::query()->where('role', 'cashier')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employeeConsumptions' => Transaction::query()->where('transaction_kind', 'employee_consumption')
                ->with(['employee', 'details'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function exportSummary(Request $request): StreamedResponse
    {
        $selectedMonth = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $data = $this->summaryData($selectedMonth);
        $filename = 'ringkasan-'.$selectedMonth.'.xls';

        return response()->streamDownload(function () use ($data): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16" ss:Color="#355E49"/></Style><Style ss:ID="Section"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#527A68" ss:Pattern="Solid"/></Style><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#E3E9E1" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="&quot;Rp&quot; #,##0"/></Style></Styles>';
            echo '<Worksheet ss:Name="Ringkasan"><Table>';
            echo $this->spreadsheetRow(['Ringkasan POS · '.$data['month_label']], 'Title');
            echo $this->spreadsheetRow(['Indikator', 'Nilai'], 'Header');
            foreach ($data['metrics'] as $label => $value) {
                echo $this->spreadsheetRow([$label, $value]);
            }

            echo $this->spreadsheetRow([]);
            echo $this->spreadsheetRow(['RINGKASAN HARIAN'], 'Section');
            echo $this->spreadsheetRow(['Tanggal', 'Pemasukan', 'Pengeluaran dibayar', 'Target harian', 'Pencapaian target (%)'], 'Header');
            foreach ($data['daily'] as $day) {
                echo $this->spreadsheetRow([
                    $day['date'],
                    $day['income'],
                    $day['expenses_paid'],
                    $day['target'] ?? 'HPP belum lengkap',
                    $day['achievement'] ?? '—',
                ]);
            }

            echo $this->spreadsheetRow([]);
            echo $this->spreadsheetRow(['DETAIL PENJUALAN'], 'Section');
            echo $this->spreadsheetRow(['Waktu bayar', 'Invoice', 'Pelanggan', 'Menu', 'Qty', 'Subtotal item', 'Diskon item', 'Total transaksi'], 'Header');
            foreach ($data['sales'] as $transaction) {
                foreach ($transaction->details as $detail) {
                    echo $this->spreadsheetRow([
                        $transaction->paid_at?->format('Y-m-d H:i:s'),
                        $transaction->invoice,
                        $transaction->customer_name,
                        $detail->item_name,
                        $detail->quantity,
                        $detail->line_total,
                        $detail->promotion_discount,
                        $transaction->grand_total,
                    ]);
                }
            }

            echo $this->spreadsheetRow([]);
            echo $this->spreadsheetRow(['PENGELUARAN OPERASIONAL'], 'Section');
            echo $this->spreadsheetRow(['Tanggal', 'Kode', 'Kategori', 'Keterangan', 'Nominal', 'Status'], 'Header');
            foreach ($data['expenses'] as $expense) {
                echo $this->spreadsheetRow([
                    $expense->expense_date,
                    $expense->expense_code,
                    $expense->category_name,
                    $expense->description,
                    $expense->amount,
                    $expense->is_non_cash ? 'Non-tunai (konsumsi)' : $expense->payment_status,
                ]);
            }
            echo '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    public function exportSummaryPdf(Request $request): View
    {
        $selectedMonth = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');

        return view('pos.summary-pdf', $this->summaryData($selectedMonth));
    }

    private function summaryData(string $selectedMonth): array
    {
        $monthDate = Carbon::createFromFormat('!Y-m', $selectedMonth);
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();
        $sales = Transaction::query()
            ->where('transaction_kind', 'sale')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->with('details')
            ->orderBy('paid_at')
            ->get();
        $expenses = DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expenses.expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('expenses.expense_date')
            ->get([
                'expenses.expense_date',
                'expenses.expense_code',
                'expenses.description',
                'expenses.amount',
                'expenses.payment_status',
                'expenses.is_non_cash',
                'expense_categories.name as category_name',
            ]);
        $expensePayments = DB::table('expense_payments')
            ->whereBetween('paid_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('paid_at as day, SUM(amount) as total')
            ->groupBy('paid_at')
            ->pluck('total', 'day');
        $dailyIncome = $sales->groupBy(fn (Transaction $transaction): string => $transaction->paid_at->toDateString())
            ->map(fn ($transactions): int => (int) $transactions->sum('grand_total'));
        $expenseTotal = (int) DB::table('expenses')
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');
        $ingredientQuery = DB::table('stock_movements')
            ->join('transactions', 'transactions.id', '=', 'stock_movements.transaction_id')
            ->where('transactions.transaction_kind', 'sale')
            ->where('transactions.status', 'paid')
            ->where('stock_movements.type', 'out')
            ->whereBetween('stock_movements.created_at', [$monthStart, $monthEnd]);
        $ingredientCostIncomplete = (clone $ingredientQuery)->whereNull('stock_movements.cost_amount')->exists();
        $ingredientCost = $ingredientCostIncomplete ? null : (int) (clone $ingredientQuery)->sum('stock_movements.cost_amount');
        $employeeConsumptionCost = (int) Transaction::query()
            ->where('transaction_kind', 'employee_consumption')
            ->where('status', 'consumed')
            ->where('cogs_complete', true)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('cogs_total');
        $incomeTotal = (int) $sales->sum('grand_total');
        $paidExpenseTotal = (int) $expensePayments->sum();
        $dailyTarget = $ingredientCost === null
            ? null
            : (int) ceil(($ingredientCost + $expenseTotal) * 1.05 / $monthDate->daysInMonth);
        $daily = collect(range(1, $monthDate->daysInMonth))
            ->map(function (int $day) use ($monthDate, $dailyIncome, $expensePayments, $dailyTarget): array {
                $date = $monthDate->copy()->day($day)->toDateString();
                $income = (int) ($dailyIncome->get($date) ?? 0);

                return [
                    'date' => $date,
                    'income' => $income,
                    'expenses_paid' => (int) ($expensePayments->get($date) ?? 0),
                    'target' => $dailyTarget,
                    'achievement' => $dailyTarget ? round($income / $dailyTarget * 100, 1) : null,
                ];
            });

        return [
            'month' => $selectedMonth,
            'month_label' => $monthDate->locale('id')->translatedFormat('F Y'),
            'days_in_month' => $monthDate->daysInMonth,
            'sales' => $sales,
            'expenses' => $expenses,
            'daily' => $daily,
            'ingredient_cost' => $ingredientCost,
            'employee_consumption_cost' => $employeeConsumptionCost,
            'operational_cost' => $expenseTotal,
            'income_total' => $incomeTotal,
            'paid_expense_total' => $paidExpenseTotal,
            'target_turnover' => $dailyTarget,
            'metrics' => [
                'Total pemasukan transaksi lunas' => $incomeTotal,
                'HPP bahan penjualan' => $ingredientCost ?? 'HPP belum lengkap',
                'HPP konsumsi karyawan' => $employeeConsumptionCost,
                'Total pengeluaran operasional' => $expenseTotal,
                'Pengeluaran yang benar-benar dibayar' => $paidExpenseTotal,
                'Target omzet harian' => $dailyTarget ?? 'HPP belum lengkap',
                'Jumlah hari kalender' => $monthDate->daysInMonth,
            ],
        ];
    }

    private function spreadsheetRow(array $values, ?string $style = null): string
    {
        $cells = '';
        foreach ($values as $value) {
            $type = is_int($value) || is_float($value) ? 'Number' : 'String';
            $encoded = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $cells .= '<Cell'.($style ? ' ss:StyleID="'.$style.'"' : '').'><Data ss:Type="'.$type.'">'.$encoded.'</Data></Cell>';
        }

        return '<Row>'.$cells.'</Row>';
    }

    public function storeDiningTable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:32', 'unique:dining_tables,number'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        DiningTable::create($data);

        return back()->with('success', 'Meja dan QR berhasil ditambahkan.');
    }

    public function clearDiningTable(DiningTable $table): RedirectResponse
    {
        DB::transaction(function () use ($table): void {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($table->id);
            $hasPendingOrders = Transaction::query()
                ->where('dining_table_id', $locked->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($hasPendingOrders) {
                throw ValidationException::withMessages([
                    'table' => 'Meja belum dapat dikosongkan karena masih ada pesanan yang menunggu pembayaran.',
                ]);
            }

            $locked->update(['status' => 'available']);
        });

        return back()->with('success', 'Status meja diperbarui menjadi tersedia.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ]);

        if ($data['type'] === 'percentage' && $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Persentase kupon maksimal 100%.']);
        }

        Coupon::create([
            ...$data,
            'code' => strtoupper($data['code']),
            'is_active' => true,
        ]);

        return back()->with('success', 'Kupon berhasil ditambahkan.');
    }

    public function updateCoupon(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['type'] === 'percentage' && $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Persentase kupon maksimal 100%.']);
        }

        $coupon->update([...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('success', 'Perubahan kupon berhasil disimpan.');
    }

    public function openShift(Request $request, PosNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'starting_cash' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        DB::transaction(function () use ($request, $data, $notifications): void {
            $alreadyOpen = CashierShift::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages(['starting_cash' => 'Shift kasir Anda masih terbuka.']);
            }

            CashierShift::create([
                'user_id' => $request->user()->id,
                'starting_cash' => $data['starting_cash'],
                'opened_at' => now(),
            ]);
            $notifications->notifyStaff(
                'Shift kasir dibuka',
                'Shift '.$request->user()->name.' dibuka; pemesanan online tersedia.',
                route('pos.index', ['module' => 'kasir']),
            );
        });

        return back()->with('success', 'Shift kasir dibuka.');
    }

    public function closeShift(Request $request, PosNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'ending_cash' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $data, $notifications): void {
            $shift = CashierShift::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->firstOrFail();

            $hasPendingOrders = Transaction::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($hasPendingOrders) {
                throw ValidationException::withMessages([
                    'ending_cash' => 'Shift belum dapat ditutup. Selesaikan pembayaran atau batalkan semua pesanan yang masih menunggu, lalu coba lagi.',
                ]);
            }

            $cashSales = (int) Transaction::query()
                ->where('cashier_shift_id', $shift->id)
                ->where('status', 'paid')
                ->where('payment_method', 'cash')
                ->sum('grand_total');
            $expectedCash = $shift->starting_cash + $cashSales;
            $cashDifference = (int) $data['ending_cash'] - $expectedCash;

            if ($cashDifference !== 0 && blank($data['closing_note'] ?? null)) {
                throw ValidationException::withMessages([
                    'closing_note' => 'Jelaskan penyebab selisih kas agar shift dapat ditutup.',
                ]);
            }

            $shift->update([
                'ending_cash' => $data['ending_cash'],
                'expected_cash' => $expectedCash,
                'cash_difference' => $cashDifference,
                'closing_note' => $cashDifference === 0 ? null : trim($data['closing_note']),
                'closed_at' => now(),
            ]);

            $closedShift = $shift->fresh()->load('user');
            $notifications->notifyStaff(
                'Shift kasir ditutup',
                'Shift '.$closedShift->user->name.' ditutup dengan selisih kas Rp'.number_format(abs($closedShift->cash_difference), 0, ',', '.').'.',
                route('pos.index', ['module' => 'ringkasan']),
            );
        });

        return back()->with('success', 'Shift kasir ditutup.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:categories,name']]);
        Category::create($data);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function storeMenu(Request $request): RedirectResponse
    {
        $request->merge(['barcode' => $request->filled('barcode') ? strtoupper(trim((string) $request->input('barcode'))) : null]);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'selling_price' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'barcode' => ['nullable', 'string', 'max:120', 'regex:/^[\x20-\x7E]+$/', 'unique:menus,barcode'],
        ]);
        $this->assertMenuBarcodeAvailable($data['barcode'] ?? null);

        $imagePath = isset($data['image']) ? app(OptimizedImageUpload::class)->store($data['image'], 'menu', 1200) : null;
        unset($data['image']);

        try {
            $menu = Menu::create([...$data, 'image_path' => $imagePath]);
        } catch (\Throwable $exception) {
            app(OptimizedImageUpload::class)->delete($imagePath);
            throw $exception;
        }

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Menu berhasil ditambahkan. Tambahkan bahan resep bila diperlukan.')
            ->with('open_menu', $menu->id);
    }

    public function updateMenu(Request $request, Menu $menu): RedirectResponse
    {
        $request->merge(['barcode' => $request->filled('barcode') ? strtoupper(trim((string) $request->input('barcode'))) : null]);
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'remove_image' => ['nullable', 'boolean'],
            'selling_price' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'barcode' => ['nullable', 'string', 'max:120', 'regex:/^[\x20-\x7E]+$/', Rule::unique('menus', 'barcode')->ignore($menu->id)],
        ]);
        $this->assertMenuBarcodeAvailable($data['barcode'] ?? null);

        $newImagePath = isset($data['image']) ? app(OptimizedImageUpload::class)->store($data['image'], 'menu', 1200) : null;
        unset($data['image'], $data['remove_image']);
        $oldImagePath = $menu->image_path;

        try {
            $menu->update([
                ...$data,
                'image_path' => $newImagePath ?? ($request->boolean('remove_image') ? null : $oldImagePath),
            ]);
        } catch (\Throwable $exception) {
            app(OptimizedImageUpload::class)->delete($newImagePath);
            throw $exception;
        }

        if ($newImagePath || $request->boolean('remove_image')) {
            app(OptimizedImageUpload::class)->delete($oldImagePath);
        }

        return back()->with('success', 'Perubahan menu berhasil disimpan.');
    }

    public function storeMaterial(Request $request, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:materials,name'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'initial_quantity' => ['nullable', 'required_with:initial_total_cost', 'numeric', 'min:0.001', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'initial_total_cost' => ['nullable', 'required_with:initial_quantity', 'integer', 'min:0', 'max:999999999999'],
        ]);

        DB::transaction(function () use ($data, $inventory): void {
            $material = Material::create([
                'name' => $data['name'],
                'unit_id' => $data['unit_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'minimum_quantity' => $data['minimum_quantity'],
            ]);

            if (isset($data['initial_quantity'], $data['initial_total_cost'])) {
                $inventory->receive(
                    $material->id,
                    'AWAL-'.$material->id.'-'.now()->format('YmdHis'),
                    (int) round((float) $data['initial_quantity'] * 1000),
                    null,
                    'Stok awal bahan',
                    number_format((int) $data['initial_total_cost'] / (float) $data['initial_quantity'], 6, '.', ''),
                );
            }
        });

        return back()->with('success', 'Bahan berhasil ditambahkan.');
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('materials', 'name')->ignore($material->id)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);

        $material->update($data);

        return back()->with('success', 'Detail bahan berhasil diperbarui.');
    }

    public function receiveMaterialStock(Request $request, Material $material, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'batch_code' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'total_cost' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'regex:/^\d+(?:\.\d{1,6})?$/'],
        ]);
        $quantity = (float) $data['quantity'];
        $unitCost = isset($data['total_cost'])
            ? number_format((int) $data['total_cost'] / $quantity, 6, '.', '')
            : (isset($data['unit_cost']) ? (string) $data['unit_cost'] : null);

        $inventory->receive(
            $material->id,
            $data['batch_code'],
            (int) round((float) $data['quantity'] * 1000),
            $data['expires_at'] ?? null,
            'Stok masuk',
            $unitCost,
        );

        return back()->with('success', 'Stok masuk berhasil dicatat.');
    }

    public function storeRecipe(Request $request, Menu $menu): RedirectResponse
    {
        abort_if($menu->is_bundle, 422, 'Komponen paket tidak dapat diatur sebagai resep langsung.');

        $data = $request->validate([
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('menu_recipes', 'material_id')->where('menu_id', $menu->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:49999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);

        DB::transaction(function () use ($menu, $data): void {
            Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $menu->recipes()->create($data);
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Bahan resep berhasil ditambahkan. Anda dapat menambahkan bahan lainnya.')
            ->with('open_menu', $menu->id);
    }

    public function storeMenuVariant(Request $request, Menu $menu): RedirectResponse
    {
        abort_if($menu->is_bundle, 422, 'Varian langsung tidak dapat ditambahkan ke menu paket.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('menu_variants', 'name')->where('menu_id', $menu->id)],
            'additional_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        DB::transaction(function () use ($menu, $data): void {
            Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $menu->variants()->create($data);
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Varian menu berhasil ditambahkan. Anda dapat menambahkan bahan tambahan.')
            ->with('open_menu', $menu->id);
    }

    public function storeEmployeeConsumption(Request $request, RecipeInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'cashier')
                    ->where('is_active', true)),
            ],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'items.*.menu_variant_id' => ['nullable', 'integer', 'exists:menu_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $items = collect($data['items']);
        $seen = [];
        foreach ($items as $index => $item) {
            $key = $item['menu_id'].':'.($item['menu_variant_id'] ?? 0);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["items.$index" => 'Item menu dan varian yang sama tidak boleh dimasukkan dua kali.']);
            }
            $seen[$key] = true;
        }

        DB::transaction(function () use ($request, $data, $items, $inventory): void {
            $shift = CashierShift::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if (! $shift) {
                throw ValidationException::withMessages(['items' => 'Buka shift kasir terlebih dahulu sebelum mencatat konsumsi karyawan.']);
            }

            $employee = User::query()
                ->where('role', 'cashier')
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);
            $menuIds = $items->pluck('menu_id')->unique()->sort()->values();
            $menus = Menu::query()
                ->whereIn('id', $menuIds)
                ->availableForSale()
                ->with(['variants' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $variantIds = $items->pluck('menu_variant_id')->filter()->unique()->sort()->values();
            $variants = MenuVariant::query()
                ->whereIn('id', $variantIds)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lines = $items->map(function (array $item) use ($menus, $variants): array {
                $menu = $menus->get((int) $item['menu_id']);
                $variant = filled($item['menu_variant_id'] ?? null) ? $variants->get((int) $item['menu_variant_id']) : null;
                if (! $menu || ($menu->variants->isNotEmpty() && ! $variant)
                    || ($variant && ($variant->menu_id !== $menu->id || $menu->is_bundle))) {
                    throw ValidationException::withMessages(['items' => 'Menu atau varian konsumsi sudah tidak tersedia. Segarkan halaman lalu coba kembali.']);
                }

                return [
                    'menu' => $menu,
                    'variant' => $variant,
                    'quantity' => (int) $item['quantity'],
                    'name' => $menu->name.($variant ? ' · '.$variant->name : ''),
                ];
            });
            $inventory->assertAvailable($lines->map(fn (array $line) => [
                'menu_id' => $line['menu']->id,
                'menu_variant_id' => $line['variant']?->id,
                'quantity' => $line['quantity'],
            ])->all());

            $transaction = Transaction::create([
                'invoice' => 'KS-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'transaction_kind' => 'employee_consumption',
                'cashier_shift_id' => $shift->id,
                'employee_id' => $employee->id,
                'customer_name' => $employee->name,
                'transaction_type' => 'takeaway',
                'status' => 'consumed',
                'kitchen_status' => 'success',
                'subtotal' => 0,
                'discount' => 0,
                'promotion_discount' => 0,
                'coupon_discount' => 0,
                'grand_total' => 0,
                'amount_paid' => 0,
                'change_due' => 0,
                'notes' => 'Konsumsi karyawan',
            ]);

            foreach ($lines as $line) {
                $transaction->details()->create([
                    'item_type' => 'menu',
                    'menu_id' => $line['menu']->id,
                    'menu_variant_id' => $line['variant']?->id,
                    'item_name' => $line['name'],
                    'price' => 0,
                    'quantity' => $line['quantity'],
                    'line_total' => 0,
                ]);
            }

            $inventory->snapshot($transaction);
            $cost = $inventory->consume($transaction);

            if (! $cost['complete']) {
                throw ValidationException::withMessages([
                    'items' => 'HPP belum lengkap. Lengkapi harga modal batch bahan sebelum mencatat konsumsi karyawan.',
                ]);
            }

            $transaction->update(['cogs_total' => $cost['total'], 'cogs_complete' => true]);
            $consumptionCategory = ExpenseCategory::query()->firstOrCreate(['name' => 'Konsumsi']);
            $consumptionSubcategory = ExpenseSubcategory::query()->firstOrCreate([
                'expense_category_id' => $consumptionCategory->id,
                'name' => 'Konsumsi karyawan',
            ]);
            Expense::query()->create([
                'expense_code' => 'EXP-'.$transaction->invoice,
                'reference_number' => $transaction->invoice,
                'expense_date' => today()->toDateString(),
                'expense_category_id' => $consumptionCategory->id,
                'expense_subcategory_id' => $consumptionSubcategory->id,
                'created_by' => $request->user()->id,
                'amount' => $cost['total'],
                'payment_status' => 'paid',
                'is_non_cash' => true,
                'description' => 'Konsumsi non-tunai untuk '.$employee->name.' · '.$transaction->invoice,
            ]);
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'konsumsi'])
            ->with('success', 'Konsumsi karyawan dicatat tanpa pembayaran; stok dan HPP bahan sudah diperbarui.');
    }

    public function storeMenuVariantRecipe(Request $request, Menu $menu, MenuVariant $variant): RedirectResponse
    {
        abort_if($menu->is_bundle, 422, 'Resep varian tidak tersedia untuk menu paket.');
        abort_unless($variant->menu_id === $menu->id, 404);
        $data = $request->validate([
            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
                Rule::unique('menu_variant_recipes', 'material_id')->where('menu_variant_id', $variant->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:49999999.999', 'regex:/^\\d+(?:\\.\\d{1,3})?$/'],
        ]);

        DB::transaction(function () use ($menu, $variant, $data): void {
            Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $lockedVariant = $menu->variants()->lockForUpdate()->findOrFail($variant->id);
            $lockedVariant->recipes()->create($data);
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Bahan tambahan varian berhasil dicatat.')
            ->with('open_menu', $menu->id)
            ->with('open_variant', $variant->id);
    }

    public function destroyMenuVariantRecipe(Menu $menu, MenuVariant $variant, MenuVariantRecipe $recipe): RedirectResponse
    {
        abort_unless($variant->menu_id === $menu->id, 404);

        DB::transaction(function () use ($menu, $variant, $recipe): void {
            Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $lockedVariant = $menu->variants()->lockForUpdate()->findOrFail($variant->id);
            $lockedVariant->recipes()->lockForUpdate()->findOrFail($recipe->id)->delete();
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Bahan tambahan varian dihapus.')
            ->with('open_menu', $menu->id)
            ->with('open_variant', $variant->id);
    }

    public function toggleMenuVariant(Menu $menu, MenuVariant $variant): RedirectResponse
    {
        abort_unless($variant->menu_id === $menu->id, 404);
        $variant->update(['is_active' => ! $variant->is_active]);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', $variant->is_active ? 'Varian diaktifkan.' : 'Varian dinonaktifkan.')
            ->with('open_menu', $menu->id);
    }

    public function destroyMenuVariant(Menu $menu, MenuVariant $variant): RedirectResponse
    {
        abort_unless($variant->menu_id === $menu->id, 404);
        $variant->delete();

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Varian menu dihapus.')
            ->with('open_menu', $menu->id);
    }

    public function destroyRecipe(Menu $menu, MenuRecipe $recipe): RedirectResponse
    {
        abort_if($menu->is_bundle, 422, 'Komponen paket tidak dapat diatur sebagai resep langsung.');

        DB::transaction(function () use ($menu, $recipe): void {
            Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $lockedRecipe = $menu->recipes()->lockForUpdate()->findOrFail($recipe->id);
            $lockedRecipe->delete();
        }, attempts: 3);

        return redirect()->route('pos.index', ['module' => 'menu'])
            ->with('success', 'Bahan berhasil dihapus dari resep.')
            ->with('open_menu', $menu->id);
    }

    public function toggleMenu(Menu $menu): RedirectResponse
    {
        $menu->update(['is_available' => ! $menu->is_available]);

        return back()->with('success', $menu->is_available ? 'Menu ditampilkan di katalog.' : 'Menu disembunyikan dari katalog.');
    }

    private function assertMenuBarcodeAvailable(?string $barcode): void
    {
        if ($barcode !== null && ProductVariant::query()->where('barcode', $barcode)->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tersebut sudah digunakan oleh varian produk.']);
        }
    }
}
