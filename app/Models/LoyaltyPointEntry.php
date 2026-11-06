<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointEntry extends Model
{
    protected $fillable = [
        'customer_id', 'transaction_id', 'transaction_return_id', 'entry_type', 'points', 'description',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function transactionReturn(): BelongsTo
    {
        return $this->belongsTo(TransactionReturn::class);
    }
}
