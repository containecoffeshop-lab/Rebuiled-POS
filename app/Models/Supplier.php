<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['code', 'name', 'phone', 'email', 'address'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
