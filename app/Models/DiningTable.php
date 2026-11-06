<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $fillable = ['number', 'capacity', 'status'];

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table): void {
            $table->qr_token ??= (string) Str::uuid();
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
