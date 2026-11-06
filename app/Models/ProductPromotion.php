<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductPromotion extends Model
{
    protected $fillable = [
        'name',
        'discount_type',
        'discount_value',
        'minimum_quantity',
        'all_menus',
        'all_customers',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'minimum_quantity' => 'integer',
            'all_menus' => 'boolean',
            'all_customers' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'product_promotion_menus')->withTimestamps();
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'product_promotion_customers')->withTimestamps();
    }
}
