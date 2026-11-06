<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'invoice', 'transaction_kind', 'cashier_shift_id', 'customer_name', 'customer_phone', 'transaction_type',
        'status', 'paid_at', 'kitchen_status', 'payment_method', 'bank_account_id', 'subtotal', 'discount', 'promotion_discount',
        'coupon_discount', 'grand_total',
        'amount_paid', 'change_due', 'notes', 'customer_id', 'dining_table_id', 'coupon_id', 'coupon_code',
        'loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_discount', 'employee_id',
        'cogs_total', 'cogs_complete',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'promotion_discount' => 'integer',
            'coupon_discount' => 'integer',
            'grand_total' => 'integer',
            'amount_paid' => 'integer',
            'change_due' => 'integer',
            'paid_at' => 'datetime',
            'cogs_total' => 'integer',
            'cogs_complete' => 'boolean',
            'loyalty_points_earned' => 'integer',
            'loyalty_points_redeemed' => 'integer',
            'loyalty_discount' => 'integer',
        ];
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(TransactionCharge::class)->orderBy('id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
