<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnDetail;
use App\Services\LoyaltyProgram;
use App\Services\ProportionalAmountAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionReturnController extends Controller
{
    public function index(Request $request): View
    {
        $filters = array_merge(
            ['search' => '', 'status' => 'all', 'transaction_id' => null],
            array_filter($request->validate([
                'search' => ['nullable', 'string', 'max:120'],
                'status' => ['nullable', 'in:all,pending,confirmed,cancelled'],
                'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            ]), fn ($value) => $value !== null),
        );

        $eligibleTransactions = Transaction::query()
            ->where('status', 'paid')
            ->with(['details.returnDetails.transactionReturn'])
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('invoice', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_phone', 'like', $search);
            }))
            ->latest('created_at')
            ->paginate(10, ['*'], 'transactions_page')
            ->withQueryString();

        $selectedTransaction = filled($filters['transaction_id'])
            ? Transaction::query()
                ->where('status', 'paid')
                ->with(['details.returnDetails.transactionReturn'])
                ->find($filters['transaction_id'])
            : null;

        $returns = TransactionReturn::query()
            ->with(['transaction', 'creator', 'details.transactionDetail', 'pointEntries'])
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('return_code', 'like', $search)
                    ->orWhereHas('transaction', fn (Builder $transactions) => $transactions
                        ->where('invoice', 'like', $search)
                        ->orWhere('customer_name', 'like', $search));
            }))
            ->latest('return_date')
            ->latest('id')
            ->paginate(10, ['*'], 'returns_page')
            ->withQueryString();

        return view('returns.index', compact('eligibleTransactions', 'selectedTransaction', 'returns', 'filters'));
    }

    public function store(Request $request, ProportionalAmountAllocator $allocator): RedirectResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'refund_method' => ['required', 'in:refund,replacement'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.transaction_detail_id' => ['required', 'integer', 'distinct', 'exists:transaction_details,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);
        $selectedItems = collect($data['items'])->filter(fn (array $item) => filled($item['quantity'] ?? null));

        if ($selectedItems->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pilih setidaknya satu item dan jumlah yang dikembalikan.']);
        }

        foreach ($selectedItems as $index => $item) {
            if (! filled($item['reason'] ?? null)) {
                throw ValidationException::withMessages(["items.$index.reason" => 'Alasan retur wajib diisi untuk item yang dipilih.']);
            }
        }

        $return = DB::transaction(function () use ($data, $request, $selectedItems, $allocator): TransactionReturn {
            $sale = Transaction::query()->lockForUpdate()->findOrFail($data['transaction_id']);

            if ($sale->status !== 'paid') {
                throw ValidationException::withMessages(['transaction_id' => 'Retur hanya dapat dibuat untuk transaksi yang sudah lunas.']);
            }

            $details = $sale->details()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $detailIds = $selectedItems->pluck('transaction_detail_id')->map(fn ($id) => (int) $id);

            if ($detailIds->contains(fn (int $id) => ! $details->has($id))) {
                throw ValidationException::withMessages(['items' => 'Item retur tidak sesuai dengan transaksi yang dipilih.']);
            }

            $alreadyReturned = TransactionReturnDetail::query()
                ->whereIn('transaction_detail_id', $detailIds)
                ->whereHas('transactionReturn', fn (Builder $query) => $query->whereIn('status', ['pending', 'confirmed']))
                ->select('transaction_detail_id')
                ->selectRaw('SUM(quantity) AS returned_quantity')
                ->groupBy('transaction_detail_id')
                ->pluck('returned_quantity', 'transaction_detail_id');

            foreach ($selectedItems as $item) {
                $detailId = (int) $item['transaction_detail_id'];
                $detail = $details->get($detailId);
                $remaining = $detail->quantity - (int) $alreadyReturned->get($detailId, 0);

                if ((int) $item['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah retur '.$detail->item_name.' melebihi sisa yang dapat diretur ('.$remaining.').',
                    ]);
                }
            }

            $netAmounts = $allocator->allocate(
                (int) $sale->grand_total,
                $details->mapWithKeys(fn (TransactionDetail $detail) => [
                    $detail->id => max(0, (int) $detail->line_total - (int) $detail->promotion_discount),
                ])->all(),
            );
            $return = TransactionReturn::create([
                'transaction_id' => $sale->id,
                'created_by' => $request->user()->id,
                'return_code' => $this->newReturnCode(),
                'return_date' => $data['return_date'],
                'refund_method' => $data['refund_method'],
                'status' => 'pending',
                'refund_amount' => 0,
                'notes' => $data['notes'] ?? null,
            ]);
            $totalRefund = 0;

            foreach ($selectedItems as $item) {
                $detailId = (int) $item['transaction_detail_id'];
                $detail = $details->get($detailId);
                $previousQuantity = (int) $alreadyReturned->get($detailId, 0);
                $newQuantity = $previousQuantity + (int) $item['quantity'];
                $lineRefund = $allocator->share($netAmounts[$detailId] ?? 0, $newQuantity, $detail->quantity)
                    - $allocator->share($netAmounts[$detailId] ?? 0, $previousQuantity, $detail->quantity);
                $refundAmount = $data['refund_method'] === 'refund' ? $lineRefund : 0;
                $totalRefund += $refundAmount;

                $return->details()->create([
                    'transaction_detail_id' => $detailId,
                    'quantity' => $item['quantity'],
                    'refund_amount' => $refundAmount,
                    'reason' => trim($item['reason']),
                ]);
            }

            $return->update(['refund_amount' => $totalRefund]);

            return $return;
        }, attempts: 3);

        return redirect()->route('pos.returns.index', [
            'search' => Transaction::query()->whereKey($data['transaction_id'])->value('invoice'),
        ])->with('success', 'Permintaan retur '.$return->return_code.' berhasil dicatat dan menunggu konfirmasi.');
    }

    public function confirm(TransactionReturn $transactionReturn, LoyaltyProgram $loyalty): RedirectResponse
    {
        $pointsReversed = DB::transaction(function () use ($transactionReturn, $loyalty): int {
            $preview = TransactionReturn::query()->findOrFail($transactionReturn->id);
            $sale = Transaction::query()->lockForUpdate()->findOrFail($preview->transaction_id);
            $locked = TransactionReturn::query()->lockForUpdate()->findOrFail($transactionReturn->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['return' => 'Hanya retur yang menunggu konfirmasi dapat diproses.']);
            }

            $locked->update(['status' => 'confirmed']);

            return $loyalty->reverseForReturn($locked, $sale);
        }, attempts: 3);

        return back()->with('success', 'Retur dikonfirmasi. Koreksi '.$pointsReversed.' poin dicatat; penggantian non-tunai tidak membuat pesanan baru atau mengubah stok.');
    }

    public function cancel(TransactionReturn $transactionReturn): RedirectResponse
    {
        DB::transaction(function () use ($transactionReturn): void {
            $locked = TransactionReturn::query()->lockForUpdate()->findOrFail($transactionReturn->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['return' => 'Hanya retur yang masih menunggu konfirmasi dapat dibatalkan.']);
            }

            $locked->update(['status' => 'cancelled']);
        }, attempts: 3);

        return back()->with('success', 'Permintaan retur dibatalkan; jumlah item kembali dapat diretur.');
    }

    private function newReturnCode(): string
    {
        do {
            $code = 'RPJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (TransactionReturn::query()->where('return_code', $code)->exists());

        return $code;
    }
}
