<?php

namespace App\Mcp\Concerns;

use App\Models\Post;
use Illuminate\Support\Str;

trait InteractsWithPosts
{
    /**
     * Zet een door de client aangeleverde titel om naar een unieke slug.
     * Negeert de post met $ignoreId (handig bij updates van een bestaande post).
     */
    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base !== '' ? $base : 'post';
        $i = 2;

        while (
            Post::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Compacte, voor Claude leesbare samenvatting van een post na een actie,
     * inclusief de publieke URL zodat de wijziging meteen na te kijken is.
     *
     * @return array<string, mixed>
     */
    protected function summarize(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'published' => $post->published,
            'featured' => $post->featured,
            'published_at' => $post->published_at?->toDateTimeString(),
            'url' => $post->publicUrl(),
            'admin_url' => url("/admin/posts/{$post->id}/edit"),
        ];
    }
}
