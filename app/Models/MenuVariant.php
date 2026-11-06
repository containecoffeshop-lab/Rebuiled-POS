<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuVariant extends Model
{
    protected $fillable = ['menu_id', 'name', 'additional_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'additional_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuVariantRecipe::class);
    }
}
