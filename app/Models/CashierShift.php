<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = ['user_id', 'shift_number', 'starting_cash', 'ending_cash', 'expected_cash', 'cash_difference', 'closing_note', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'starting_cash' => 'integer',
            'shift_number' => 'integer',
            'ending_cash' => 'integer',
            'expected_cash' => 'integer',
            'cash_difference' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
