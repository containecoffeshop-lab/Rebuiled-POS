<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CycleCountItem extends Model
{
    protected $fillable = ['material_id', 'material_name', 'unit_name', 'system_quantity', 'counted_quantity', 'difference', 'stock_before_approval', 'stock_after_approval', 'note'];
}
