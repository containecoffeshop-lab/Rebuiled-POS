<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionReturnDetail extends Model
{
    protected $fillable = [
        'transaction_detail_id', 'quantity', 'refund_amount', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refund_amount' => 'integer',
        ];
    }

    public function transactionReturn(): BelongsTo
    {
        return $this->belongsTo(TransactionReturn::class);
    }

    public function transactionDetail(): BelongsTo
    {
        return $this->belongsTo(TransactionDetail::class);
    }
}
