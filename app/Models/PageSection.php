<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'sectionable_type',
    'sectionable_id',
    'section_type',
    'position',
    'content',
    'locale',
    'translation_of',
])]
class PageSection extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'position' => 'integer',
        ];
    }

    public function sectionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceTranslation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'translation_of');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'translation_of');
    }
}
