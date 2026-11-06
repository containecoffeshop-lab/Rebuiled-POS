<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionReturn extends Model
{
    protected $fillable = [
        'transaction_id', 'created_by', 'return_code', 'return_date',
        'refund_method', 'status', 'refund_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'refund_amount' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionReturnDetail::class);
    }

    public function pointEntries(): HasMany
    {
        return $this->hasMany(LoyaltyPointEntry::class);
    }
}
