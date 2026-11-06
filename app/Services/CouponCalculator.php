<?php

namespace App\Services;

use App\Models\Coupon;

class CouponCalculator
{
    public function discount(Coupon $coupon, int $subtotal): int
    {
        $discount = $coupon->type === 'percentage'
            ? (int) floor($subtotal * (int) $coupon->value / 100)
            : (int) $coupon->value;

        return min($subtotal, $discount);
    }
}
