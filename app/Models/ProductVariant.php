<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'label', 'barcode', 'selling_price', 'minimum_quantity', 'is_available'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'integer',
            'minimum_quantity' => 'decimal:3',
            'is_available' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(ProductStockBatch::class);
    }
}
