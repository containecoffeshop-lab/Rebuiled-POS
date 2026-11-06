<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['material_stock_batch_id', 'transaction_id', 'type', 'quantity', 'description', 'unit_cost', 'cost_amount'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:6',
            'cost_amount' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MaterialStockBatch::class, 'material_stock_batch_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
