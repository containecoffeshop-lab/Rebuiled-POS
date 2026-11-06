<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAuditTask extends Model
{
    protected $fillable = [
        'task_code',
        'shift1_user_id',
        'shift2_user_id',
        'status',
        'note',
        'shift1_completed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'shift1_completed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function shift1User(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shift1_user_id');
    }

    public function shift2User(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shift2_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAuditTaskItem::class);
    }
}
