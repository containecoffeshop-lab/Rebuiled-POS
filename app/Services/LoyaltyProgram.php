<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyPointEntry;
use App\Models\StoreSetting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyProgram
{
    public function quote(?Customer $customer, int $points, int $discountableAmount): array
    {
        $balance = $customer ? $this->balance($customer->id) : 0;
        $pointValue = $this->pointValue();
        $discount = 0;

        if ($points > 0) {
            if (! $customer) {
                throw ValidationException::withMessages(['redeem_points' => 'Masukkan nomor telepon pelanggan terdaftar untuk menukar poin.']);
            }

            if ($pointValue < 1) {
                throw ValidationException::withMessages(['redeem_points' => 'Penukaran poin belum diaktifkan pengelola.']);
            }

            if ($points > $balance) {
                throw ValidationException::withMessages(['redeem_points' => 'Saldo poin pelanggan tidak mencukupi. Saldo saat ini: '.$balance.' poin.']);
            }

            $maximumPoints = intdiv(max(0, $discountableAmount), $pointValue);

            if ($points > $maximumPoints) {
                throw ValidationException::withMessages(['redeem_points' => 'Jumlah poin melebihi nilai belanja setelah diskon lainnya. Maksimal '.$maximumPoints.' poin.']);
            }

            $discount = $points * $pointValue;
        }

        return [
            'balance' => $balance,
            'points' => $points,
            'discount' => $discount,
        ];
    }

    public function balance(int $customerId): int
    {
        return (int) LoyaltyPointEntry::query()->where('customer_id', $customerId)->sum('points');
    }

    public function recordSale(Transaction $transaction, int $redeemedPoints = 0): array
    {
        if (! $transaction->customer_id) {
            if ($redeemedPoints > 0) {
                throw ValidationException::withMessages(['redeem_points' => 'Pelanggan dengan nomor telepon terdaftar wajib dipilih untuk menukar poin.']);
            }

            return ['earned' => 0, 'redeemed' => 0, 'discount' => 0];
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($transaction->customer_id);
        $discountable = max(
            0,
            (int) $transaction->subtotal - (int) $transaction->promotion_discount - (int) $transaction->coupon_discount,
        );
        $quote = $this->quote($customer, $redeemedPoints, $discountable);
        $earningThreshold = $this->earningThreshold();
        $earningBase = $discountable - $quote['discount'];
        $earnedPoints = $earningThreshold > 0 ? intdiv($earningBase, $earningThreshold) : 0;

        if ($earnedPoints > 0) {
            $customer->pointEntries()->create([
                'transaction_id' => $transaction->id,
                'entry_type' => 'earned',
                'points' => $earnedPoints,
                'description' => 'Poin transaksi '.$transaction->invoice,
            ]);
        }

        if ($redeemedPoints > 0) {
            $customer->pointEntries()->create([
                'transaction_id' => $transaction->id,
                'entry_type' => 'redeemed',
                'points' => -$redeemedPoints,
                'description' => 'Penukaran poin transaksi '.$transaction->invoice,
            ]);
        }

        $transaction->update([
            'loyalty_points_earned' => $earnedPoints,
            'loyalty_points_redeemed' => $redeemedPoints,
            'loyalty_discount' => $quote['discount'],
        ]);

        return ['earned' => $earnedPoints, 'redeemed' => $redeemedPoints, 'discount' => $quote['discount']];
    }

    public function reverseForReturn(TransactionReturn $return, Transaction $transaction): int
    {
        $earned = (int) $transaction->loyalty_points_earned;

        $earningBase = max(
            0,
            (int) $transaction->subtotal
                - (int) $transaction->promotion_discount
                - (int) $transaction->coupon_discount
                - (int) $transaction->loyalty_discount,
        );

        if (! $transaction->customer_id || $earned < 1 || $earningBase < 1) {
            return 0;
        }

        Customer::query()->whereKey($transaction->customer_id)->lockForUpdate()->firstOrFail();
        $details = TransactionDetail::query()
            ->where('transaction_id', $transaction->id)
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $earningBaseByDetail = app(ProportionalAmountAllocator::class)->allocate(
            $earningBase,
            $details->mapWithKeys(fn (TransactionDetail $detail) => [
                $detail->id => max(0, (int) $detail->line_total - (int) $detail->promotion_discount),
            ])->all(),
        );
        $confirmedReturnIds = TransactionReturn::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', 'confirmed')
            ->select('id');
        $returnedQuantities = TransactionReturnDetail::query()
            ->whereIn('transaction_return_id', $confirmedReturnIds)
            ->whereIn('transaction_detail_id', $details->keys())
            ->select('transaction_detail_id')
            ->selectRaw('SUM(quantity) AS returned_quantity')
            ->groupBy('transaction_detail_id')
            ->pluck('returned_quantity', 'transaction_detail_id');
        $allocator = app(ProportionalAmountAllocator::class);
        $returnedEarningBase = $details->sum(fn (TransactionDetail $detail): int => $allocator->share(
            (int) ($earningBaseByDetail[$detail->id] ?? 0),
            min((int) $returnedQuantities->get($detail->id, 0), (int) $detail->quantity),
            (int) $detail->quantity,
        ));
        $alreadyReversed = abs((int) LoyaltyPointEntry::query()
            ->where('transaction_id', $transaction->id)
            ->where('entry_type', 'return_reversal')
            ->sum('points'));
        $targetReversal = min(
            $earned,
            $allocator->share($earned, $returnedEarningBase, $earningBase),
        );
        $pointsToReverse = max(0, $targetReversal - $alreadyReversed);

        if ($pointsToReverse > 0) {
            DB::table('loyalty_point_entries')->insert([
                'customer_id' => $transaction->customer_id,
                'transaction_id' => $transaction->id,
                'transaction_return_id' => $return->id,
                'entry_type' => 'return_reversal',
                'points' => -$pointsToReverse,
                'description' => 'Koreksi poin retur '.$return->return_code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $pointsToReverse;
    }

    public function earningThreshold(): int
    {
        return max(0, (int) StoreSetting::query()->where('key', 'loyalty_earning_threshold')->value('value'));
    }

    public function pointValue(): int
    {
        return max(0, (int) StoreSetting::query()->where('key', 'loyalty_point_value')->value('value'));
    }
}
