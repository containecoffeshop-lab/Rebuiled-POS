<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditTaskItem extends Model
{
    protected $fillable = [
        'material_id',
        'material_name',
        'unit_name',
        'system_quantity',
        'shift1_quantity',
        'shift2_quantity',
        'shift1_note',
        'shift2_note',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:3',
            'shift1_quantity' => 'decimal:3',
            'shift2_quantity' => 'decimal:3',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(StockAuditTask::class, 'stock_audit_task_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
