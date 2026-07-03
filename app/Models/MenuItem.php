<?php

namespace App\Models;

use App\Support\Url;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'menu_id',
    'parent_id',
    'label',
    'page_id',
    'url',
    'position',
    'target_blank',
])]
class MenuItem extends Model
{
    protected function casts(): array
    {
        return [
            'target_blank' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * De uiteindelijke href: een gekoppelde pagina wint van een losse URL.
     * Pagina → root-relatief pad (homepage = "/"); anders de genormaliseerde
     * losse URL (zelfde regel als PageLinkField en Url::normalize).
     */
    public function resolvedHref(): string
    {
        if ($this->page !== null) {
            return $this->page->is_homepage ? '/' : '/'.$this->page->slug;
        }

        return Url::normalize($this->url) ?? '#';
    }
}
