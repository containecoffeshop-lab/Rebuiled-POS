<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeTemplate extends Model
{
    protected $fillable = [
        'name',
        'paper_type',
        'paper_width_mm',
        'paper_height_mm',
        'label_width_mm',
        'label_height_mm',
        'gap_horizontal_mm',
        'gap_vertical_mm',
        'margin_top_mm',
        'margin_left_mm',
        'columns',
        'rows',
    ];

    protected function casts(): array
    {
        return [
            'paper_width_mm' => 'float',
            'paper_height_mm' => 'float',
            'label_width_mm' => 'float',
            'label_height_mm' => 'float',
            'gap_horizontal_mm' => 'float',
            'gap_vertical_mm' => 'float',
            'margin_top_mm' => 'float',
            'margin_left_mm' => 'float',
        ];
    }
}
