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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Werk een bestaande case bij. Geef alleen de velden mee die je wil wijzigen. Let op: content vervangt het volledige content-blok, dus geef dat alleen mee als je de hele inhoud opnieuw aanlevert. Zoek het id eerst op met list_cases.')]
// Overschrijft bestaande inhoud => destructief.
#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
class UpdateCase extends Tool
{
    use InteractsWithCases;

    protected string $name = 'update_case';

    public function handle(Request $request): Response
    {
        $rules = [
            'id' => ['required', 'integer'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'client' => ['sometimes', 'nullable', 'string', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:400'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:8'],
            'tags.*' => ['string', 'max:50'],
            'cover_url' => ['sometimes', 'nullable', 'string', 'max:255', new MediaUrl],
            'cover_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'featured' => ['sometimes', 'boolean'],
        ];

        // content is bij een update optioneel; wordt het meegegeven, dan geldt
        // hetzelfde contract als bij create_case.
        if ($request->get('content') !== null) {
            $rules += $this->contentRules();
        }

        $validated = $request->validate($rules);

        $case = CaseStudy::find($validated['id']);

        if (! $case) {
            return Response::error("Geen case gevonden met id {$validated['id']}.");
        }

        foreach (['title', 'client', 'industry', 'excerpt', 'cover_url', 'cover_alt', 'meta_title', 'meta_description', 'featured'] as $field) {
            if (array_key_exists($field, $validated)) {
                $case->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('tags', $validated)) {
            $case->tags = $validated['tags'];
        }

        if (array_key_exists('content', $validated)) {
            $case->content = $validated['content'];
        }

        if (array_key_exists('slug', $validated)) {
            $case->slug = $this->uniqueSlug($validated['slug'], ignoreId: $case->id);
        }

        $case->save();

        return Response::json([
            'message' => "Case “{$case->title}” is bijgewerkt.",
            'case' => $this->summarize($case),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('Het id van de bij te werken case (via list_cases).')
                ->required(),
            'content' => $this->contentSchema($schema)
                ->description('Vervangt het VOLLEDIGE content-blok. Alleen meegeven als je de hele inhoud opnieuw aanlevert.'),
            'title' => $schema->string()->description('Nieuwe titel.'),
            'client' => $schema->string()->description('Nieuwe klantnaam.'),
            'industry' => $schema->string()->description('Nieuwe sector.'),
            'excerpt' => $schema->string()->description('Nieuwe teaser.')->max(400),
            'slug' => $schema->string()->description('Nieuwe URL-slug (wordt uniek gemaakt indien nodig).'),
            'tags' => $schema->array()->description('Vervangende lijst tags.')->items($schema->string()),
            'cover_url' => $schema->string()->description('Nieuwe coverafbeelding (media-URL uit de library).'),
            'cover_alt' => $schema->string()->description('Nieuwe alt-tekst voor de cover.'),
            'meta_title' => $schema->string()->description('Nieuwe SEO meta-titel.')->max(60),
            'meta_description' => $schema->string()->description('Nieuwe SEO meta-omschrijving.')->max(160),
            'featured' => $schema->boolean()->description('Uitgelicht aan/uit.'),
        ];
    }
}
