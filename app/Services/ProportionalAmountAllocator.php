<?php

namespace App\Services;

class ProportionalAmountAllocator
{
    public function allocate(int $total, array $weights): array
    {
        $denominator = array_sum($weights);

        if ($denominator <= 0 || $total <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $allocations = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            [$amount, $remainder] = $this->multiplyDivide((int) $weight, $total, $denominator);
            $allocations[$key] = ['amount' => $amount, 'remainder' => $remainder];
            $allocated += $amount;
        }

        $remaining = $total - $allocated;
        $keys = array_keys($allocations);
        usort($keys, fn ($left, $right) => (
            $allocations[$right]['remainder'] <=> $allocations[$left]['remainder']
        ) ?: ((int) $left <=> (int) $right));

        foreach (array_slice($keys, 0, $remaining) as $key) {
            $allocations[$key]['amount']++;
        }

        return array_map(fn (array $allocation) => $allocation['amount'], $allocations);
    }

    public function share(int $total, int $numerator, int $denominator): int
    {
        if ($total <= 0 || $numerator <= 0 || $denominator <= 0) {
            return 0;
        }

        return $this->multiplyDivide($total, min($numerator, $denominator), $denominator)[0];
    }

    private function multiplyDivide(int $value, int $multiplier, int $divisor): array
    {
        $quotient = 0;
        $remainder = 0;
        $valueQuotient = intdiv($value, $divisor);
        $valueRemainder = $value % $divisor;

        foreach (str_split(decbin($multiplier)) as $bit) {
            $quotient *= 2;
            $remainder *= 2;

            if ($remainder >= $divisor) {
                $quotient++;
                $remainder -= $divisor;
            }

            if ($bit === '1') {
                $quotient += $valueQuotient;
                $remainder += $valueRemainder;

                if ($remainder >= $divisor) {
                    $quotient++;
                    $remainder -= $divisor;
                }
            }
        }

        return [$quotient, $remainder];
    }
}
