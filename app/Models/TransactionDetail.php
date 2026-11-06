<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionDetail extends Model
{
    protected $fillable = ['menu_id', 'menu_variant_id', 'item_type', 'product_variant_id', 'item_name', 'price', 'quantity', 'line_total', 'promotion_discount', 'promotion_name', 'note'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'promotion_discount' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function menuVariant(): BelongsTo
    {
        return $this->belongsTo(MenuVariant::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function recipeDetails(): HasMany
    {
        return $this->hasMany(TransactionRecipeDetail::class);
    }

    public function returnDetails(): HasMany
    {
        return $this->hasMany(TransactionReturnDetail::class);
    }
}
