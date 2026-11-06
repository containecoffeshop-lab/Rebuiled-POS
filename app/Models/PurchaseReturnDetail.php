<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnDetail extends Model
{
    protected $fillable = [
        'purchase_return_id', 'purchase_item_id', 'quantity', 'amount',
        'replacement_expires_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'amount' => 'integer',
            'replacement_expires_at' => 'date',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
