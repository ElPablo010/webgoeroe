<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
            'content'       => 'array',
        ];
    }

    public function publicUrl(): string
    {
        return route('case-studies.show', ['slug' => $this->slug]);
    }
}
