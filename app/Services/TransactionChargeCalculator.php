<?php

namespace App\Services;

use App\Models\StoreCharge;
use Illuminate\Support\Collection;

class TransactionChargeCalculator
{
    public function calculate(int $discountedSubtotal, bool $lock = false): Collection
    {
        $query = StoreCharge::query()
            ->where('is_active', true)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->map(function (StoreCharge $charge) use ($discountedSubtotal): array {
                $value = (string) $charge->value;
                $amount = str_ends_with($value, '%')
                    ? (int) round($discountedSubtotal * ((float) substr($value, 0, -1)) / 100)
                    : (int) $value;

                return [
                    'code' => $charge->code,
                    'name' => $charge->name,
                    'rate' => $value,
                    'amount' => $amount,
                ];
            })
            ->filter(fn (array $charge) => $charge['amount'] > 0)
            ->values();
    }
}
