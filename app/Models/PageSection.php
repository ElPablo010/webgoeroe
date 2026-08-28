<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * RichEditor bewaart een leeggemaakt veld als "<p></p>" — voor de views is
     * dat inhoud, dus renderen ze een leeg blok mét marges. Normaliseer zulke
     * visueel lege HTML-strings naar null bij het uitlezen, zodat elke sectie
     * met een simpele empty()-check kan blijven werken.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?array {
                $content = $value === null ? null : json_decode($value, true);
                if (! is_array($content)) {
                    return $content;
                }

                foreach ($content as $key => $item) {
                    if (
                        is_string($item)
                        && str_contains($item, '<')
                        && trim(strip_tags($item)) === ''
                        && preg_match('/<(img|iframe|svg|video|hr)\b/i', $item) !== 1
                    ) {
                        $content[$key] = null;
                    }
                }

                return $content;
            },
        )->shouldCache();
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
