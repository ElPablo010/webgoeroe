<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title', 'slug', 'excerpt', 'body', 'cover_url', 'cover_alt', 'tags',
    'author_name', 'author_bio', 'author_avatar_url',
    'published', 'featured', 'published_at',
    'meta_title', 'meta_description', 'meta_robots', 'canonical_url',
    'is_cornerstone', 'seo_image_url', 'seo_image_alt',
])]
class Post extends Model
{
    protected function casts(): array
    {
        return [
            'published'      => 'boolean',
            'featured'       => 'boolean',
            'is_cornerstone' => 'boolean',
            'tags'           => 'array',
            'published_at'   => 'datetime',
        ];
    }

    /**
     * Media hoort relatief in de database te staan — zie MediaPath.
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => MediaPath::relative($value));
    }

    protected function authorAvatarUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => MediaPath::relative($value));
    }

    protected function seoImageUrl(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => MediaPath::relative($value));
    }

    public function publicUrl(): string
    {
        return route('blog.show', ['slug' => $this->slug]);
    }

    public function readingTimeMinutes(): int
    {
        $wordCount = str_word_count(strip_tags($this->body ?? ''));

        return max(1, (int) round($wordCount / 250));
    }
}
