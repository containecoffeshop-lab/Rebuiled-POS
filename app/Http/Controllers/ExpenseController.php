<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $categoryIds = ExpenseCategory::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $filters = array_merge([
            'search' => '',
            'category_id' => 'all',
            'payment_status' => 'all',
            'date_from' => '',
            'date_to' => '',
        ], array_filter($request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', Rule::in(array_merge(['all'], $categoryIds))],
            'payment_status' => ['nullable', 'in:all,unpaid,partial,paid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]), fn ($value) => $value !== null));

        $expenses = Expense::query()
            ->with(['category', 'subcategory', 'payments'])
            ->when(
                filled($filters['search']),
                fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                    $search = '%'.$filters['search'].'%';
                    $query->where('expense_code', 'like', $search)
                        ->orWhere('reference_number', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $search))
                        ->orWhereHas('subcategory', fn ($subcategory) => $subcategory->where('name', 'like', $search));
                }),
            )
            ->when($filters['category_id'] !== 'all', fn (Builder $query) => $query->where('expense_category_id', $filters['category_id']))
            ->when($filters['payment_status'] !== 'all', fn (Builder $query) => $query->where('payment_status', $filters['payment_status']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('expense_date', '<=', $filters['date_to']))
            ->latest('expense_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $categories = ExpenseCategory::query()
            ->with('subcategories')
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120', 'unique:expense_categories,name'],
        ]);
        ExpenseCategory::create($data);

        return back()->with('success', 'Kategori pengeluaran ditambahkan.');
    }

    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120', Rule::unique('expense_categories', 'name')->ignore($category->id)],
        ]);
        $category->update($data);

        return back()->with('success', 'Kategori pengeluaran diperbarui.');
    }

    public function storeSubcategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'name' => [
                'required', 'string', 'min:3', 'max:120',
                Rule::unique('expense_subcategories', 'name')->where('expense_category_id', $request->integer('expense_category_id')),
            ],
        ]);
        ExpenseSubcategory::create($data);

        return back()->with('success', 'Subkategori pengeluaran ditambahkan.');
    }

    public function updateSubcategory(Request $request, ExpenseSubcategory $subcategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:3', 'max:120',
                Rule::unique('expense_subcategories', 'name')
                    ->where('expense_category_id', $subcategory->expense_category_id)
                    ->ignore($subcategory->id),
            ],
        ]);
        $subcategory->update($data);

        return back()->with('success', 'Subkategori pengeluaran diperbarui.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_subcategory_id' => [
                'required',
                'integer',
                Rule::exists('expense_subcategories', 'id')->where('expense_category_id', $request->integer('expense_category_id')),
            ],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'initial_payment' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
        ]);
        $initialPayment = (int) ($data['initial_payment'] ?? 0);

        if ($initialPayment > 0 && ! filled($data['payment_method'] ?? null)) {
            throw ValidationException::withMessages(['payment_method' => 'Pilih metode pembayaran awal.']);
        }

        if ($initialPayment > (int) $data['amount']) {
            throw ValidationException::withMessages(['initial_payment' => 'Pembayaran awal tidak boleh melebihi nilai pengeluaran.']);
        }

        return DB::transaction(function () use ($data, $request, $initialPayment): RedirectResponse {
            $code = $this->newExpenseCode();
            $expense = Expense::create([
                'expense_code' => $code,
                'reference_number' => $data['reference_number'] ?? null,
                'expense_date' => $data['expense_date'],
                'expense_category_id' => $data['expense_category_id'],
                'expense_subcategory_id' => $data['expense_subcategory_id'],
                'created_by' => $request->user()->id,
                'amount' => $data['amount'],
                'payment_status' => $this->paymentStatus($initialPayment, (int) $data['amount']),
                'description' => $data['description'] ?? null,
            ]);

            if ($initialPayment > 0) {
                $expense->payments()->create([
                    'paid_at' => today(),
                    'amount' => $initialPayment,
                    'payment_method' => $data['payment_method'],
                ]);
            }

            return back()->with('success', 'Pengeluaran '.$expense->expense_code.' berhasil dicatat.');
        }, attempts: 3);
    }

    public function storePayment(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_method' => ['required', 'in:cash,transfer'],
        ]);

        DB::transaction(function () use ($expense, $data): void {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            $paid = (int) $locked->payments()->sum('amount');
            $remaining = $locked->amount - $paid;

            if ($remaining <= 0 || (int) $data['amount'] > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa pengeluaran.']);
            }

            $locked->payments()->create($data);
            $locked->update(['payment_status' => $this->paymentStatus($paid + (int) $data['amount'], $locked->amount)]);
        }, attempts: 3);

        return back()->with('success', 'Pembayaran pengeluaran berhasil dicatat.');
    }

    private function paymentStatus(int $paid, int $amount): string
    {
        if ($paid >= $amount) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }

    private function newExpenseCode(): string
    {
        do {
            $code = 'BY-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Expense::query()->where('expense_code', $code)->exists());

        return $code;
    }
}
