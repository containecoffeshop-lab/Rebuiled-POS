<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialStockBatch;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\TransactionReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);

        $reportData = match ($filters['report']) {
            'sales' => $this->salesReport($filters),
            'purchases' => $this->purchaseReport($filters),
            'cash-flow' => $this->cashFlowReport($filters),
            'stock' => $this->stockReport($filters),
            'stock-card' => $this->stockCardReport($filters),
            'product-sales' => $this->productSalesReport($filters),
            'profit-loss' => $this->profitLossReport($filters),
        };

        return view('reports.index', [
            'filters' => $filters,
            'reportData' => $reportData,
            'materials' => Material::query()->with('unit')->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $reportData = match ($filters['report']) {
            'sales' => $this->salesReport($filters, export: true),
            'purchases' => $this->purchaseReport($filters, export: true),
            'cash-flow' => $this->cashFlowReport($filters, export: true),
            'stock' => $this->stockReport($filters, export: true),
            'stock-card' => $this->stockCardReport($filters, export: true),
            'product-sales' => $this->productSalesReport($filters, export: true),
            'profit-loss' => $this->profitLossReport($filters, export: true),
        };
        $rows = $reportData['rows'];
        $kind = $reportData['kind'];
        $materialName = $reportData['material']?->name ?? null;
        $filename = 'laporan-'.$kind.'-'.$filters['start_date'].'-'.$filters['end_date'].'.csv';

        return response()->streamDownload(function () use ($rows, $kind, $materialName): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");

            $headers = match ($kind) {
                'sales' => ['Tanggal', 'Invoice', 'Pelanggan', 'Telepon', 'Jenis', 'Kasir', 'Subtotal', 'Diskon', 'Total'],
                'purchases' => ['Tanggal', 'Kode pembelian', 'Pemasok', 'Status barang', 'Status pembayaran', 'Total', 'Dibayar', 'Pengurangan utang', 'Sisa utang'],
                'cash-flow' => ['Tanggal', 'Referensi', 'Jenis', 'Keterangan', 'Metode', 'Rekening', 'Arah', 'Jumlah'],
                'stock' => ['Bahan', 'Satuan', 'Batch', 'Kedaluwarsa', 'Status', 'Awal', 'Masuk', 'Keluar', 'Saldo'],
                'stock-card' => ['Tanggal', 'Bahan', 'Batch', 'Jenis', 'Jumlah', 'Saldo', 'Keterangan'],
                'product-sales' => ['Menu', 'Harga snapshot', 'Jumlah terjual', 'Nilai kotor item'],
                'profit-loss' => ['Tanggal bayar', 'Invoice', 'Pelanggan', 'Pendapatan transaksi di luar PJK', 'HPP saat pemakaian', 'Laba kotor sebelum refund', 'Status HPP'],
            };
            fputcsv($stream, $headers);

            foreach ($rows as $row) {
                $csvRows = match ($kind) {
                    'sales' => [[
                        $row->created_at->format('Y-m-d H:i:s'),
                        $row->invoice,
                        $row->customer_name,
                        $row->customer_phone,
                        $row->transaction_type,
                        $row->cashierShift?->user?->name,
                        $row->subtotal,
                        $row->discount,
                        $row->grand_total,
                    ]],
                    'purchases' => [[
                        $row->purchase_date->toDateString(),
                        $row->purchase_code,
                        $row->supplier?->name,
                        $row->status,
                        $row->payment_status,
                        $row->grand_total,
                        $row->payments->sum('amount'),
                        $row->returns->where('settlement_method', 'debt_reduction')->where('status', 'confirmed')->sum('amount'),
                        max(0, $row->grand_total
                            - $row->payments->sum('amount')
                            - $row->returns->where('settlement_method', 'debt_reduction')->whereIn('status', ['pending', 'confirmed'])->sum('amount')),
                    ]],
                    'cash-flow' => [[
                        $row->flow_date,
                        $row->reference,
                        $row->entry_type,
                        $row->description,
                        $row->payment_method,
                        $row->bank_account,
                        $row->direction,
                        $row->amount,
                    ]],
                    'stock' => $row['batches']->map(fn (array $batch) => [
                        $row['material'],
                        $row['unit'],
                        $batch['batch'],
                        $batch['expires_at'],
                        $batch['expired'] ? 'expired' : ($batch['closing'] <= 0 ? 'empty' : 'active'),
                        $batch['opening'],
                        $batch['in'],
                        $batch['out'],
                        $batch['closing'],
                    ])->all() ?: [[
                        $row['material'], $row['unit'], '', '', 'empty', 0, 0, 0, 0,
                    ]],
                    'stock-card' => [[
                        $row['date']->format('Y-m-d H:i:s'),
                        $materialName,
                        $row['batch'],
                        $row['type'],
                        $row['quantity'],
                        $row['balance'],
                        $row['description'],
                    ]],
                    'product-sales' => [[
                        $row->item_name,
                        $row->price,
                        $row->quantity,
                        $row->revenue,
                    ]],
                    'profit-loss' => [[
                        $row->paid_at?->format('Y-m-d H:i:s'),
                        $row->invoice,
                        $row->customer_name,
                        $row->recognized_revenue,
                        $row->cogs_complete ? $row->cogs_total : '',
                        $row->cogs_complete ? $row->recognized_revenue - $row->cogs_total : '',
                        $row->cogs_complete ? 'lengkap' : 'belum lengkap',
                    ]],
                };

                foreach ($csvRows as $csvRow) {
                    fputcsv($stream, array_map($this->safeCsvCell(...), $csvRow));
                }
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request): array
    {
        $filters = array_merge([
            'report' => 'sales',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => today()->toDateString(),
            'search' => '',
            'material_id' => null,
            'hide_zero' => false,
        ], $request->validate([
            'report' => ['nullable', 'in:sales,purchases,cash-flow,stock,stock-card,product-sales,profit-loss'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'search' => ['nullable', 'string', 'max:120'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'hide_zero' => ['nullable', 'boolean'],
        ]));
        $filters['report'] = $filters['report'] ?: 'sales';
        $filters['start_date'] = $filters['start_date'] ?: now()->startOfMonth()->toDateString();
        $filters['end_date'] = $filters['end_date'] ?: today()->toDateString();
        $filters['search'] = trim($filters['search'] ?? '');
        $filters['hide_zero'] = $request->boolean('hide_zero');

        if ($filters['start_date'] > $filters['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        return $filters;
    }

    private function salesReport(array $filters, bool $export = false): array
    {
        $sales = Transaction::query()
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $filters['start_date'])
            ->whereDate('created_at', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('invoice', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_phone', 'like', '%'.$filters['search'].'%');
            }))
            ->with('cashierShift.user')
            ->latest('created_at')
            ->when($export, fn (Builder $query) => $query->get(), fn (Builder $query) => $query->paginate(self::PER_PAGE)->withQueryString());

        $summaryQuery = Transaction::query()
            ->where('status', 'paid')
            ->whereDate('created_at', '>=', $filters['start_date'])
            ->whereDate('created_at', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('invoice', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_phone', 'like', '%'.$filters['search'].'%');
            }));
        $grossSales = (int) (clone $summaryQuery)->sum('subtotal');
        $discounts = (int) (clone $summaryQuery)->sum('discount');
        $netSales = (int) (clone $summaryQuery)->sum('grand_total');
        $salesReturns = (int) TransactionReturn::query()
            ->where('status', 'confirmed')
            ->where('refund_method', 'refund')
            ->whereDate('return_date', '>=', $filters['start_date'])
            ->whereDate('return_date', '<=', $filters['end_date'])
            ->whereHas('transaction', fn (Builder $query) => $query
                ->where('status', 'paid')
                ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                    $query->where('invoice', 'like', '%'.$filters['search'].'%')
                        ->orWhere('customer_name', 'like', '%'.$filters['search'].'%');
                })))
            ->sum('refund_amount');

        return [
            'title' => 'Penjualan',
            'summary' => [
                ['label' => 'Transaksi lunas', 'value' => number_format((clone $summaryQuery)->count(), 0, ',', '.')],
                ['label' => 'Subtotal sebelum diskon', 'value' => $this->rupiah($grossSales)],
                ['label' => 'Diskon transaksi', 'value' => $this->rupiah($discounts)],
                ['label' => 'Refund terkonfirmasi', 'value' => $this->rupiah($salesReturns)],
                ['label' => 'Penjualan setelah refund', 'value' => $this->rupiah($netSales - $salesReturns)],
            ],
            'rows' => $sales,
            'kind' => 'sales',
        ];
    }

    private function purchaseReport(array $filters, bool $export = false): array
    {
        $purchases = Purchase::query()
            ->with(['supplier', 'payments', 'returns'])
            ->whereDate('purchase_date', '>=', $filters['start_date'])
            ->whereDate('purchase_date', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('purchase_code', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'));
            }))
            ->latest('purchase_date')
            ->when($export, fn (Builder $query) => $query->get(), fn (Builder $query) => $query->paginate(self::PER_PAGE)->withQueryString());

        $summaryQuery = Purchase::query()
            ->whereDate('purchase_date', '>=', $filters['start_date'])
            ->whereDate('purchase_date', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('purchase_code', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'));
            }));
        $purchaseTotal = (int) (clone $summaryQuery)->sum('grand_total');
        $paidInRange = (int) PurchasePayment::query()
            ->whereDate('paid_at', '>=', $filters['start_date'])
            ->whereDate('paid_at', '<=', $filters['end_date'])
            ->whereHas('purchase', fn (Builder $query) => $query->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('purchase_code', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'));
            })))
            ->sum('amount');
        $debtReductionInRange = (int) PurchaseReturn::query()
            ->where('settlement_method', 'debt_reduction')
            ->where('status', 'confirmed')
            ->whereDate('return_date', '>=', $filters['start_date'])
            ->whereDate('return_date', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereHas('purchase', fn (Builder $purchase) => $purchase
                ->where('purchase_code', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'))))
            ->sum('amount');
        $supplierRefundInRange = (int) PurchaseReturn::query()
            ->where('settlement_method', 'refund')
            ->where('status', 'confirmed')
            ->whereDate('return_date', '>=', $filters['start_date'])
            ->whereDate('return_date', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereHas('purchase', fn (Builder $purchase) => $purchase
                ->where('purchase_code', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'))))
            ->sum('amount');

        return [
            'title' => 'Pembelian',
            'summary' => [
                ['label' => 'Pembelian', 'value' => $this->rupiah($purchaseTotal)],
                ['label' => 'Pembayaran pada periode', 'value' => $this->rupiah($paidInRange)],
                ['label' => 'Pengurangan utang', 'value' => $this->rupiah($debtReductionInRange)],
                ['label' => 'Refund pemasok', 'value' => $this->rupiah($supplierRefundInRange)],
            ],
            'rows' => $purchases,
            'kind' => 'purchases',
        ];
    }

    private function cashFlowReport(array $filters, bool $export = false): array
    {
        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $rows = DB::table('transactions')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'transactions.bank_account_id')
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $start)
            ->whereDate('paid_at', '<=', $end)
            ->selectRaw("DATE(paid_at) as flow_date, invoice as reference, 'Penerimaan penjualan' as entry_type, customer_name as description, payment_method, bank_accounts.bank_name as bank_account, grand_total as amount, 'Masuk' as direction")
            ->unionAll(DB::table('transaction_returns')
                ->join('transactions', 'transactions.id', '=', 'transaction_returns.transaction_id')
                ->where('transaction_returns.status', 'confirmed')
                ->where('transaction_returns.refund_method', 'refund')
                ->where('transaction_returns.refund_amount', '>', 0)
                ->whereDate('transaction_returns.return_date', '>=', $start)
                ->whereDate('transaction_returns.return_date', '<=', $end)
                ->selectRaw("transaction_returns.return_date as flow_date, transaction_returns.return_code as reference, 'Refund penjualan' as entry_type, transactions.invoice as description, 'refund' as payment_method, '' as bank_account, transaction_returns.refund_amount as amount, 'Keluar' as direction"))
            ->unionAll(DB::table('purchase_payments')
                ->join('purchases', 'purchases.id', '=', 'purchase_payments.purchase_id')
                ->whereDate('purchase_payments.paid_at', '>=', $start)
                ->whereDate('purchase_payments.paid_at', '<=', $end)
                ->selectRaw("purchase_payments.paid_at as flow_date, purchases.purchase_code as reference, 'Pembayaran pemasok' as entry_type, suppliers.name as description, purchase_payments.payment_method, '' as bank_account, purchase_payments.amount, 'Keluar' as direction")
                ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id'))
            ->unionAll(DB::table('expense_payments')
                ->join('expenses', 'expenses.id', '=', 'expense_payments.expense_id')
                ->whereDate('expense_payments.paid_at', '>=', $start)
                ->whereDate('expense_payments.paid_at', '<=', $end)
                ->selectRaw("expense_payments.paid_at as flow_date, expenses.expense_code as reference, 'Pembayaran pengeluaran' as entry_type, expenses.description, expense_payments.payment_method, '' as bank_account, expense_payments.amount, 'Keluar' as direction"))
            ->unionAll(DB::table('purchase_returns')
                ->join('purchases', 'purchases.id', '=', 'purchase_returns.purchase_id')
                ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->where('purchase_returns.status', 'confirmed')
                ->where('purchase_returns.settlement_method', 'refund')
                ->where('purchase_returns.amount', '>', 0)
                ->whereDate('purchase_returns.return_date', '>=', $start)
                ->whereDate('purchase_returns.return_date', '<=', $end)
                ->selectRaw("purchase_returns.return_date as flow_date, purchase_returns.return_code as reference, 'Refund pemasok' as entry_type, suppliers.name as description, 'refund' as payment_method, '' as bank_account, purchase_returns.amount, 'Masuk' as direction"));

        $flowQuery = DB::query()->fromSub($rows, 'cash_flows')
            ->when($filters['search'] !== '', fn (QueryBuilder $query) => $query->where(function (QueryBuilder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('reference', 'like', $search)
                    ->orWhere('entry_type', 'like', $search)
                    ->orWhere('description', 'like', $search);
            }));
        $incoming = (int) (clone $flowQuery)->where('direction', 'Masuk')->sum('amount');
        $outgoing = (int) (clone $flowQuery)->where('direction', 'Keluar')->sum('amount');
        $paginatedRows = (clone $flowQuery)
            ->orderByDesc('flow_date')
            ->orderByDesc('reference')
            ->when($export, fn (QueryBuilder $query) => $query->get(), fn (QueryBuilder $query) => $query->paginate(self::PER_PAGE)->withQueryString());

        return [
            'title' => 'Arus kas',
            'summary' => [
                ['label' => 'Kas masuk', 'value' => $this->rupiah($incoming)],
                ['label' => 'Kas keluar', 'value' => $this->rupiah($outgoing)],
                ['label' => 'Selisih periode', 'value' => $this->rupiah($incoming - $outgoing)],
                ['label' => 'Transaksi kas', 'value' => number_format(
                    $paginatedRows instanceof LengthAwarePaginator ? $paginatedRows->total() : $paginatedRows->count(),
                    0,
                    ',',
                    '.',
                )],
            ],
            'rows' => $paginatedRows,
            'kind' => 'cash-flow',
        ];
    }

    private function stockReport(array $filters, bool $export = false): array
    {
        $batches = MaterialStockBatch::query()->with(['material.unit'])->get();
        $movementBalances = DB::table('stock_movements')
            ->whereIn('material_stock_batch_id', $batches->pluck('id'))
            ->whereDate('created_at', '<=', $filters['end_date'])
            ->select('material_stock_batch_id')
            ->selectRaw(
                "SUM(CASE WHEN DATE(created_at) < ? THEN CASE WHEN type = 'in' THEN quantity ELSE -quantity END ELSE 0 END) AS opening_quantity",
                [$filters['start_date']],
            )
            ->selectRaw(
                "SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND type = 'in' THEN quantity ELSE 0 END) AS period_in",
                [$filters['start_date'], $filters['end_date']],
            )
            ->selectRaw(
                "SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND type = 'out' THEN quantity ELSE 0 END) AS period_out",
                [$filters['start_date'], $filters['end_date']],
            )
            ->groupBy('material_stock_batch_id')
            ->get()
            ->keyBy('material_stock_batch_id');

        $batchRows = $batches->map(function (MaterialStockBatch $batch) use ($movementBalances, $filters): array {
            $movement = $movementBalances->get($batch->id);
            $opening = (float) ($movement->opening_quantity ?? 0);
            $in = (float) ($movement->period_in ?? 0);
            $out = (float) ($movement->period_out ?? 0);
            $closing = round($opening + $in - $out, 3);
            $isExpired = $batch->expires_at && $batch->expires_at->toDateString() < $filters['end_date'];

            return [
                'material_id' => $batch->material_id,
                'material' => $batch->material?->name ?? 'Bahan tidak tersedia',
                'unit' => $batch->material?->unit?->name ?? '',
                'minimum' => (float) ($batch->material?->minimum_quantity ?? 0),
                'batch' => $batch->batch_code,
                'expires_at' => $batch->expires_at?->toDateString(),
                'opening' => $opening,
                'in' => $in,
                'out' => $out,
                'closing' => $closing,
                'expired' => (bool) $isExpired,
            ];
        });
        $materialRows = $batchRows->groupBy('material_id')->map(function (Collection $rows): array {
            $first = $rows->first();

            return [
                'material_id' => $first['material_id'],
                'material' => $first['material'],
                'unit' => $first['unit'],
                'minimum' => $first['minimum'],
                'current' => round($rows->where('expired', false)->sum('closing'), 3),
                'expired' => round($rows->where('expired', true)->sum('closing'), 3),
                'opening' => round($rows->sum('opening'), 3),
                'in' => round($rows->sum('in'), 3),
                'out' => round($rows->sum('out'), 3),
                'closing' => round($rows->sum('closing'), 3),
                'batches' => $rows->values(),
            ];
        });

        foreach (Material::query()->with('unit')->get() as $material) {
            if (! $materialRows->has($material->id)) {
                $materialRows->put($material->id, [
                    'material_id' => $material->id,
                    'material' => $material->name,
                    'unit' => $material->unit?->name ?? '',
                    'minimum' => (float) $material->minimum_quantity,
                    'current' => 0.0,
                    'expired' => 0.0,
                    'opening' => 0.0,
                    'in' => 0.0,
                    'out' => 0.0,
                    'closing' => 0.0,
                    'batches' => collect(),
                ]);
            }
        }

        $filtered = $materialRows
            ->filter(fn (array $row) => $filters['search'] === ''
                || stripos($row['material'], $filters['search']) !== false
                || $row['batches']->contains(fn (array $batch) => stripos($batch['batch'], $filters['search']) !== false))
            ->when($filters['hide_zero'], fn (Collection $rows) => $rows->filter(fn (array $row) => $row['current'] != 0))
            ->sortBy('material')
            ->values();
        $shownMaterialIds = $filtered->pluck('material_id')->all();
        $expiredBatchCount = $batchRows->filter(fn (array $row) => in_array($row['material_id'], $shownMaterialIds, true)
            && $row['expired']
            && $row['closing'] > 0)->count();
        $availableMaterialCount = $filtered->where('current', '>', 0)->count();
        $belowMinimumCount = $filtered->filter(fn (array $row) => $row['current'] < $row['minimum'])->count();

        return [
            'title' => 'Stok bahan',
            'summary' => [
                ['label' => 'Bahan ditampilkan', 'value' => number_format($filtered->count(), 0, ',', '.')],
                ['label' => 'Bahan dengan stok tersedia', 'value' => number_format($availableMaterialCount, 0, ',', '.')],
                ['label' => 'Bahan di bawah minimum', 'value' => number_format($belowMinimumCount, 0, ',', '.')],
                ['label' => 'Batch kedaluwarsa tersisa', 'value' => number_format($expiredBatchCount, 0, ',', '.')],
                ['label' => 'Posisi per tanggal', 'value' => $filters['end_date']],
            ],
            'rows' => $export ? $filtered : $this->paginateCollection($filtered, self::PER_PAGE),
            'kind' => 'stock',
        ];
    }

    private function stockCardReport(array $filters, bool $export = false): array
    {
        $material = filled($filters['material_id'])
            ? Material::query()->with('unit')->find($filters['material_id'])
            : null;

        if (! $material) {
            return [
                'title' => 'Kartu stok bahan',
                'summary' => [],
                'rows' => $export ? collect() : $this->paginateCollection(collect(), self::PER_PAGE),
                'kind' => 'stock-card',
                'material' => null,
            ];
        }

        $batchIds = MaterialStockBatch::query()->where('material_id', $material->id)->select('id');
        $opening = (float) DB::table('stock_movements')
            ->whereIn('material_stock_batch_id', $batchIds)
            ->whereDate('created_at', '<', $filters['start_date'])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as balance")
            ->value('balance');
        $movements = StockMovement::query()
            ->with('batch')
            ->whereHas('batch', fn (Builder $query) => $query->where('material_id', $material->id))
            ->whereDate('created_at', '>=', $filters['start_date'])
            ->whereDate('created_at', '<=', $filters['end_date'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $balance = $opening;
        $rows = $movements->map(function (StockMovement $movement) use (&$balance): array {
            $quantity = (float) $movement->quantity;
            $balance += $movement->type === 'in' ? $quantity : -$quantity;

            return [
                'date' => $movement->created_at,
                'batch' => $movement->batch?->batch_code ?? '—',
                'type' => $movement->type,
                'quantity' => $quantity,
                'balance' => round($balance, 3),
                'description' => $movement->description,
            ];
        });

        return [
            'title' => 'Kartu stok bahan',
            'summary' => [
                ['label' => 'Bahan', 'value' => $material->name],
                ['label' => 'Stok awal', 'value' => number_format($opening, 3, ',', '.').' '.$material->unit?->name],
                ['label' => 'Masuk periode', 'value' => number_format($movements->where('type', 'in')->sum('quantity'), 3, ',', '.').' '.$material->unit?->name],
                ['label' => 'Keluar periode', 'value' => number_format($movements->where('type', 'out')->sum('quantity'), 3, ',', '.').' '.$material->unit?->name],
            ],
            'rows' => $export ? $rows : $this->paginateCollection($rows, self::PER_PAGE),
            'kind' => 'stock-card',
            'material' => $material,
        ];
    }

    private function productSalesReport(array $filters, bool $export = false): array
    {
        $rows = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.status', 'paid')
            ->whereDate('transactions.created_at', '>=', $filters['start_date'])
            ->whereDate('transactions.created_at', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (QueryBuilder $query) => $query->where('transaction_details.item_name', 'like', '%'.$filters['search'].'%'))
            ->select('transaction_details.item_name', 'transaction_details.price')
            ->selectRaw('SUM(transaction_details.quantity) as quantity, SUM(transaction_details.line_total) as revenue')
            ->groupBy('transaction_details.item_name', 'transaction_details.price')
            ->orderByDesc('quantity')
            ->orderBy('transaction_details.item_name')
            ->when($export, fn (QueryBuilder $query) => $query->get(), fn (QueryBuilder $query) => $query->paginate(self::PER_PAGE)->withQueryString());
        $summary = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.status', 'paid')
            ->whereDate('transactions.created_at', '>=', $filters['start_date'])
            ->whereDate('transactions.created_at', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (QueryBuilder $query) => $query->where('transaction_details.item_name', 'like', '%'.$filters['search'].'%'))
            ->selectRaw('COALESCE(SUM(transaction_details.quantity), 0) as quantity, COALESCE(SUM(transaction_details.line_total), 0) as revenue')
            ->first();

        return [
            'title' => 'Produk terjual',
            'summary' => [
                ['label' => 'Jumlah item terjual', 'value' => number_format((int) $summary->quantity, 0, ',', '.')],
                ['label' => 'Nilai kotor item', 'value' => $this->rupiah((int) $summary->revenue)],
            ],
            'rows' => $rows,
            'kind' => 'product-sales',
        ];
    }

    private function profitLossReport(array $filters, bool $export = false): array
    {
        $salesQuery = Transaction::query()
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $filters['start_date'])
            ->whereDate('paid_at', '<=', $filters['end_date'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('invoice', 'like', '%'.$filters['search'].'%')
                    ->orWhere('customer_name', 'like', '%'.$filters['search'].'%');
            }));

        $salesCount = (clone $salesQuery)->count();
        $completeSalesQuery = (clone $salesQuery)->where('cogs_complete', true);
        $incompleteCount = $salesCount - (clone $completeSalesQuery)->count();
        $salesRevenue = (int) (clone $salesQuery)->sum(DB::raw('subtotal - discount'))
            + (int) TransactionCharge::query()
                ->where('code', 'OPR')
                ->whereHas('transaction', fn (Builder $query) => $query
                    ->where('status', 'paid')
                    ->whereDate('paid_at', '>=', $filters['start_date'])
                    ->whereDate('paid_at', '<=', $filters['end_date'])
                    ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                        $query->where('invoice', 'like', '%'.$filters['search'].'%')
                            ->orWhere('customer_name', 'like', '%'.$filters['search'].'%');
                    })))
                ->sum('amount');
        $completeSalesRevenue = (int) (clone $completeSalesQuery)->sum(DB::raw('subtotal - discount'))
            + (int) TransactionCharge::query()
                ->where('code', 'OPR')
                ->whereHas('transaction', fn (Builder $query) => $query
                    ->where('status', 'paid')
                    ->where('cogs_complete', true)
                    ->whereDate('paid_at', '>=', $filters['start_date'])
                    ->whereDate('paid_at', '<=', $filters['end_date'])
                    ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                        $query->where('invoice', 'like', '%'.$filters['search'].'%')
                            ->orWhere('customer_name', 'like', '%'.$filters['search'].'%');
                    })))
                ->sum('amount');
        $cogs = (int) (clone $completeSalesQuery)->sum('cogs_total');
        $refundsQuery = TransactionReturn::query()
            ->where('status', 'confirmed')
            ->where('refund_method', 'refund')
            ->whereDate('return_date', '>=', $filters['start_date'])
            ->whereDate('return_date', '<=', $filters['end_date'])
            ->whereHas('transaction', fn (Builder $query) => $query
                ->where('status', 'paid')
                ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                    $query->where('invoice', 'like', '%'.$filters['search'].'%')
                        ->orWhere('customer_name', 'like', '%'.$filters['search'].'%');
                })));
        $refunds = (int) (clone $refundsQuery)->sum('refund_amount');
        $refundRows = (clone $refundsQuery)->with('transaction.charges')->get();
        $recognizedRefunds = (int) $refundRows->sum(fn (TransactionReturn $refund): int => $this->recognizedRefund($refund));
        $completeRefunds = (int) $refundRows
            ->filter(fn (TransactionReturn $refund) => $refund->transaction?->cogs_complete)
            ->sum(fn (TransactionReturn $refund): int => $this->recognizedRefund($refund));
        $netSales = $salesRevenue - $recognizedRefunds;
        $verifiedProfit = $completeSalesRevenue - $completeRefunds - $cogs;
        $rows = (clone $salesQuery)
            ->with('charges')
            ->select(['id', 'invoice', 'customer_name', 'paid_at', 'subtotal', 'discount', 'grand_total', 'cogs_total', 'cogs_complete'])
            ->latest('paid_at')
            ->when($export, fn (Builder $query) => $query->get(), fn (Builder $query) => $query->paginate(self::PER_PAGE)->withQueryString());
        $rowItems = $rows instanceof LengthAwarePaginator ? $rows->getCollection() : $rows;
        $rowItems->each(function (Transaction $transaction): void {
            $transaction->setAttribute(
                'recognized_revenue',
                (int) $transaction->subtotal - (int) $transaction->discount + (int) $transaction->charges->where('code', 'OPR')->sum('amount'),
            );
        });

        return [
            'title' => 'Laba rugi kotor',
            'summary' => [
                ['label' => 'Pendapatan transaksi di luar PJK', 'value' => $this->rupiah($salesRevenue)],
                ['label' => 'Refund terkonfirmasi (termasuk PJK)', 'value' => $this->rupiah($refunds)],
                ['label' => 'Refund pengurang pendapatan (di luar PJK)', 'value' => $this->rupiah($recognizedRefunds)],
                ['label' => 'Penjualan setelah refund', 'value' => $this->rupiah($netSales)],
                ['label' => 'HPP transaksi lengkap', 'value' => $this->rupiah($cogs)],
                ['label' => 'Laba kotor dengan HPP lengkap', 'value' => $this->rupiah($verifiedProfit)],
                ['label' => 'HPP lengkap / belum lengkap', 'value' => number_format($salesCount - $incompleteCount, 0, ',', '.').' / '.number_format($incompleteCount, 0, ',', '.')],
                ['label' => 'Laba total periode', 'value' => $incompleteCount === 0 ? $this->rupiah($verifiedProfit) : 'Belum tersedia'],
            ],
            'rows' => $rows,
            'kind' => 'profit-loss',
            'incomplete_count' => $incompleteCount,
        ];
    }

    private function recognizedRefund(TransactionReturn $refund): int
    {
        $transaction = $refund->transaction;

        if (! $transaction || (int) $transaction->grand_total <= 0) {
            return 0;
        }

        $recognizedRevenue = (int) $transaction->subtotal
            - (int) $transaction->discount
            + (int) $transaction->charges->where('code', 'OPR')->sum('amount');

        return (int) round((int) $refund->refund_amount * $recognizedRevenue / (int) $transaction->grand_total);
    }

    private function paginateCollection(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }

    private function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function safeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
