<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStockBatch extends Model
{
    protected $fillable = ['product_variant_id', 'batch_code', 'expires_at', 'unit_cost'];

    protected function casts(): array
    {
        return ['unit_cost' => 'integer', 'expires_at' => 'date'];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }
}
