<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithPosts;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Maak een nieuw blogartikel op dewebgoeroe.be. De artikeltekst geef je als Markdown. Zet published op true om het meteen live te zetten; anders blijft het een concept in de admin.')]
class CreatePost extends Tool
{
    use InteractsWithPosts;

    protected string $name = 'create_post';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'slug' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:50'],
            'cover_url' => ['nullable', 'url', 'max:255'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
        ]);

        $published = (bool) ($validated['published'] ?? false);

        $post = new Post([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['title']),
            'body' => Str::markdown($validated['body']),
            'excerpt' => $validated['excerpt'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'cover_url' => $validated['cover_url'] ?? null,
            'cover_alt' => $validated['cover_alt'] ?? null,
            'author_name' => $validated['author_name'] ?? 'De Webgoeroe',
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'featured' => (bool) ($validated['featured'] ?? false),
            'published' => $published,
            'published_at' => $published ? now() : null,
        ]);

        $post->save();

        $state = $published ? 'gepubliceerd en live' : 'aangemaakt als concept';

        return Response::json([
            'message' => "Blogartikel “{$post->title}” is {$state}.",
            'post' => $this->summarize($post),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('De titel van het artikel.')
                ->required(),
            'body' => $schema->string()
                ->description('De volledige artikeltekst in Markdown. Gebruik ## voor tussenkopjes (voeden de inhoudsopgave) en ### voor subkopjes.')
                ->required(),
            'excerpt' => $schema->string()
                ->description('Korte teaser (1-2 zinnen, max 400 tekens) voor het overzicht en als SEO-fallback.')
                ->max(400),
            'slug' => $schema->string()
                ->description('Optioneel. URL-slug; standaard afgeleid van de titel. Wordt uniek gemaakt indien nodig.'),
            'tags' => $schema->array()
                ->description('1-4 relevante tags. Alfabetisch ordenen.')
                ->items($schema->string()),
            'cover_url' => $schema->string()
                ->description('Optionele URL van de coverafbeelding bovenaan het artikel.'),
            'cover_alt' => $schema->string()
                ->description('Alt-tekst voor de coverafbeelding.'),
            'author_name' => $schema->string()
                ->description('Naam van de auteur. Standaard “De Webgoeroe”.'),
            'meta_title' => $schema->string()
                ->description('SEO meta-titel (~60 tekens). Standaard de titel.')
                ->max(60),
            'meta_description' => $schema->string()
                ->description('SEO meta-omschrijving (~160 tekens). Standaard de excerpt.')
                ->max(160),
            'featured' => $schema->boolean()
                ->description('Uitgelicht bovenaan het overzicht plaatsen. Standaard false.'),
            'published' => $schema->boolean()
                ->description('true = meteen live publiceren; false = concept in de admin. Standaard false.'),
        ];
    }
}
