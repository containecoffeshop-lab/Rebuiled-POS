<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCharge extends Model
{
    protected $fillable = ['code', 'name', 'value', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
