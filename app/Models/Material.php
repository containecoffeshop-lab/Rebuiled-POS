<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = ['unit_id', 'supplier_id', 'name', 'minimum_quantity'];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'decimal:3'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuRecipe::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MaterialStockBatch::class);
    }
}
