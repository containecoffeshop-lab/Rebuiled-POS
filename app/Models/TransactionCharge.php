<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionCharge extends Model
{
    protected $fillable = ['code', 'name', 'rate', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
