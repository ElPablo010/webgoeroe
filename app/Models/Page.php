<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'slug',
    'locale',
    'translation_of',
    'is_homepage',
    'published',
    'meta_title',
    'meta_description',
    'meta_robots',
    'canonical_url',
    'is_cornerstone',
    'seo_image_url',
    'seo_image_alt',
])]
class Page extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Page $page) {
            $page->sections()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'is_homepage' => 'boolean',
            'published' => 'boolean',
            'is_cornerstone' => 'boolean',
        ];
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(PageSection::class, 'sectionable')->orderBy('position');
    }

    public function publicUrl(): string
    {
        return route('page.show', $this->is_homepage ? [] : ['slug' => $this->slug]);
    }

    /**
     * Maak een volledige kopie van deze pagina, inclusief alle secties.
     *
     * De kopie is bewust nooit de homepage en nooit gepubliceerd: ze is een
     * werkversie tot iemand ze zelf live zet. Ze is ook geen vertaling van de
     * bron — een kopie staat op zichzelf.
     */
    public function duplicate(): self
    {
        return DB::transaction(function (): self {
            // Fallback op de kolom-default: `locale` is null zolang een net
            // aangemaakte pagina niet opnieuw uit de database geladen is.
            $locale = $this->locale ?? 'nl';

            $copy = $this->replicate();
            $copy->title = "{$this->title} (kopie)";
            $copy->locale = $locale;
            $copy->slug = static::uniqueSlug("{$this->slug}-kopie", $locale);
            $copy->is_homepage = false;
            $copy->published = false;
            $copy->translation_of = null;
            $copy->save();

            foreach ($this->sections()->get() as $section) {
                $sectionCopy = $section->replicate();
                $sectionCopy->sectionable_id = $copy->getKey();
                $sectionCopy->translation_of = null;
                $sectionCopy->save();
            }

            return $copy;
        });
    }

    /** Garandeer een unieke slug binnen de locale (unique index: locale + slug). */
    public static function uniqueSlug(string $source, string $locale = 'nl'): string
    {
        $base = Str::slug($source) ?: 'pagina';
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
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
