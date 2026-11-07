<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CycleCountTask extends Model
{
    protected $fillable = ['task_date', 'shift_number', 'status', 'note', 'created_by', 'counted_by', 'cashier_shift_id', 'counted_at', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['task_date' => 'date', 'shift_number' => 'integer', 'counted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CycleCountItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
