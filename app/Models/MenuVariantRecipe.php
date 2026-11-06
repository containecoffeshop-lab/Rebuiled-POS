<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuVariantRecipe extends Model
{
    protected $fillable = ['menu_variant_id', 'material_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuVariant::class, 'menu_variant_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
