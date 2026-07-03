<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'location',
    'name',
    'title',
])]
class Menu extends Model
{
    /**
     * Top-level items (geen submenu-kinderen), op volgorde.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('position');
    }

    /**
     * Alle items (parents én children), op volgorde.
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('position');
    }
}
