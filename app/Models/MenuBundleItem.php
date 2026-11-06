<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuBundleItem extends Model
{
    protected $fillable = ['bundle_menu_id', 'component_menu_id'];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'bundle_menu_id');
    }

    public function componentMenu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'component_menu_id');
    }
}
