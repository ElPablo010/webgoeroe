<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithPosts;
use App\Models\Post;
use App\Rules\MediaUrl;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Werk een bestaand blogartikel bij. Geef alleen de velden mee die je wil wijzigen; de rest blijft ongemoeid. Zoek het id eerst op met list_posts.')]
class UpdatePost extends Tool
{
    use InteractsWithPosts;

    protected string $name = 'update_post';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:400'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:50'],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:255', new MediaUrl],
            'cover_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'featured' => ['sometimes', 'boolean'],
        ]);

        $post = Post::find($validated['id']);

        if (! $post) {
            return Response::error("Geen blogartikel gevonden met id {$validated['id']}.");
        }

        foreach (['title', 'excerpt', 'cover_url', 'cover_alt', 'meta_title', 'meta_description', 'featured'] as $field) {
            if (array_key_exists($field, $validated)) {
                $post->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('tags', $validated)) {
            $post->tags = $validated['tags'];
        }

        if (array_key_exists('body', $validated)) {
            $post->body = Str::markdown($validated['body']);
        }

        if (array_key_exists('slug', $validated)) {
            $post->slug = $this->uniqueSlug($validated['slug'], ignoreId: $post->id);
        }

        $post->save();

        return Response::json([
            'message' => "Blogartikel “{$post->title}” is bijgewerkt.",
            'post' => $this->summarize($post),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van het bij te werken artikel (via list_posts).')
                ->required(),
            'title' => $schema->string()->description('Nieuwe titel.'),
            'body' => $schema->string()->description('Nieuwe artikeltekst in Markdown (vervangt de volledige inhoud).'),
            'excerpt' => $schema->string()->description('Nieuwe teaser.')->max(400),
            'slug' => $schema->string()->description('Nieuwe URL-slug (wordt uniek gemaakt indien nodig).'),
            'tags' => $schema->array()->description('Vervangende lijst tags.')->items($schema->string()),
            'cover_url' => $schema->string()->description('Nieuwe cover-afbeelding-URL.'),
            'cover_alt' => $schema->string()->description('Nieuwe alt-tekst voor de cover.'),
            'meta_title' => $schema->string()->description('Nieuwe SEO meta-titel.')->max(60),
            'meta_description' => $schema->string()->description('Nieuwe SEO meta-omschrijving.')->max(160),
            'featured' => $schema->boolean()->description('Uitgelicht aan/uit.'),
        ];
    }
}
