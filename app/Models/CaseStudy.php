<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'client',
    'industry',
    'cover_url',
    'cover_alt',
    'tags',
    'excerpt',
    'content',
    'published',
    'featured',
    'meta_title',
    'meta_description',
    'meta_robots',
    'canonical_url',
    'is_cornerstone',
    'seo_image_url',
    'seo_image_alt',
])]
class CaseStudy extends Model
{
    protected function casts(): array
    {
        return [
            'published'     => 'boolean',
            'featured'      => 'boolean',
            'is_cornerstone' => 'boolean',
            'tags'          => 'array',
            // 'content' heeft een eigen Attribute (zie onder) die media
            // normaliseert; een 'array'-cast zou daarmee botsen.
        ];
    }

    /**
     * Media hoort relatief in de database te staan — zie MediaPath.
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => MediaPath::relative($value));
    }

    protected function seoImageUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => MediaPath::relative($value));
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?array => $value === null ? null : json_decode($value, true),
            set: fn (?array $value): ?string => $value === null
                ? null
                : json_encode(MediaPath::relativeInContent($value)),
        );
    }

    public function publicUrl(): string
    {
        return route('case-studies.show', ['slug' => $this->slug]);
    }
}
