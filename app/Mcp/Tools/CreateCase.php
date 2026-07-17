<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithCases;
use App\Models\CaseStudy;
use App\Rules\MediaUrl;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Maak een nieuwe case (klantproject) aan. Een case volgt een vast stramien: uitdaging, doelen, aanpak, oplossing, resultaat, getuigenis, reflectie en een call-to-action. Zet published op true om meteen live te gaan; anders blijft het een concept.')]
// Voegt toe, overschrijft niets — maar kan wel meteen publiceren.
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateCase extends Tool
{
    use InteractsWithCases;

    protected string $name = 'create_case';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'tags' => ['nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:50'],
            'cover_url' => ['nullable', 'string', 'max:255', new MediaUrl],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'featured' => ['nullable', 'boolean'],
            'published' => ['nullable', 'boolean'],
            ...$this->contentRules(),
        ]);

        $published = (bool) ($validated['published'] ?? false);

        $case = new CaseStudy([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['title']),
            'client' => $validated['client'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'cover_url' => $validated['cover_url'] ?? null,
            'cover_alt' => $validated['cover_alt'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'content' => $validated['content'],
            'featured' => (bool) ($validated['featured'] ?? false),
            'published' => $published,
        ]);

        $case->save();

        $state = $published ? 'gepubliceerd en live' : 'aangemaakt als concept';

        return Response::json([
            'message' => "Case “{$case->title}” is {$state}.",
            'case' => $this->summarize($case),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('De titel van de case, bv. "Webshop op maat voor dartsspeciaalzaak".')
                ->required(),
            'content' => $this->contentSchema($schema)
                ->description('De inhoud van de case volgens het vaste stramien.')
                ->required(),
            'client' => $schema->string()->description('Naam van de klant.'),
            'industry' => $schema->string()->description('Sector van de klant, bv. "Horeca".'),
            'excerpt' => $schema->string()->description('Korte teaser (1-2 zinnen) voor het overzicht en als SEO-fallback.')->max(400),
            'slug' => $schema->string()->description('Optioneel. URL-slug; standaard afgeleid van de titel.'),
            'tags' => $schema->array()->description('1-4 relevante tags, alfabetisch.')->items($schema->string()),
            'cover_url' => $schema->string()->description('Coverafbeelding. Gebruik de url die upload_media_from_url teruggaf, niet een externe link.'),
            'cover_alt' => $schema->string()->description('Alt-tekst voor de cover.'),
            'meta_title' => $schema->string()->description('SEO meta-titel (~60 tekens).')->max(60),
            'meta_description' => $schema->string()->description('SEO meta-omschrijving (~160 tekens).')->max(160),
            'featured' => $schema->boolean()->description('Uitgelicht bovenaan het overzicht. Standaard false.'),
            'published' => $schema->boolean()->description('true = meteen live; false = concept. Standaard false.'),
        ];
    }
}
