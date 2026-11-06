<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialStockBatch extends Model
{
    protected $fillable = ['material_id', 'batch_code', 'expires_at', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'unit_cost' => 'decimal:6',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
