<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockMovement extends Model
{
    protected $fillable = ['transaction_id', 'type', 'quantity', 'unit_cost', 'cost_amount', 'description'];

    protected function casts(): array
    {
        return ['unit_cost' => 'integer', 'cost_amount' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductStockBatch::class, 'product_stock_batch_id');
    }
}
