<?php

namespace App\Mcp\Concerns;

use App\Models\CaseStudy;
use App\Rules\MediaUrl;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;

trait InteractsWithCases
{
    /**
     * Zet een aangeleverde titel om naar een unieke slug.
     * Negeert de case met $ignoreId (handig bij updates).
     */
    protected function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base !== '' ? $base : 'case';
        $i = 2;

        while (
            CaseStudy::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Compacte samenvatting na een actie, met de publieke URL zodat de wijziging
     * meteen na te kijken is.
     *
     * @return array<string, mixed>
     */
    protected function summarize(CaseStudy $case): array
    {
        return [
            'id' => $case->id,
            'title' => $case->title,
            'slug' => $case->slug,
            'client' => $case->client,
            'published' => $case->published,
            'featured' => $case->featured,
            'url' => $case->publicUrl(),
            'admin_url' => url("/admin/cases/{$case->id}/edit"),
        ];
    }

    /**
     * Het `content`-contract van een case, zoals de admin-form en de publieke
     * view het verwachten. Wordt zowel als MCP-inputschema als voor validatie
     * gebruikt, zodat beide niet uit elkaar kunnen lopen.
     *
     * @return array<string, array<int, string>>
     */
    protected function contentRules(string $prefix = 'content'): array
    {
        return [
            "{$prefix}" => ['required', 'array'],
            "{$prefix}.challenge.body" => ['required', 'string'],

            "{$prefix}.goals" => ['nullable', 'array', 'max:10'],
            "{$prefix}.goals.*.text" => ['required', 'string', 'max:200'],

            "{$prefix}.approach.steps" => ['nullable', 'array', 'max:10'],
            "{$prefix}.approach.steps.*.title" => ['required', 'string', 'max:100'],
            "{$prefix}.approach.steps.*.body" => ['required', 'string'],

            "{$prefix}.solution.body" => ['required', 'string'],
            // Media-velden krijgen dezelfde regel als cover_url: een volledige
            // http(s)-URL of een /storage-pad uit de eigen library.
            "{$prefix}.solution.image_url" => ['nullable', 'string', 'max:255', new MediaUrl],
            "{$prefix}.solution.image_alt" => ['nullable', 'string', 'max:255'],

            "{$prefix}.results.intro" => ['nullable', 'string'],
            "{$prefix}.results.metrics" => ['nullable', 'array', 'max:10'],
            "{$prefix}.results.metrics.*.label" => ['required', 'string', 'max:80'],
            "{$prefix}.results.metrics.*.value" => ['required', 'string', 'max:40'],

            "{$prefix}.testimonial.quote" => ['nullable', 'string'],
            "{$prefix}.testimonial.name" => ['nullable', 'string', 'max:100'],
            "{$prefix}.testimonial.role" => ['nullable', 'string', 'max:100'],
            "{$prefix}.testimonial.avatar_url" => ['nullable', 'string', 'max:255', new MediaUrl],

            "{$prefix}.reflection.body" => ['nullable', 'string'],
            "{$prefix}.reflection.website_url" => ['nullable', 'url', 'max:255'],

            "{$prefix}.cta.title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.cta.body" => ['nullable', 'string'],
            "{$prefix}.cta.button_label" => ['nullable', 'string', 'max:80'],
            "{$prefix}.cta.button_url" => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Hetzelfde contract als contentRules(), maar als MCP-inputschema.
     */
    protected function contentSchema(JsonSchema $schema): object
    {
        return $schema->object([
            'challenge' => $schema->object([
                'body' => $schema->string()
                    ->description('De situatie en het probleem van de klant vóór de samenwerking.')
                    ->required(),
            ])->required(),

            'goals' => $schema->array()
                ->description('De projectdoelen, elk als los item.')
                ->items($schema->object([
                    'text' => $schema->string()->description('Eén doel (max 200 tekens).')->required(),
                ])),

            'approach' => $schema->object([
                'steps' => $schema->array()
                    ->description('De gezette stappen, in volgorde.')
                    ->items($schema->object([
                        'title' => $schema->string()->description('Korte titel van de stap.')->required(),
                        'body' => $schema->string()->description('Wat hield deze stap in?')->required(),
                    ])),
            ]),

            'solution' => $schema->object([
                'body' => $schema->string()->description('Wat is er gebouwd of opgeleverd?')->required(),
                'image_url' => $schema->string()->description('Optionele screenshot/mockup. Gebruik upload_media_from_url en geef de teruggegeven /storage-URL door.'),
                'image_alt' => $schema->string()->description('Alt-tekst bij de afbeelding.'),
            ])->required(),

            'results' => $schema->object([
                'intro' => $schema->string()->description('Inleidende tekst bij de resultaten.'),
                'metrics' => $schema->array()
                    ->description('Concrete, meetbare resultaten.')
                    ->items($schema->object([
                        'label' => $schema->string()->description('Bv. "Online boekingen".')->required(),
                        'value' => $schema->string()->description('Bv. "+65%".')->required(),
                    ])),
            ]),

            'testimonial' => $schema->object([
                'quote' => $schema->string()->description('Quote van de klant. Verzin deze nooit — laat leeg als er geen echte getuigenis is.'),
                'name' => $schema->string()->description('Naam van de spreker.'),
                'role' => $schema->string()->description('Functie / titel van de spreker.'),
                'avatar_url' => $schema->string()->description('Profielfoto (media-URL uit de library).'),
            ]),

            'reflection' => $schema->object([
                'body' => $schema->string()->description('Waarom werkte deze aanpak? 2-3 zinnen.'),
                'website_url' => $schema->string()->description('Link naar het live project (volledige URL).'),
            ]),

            'cta' => $schema->object([
                'title' => $schema->string()->description('Titel van de afsluitende call-to-action.'),
                'body' => $schema->string()->description('Tekst van de call-to-action.'),
                'button_label' => $schema->string()->description('Knoptekst.'),
                'button_url' => $schema->string()->description('Knop-URL, bv. /contact.'),
            ]),
        ]);
    }
}
