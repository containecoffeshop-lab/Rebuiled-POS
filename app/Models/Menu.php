<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['category_id', 'name', 'description', 'image_path', 'selling_price', 'is_available', 'is_bundle', 'barcode'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'integer',
            'is_available' => 'boolean',
            'is_bundle' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuRecipe::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuVariant::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(MenuBundleItem::class, 'bundle_menu_id');
    }

    public function scopeAvailableForSale(Builder $query): Builder
    {
        return $query->where('is_available', true)
            ->where(fn (Builder $query) => $query
                ->where('is_bundle', false)
                ->orWhere(fn (Builder $query) => $query
                    ->whereHas('bundleItems')
                    ->whereDoesntHave('bundleItems.componentMenu', fn (Builder $components) => $components->where('is_available', false))));
    }
}
