<?php

namespace App\Mcp\Tools;

use App\Services\Website\WebsiteMediaService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Description('Download een afbeelding van een publieke URL en zet ze in de medialibrary van de site. Geeft de interne media-URL terug, die je daarna als cover_url in create_post/update_post kunt gebruiken. Gebruik dit altijd in plaats van rechtstreeks naar een externe afbeelding te linken.')]
// Schrijft (nieuwe media), maakt niets stuk, maar haalt wél een externe URL op.
// Die open-world-hint is precies waarom een client hier terecht om bevestiging vraagt.
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld]
class UploadMediaFromUrl extends Tool
{
    protected string $name = 'upload_media_from_url';

    public function handle(Request $request, WebsiteMediaService $media): Response
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $stored = $media->storeFromUrl($validated['url'], $validated['filename'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'message' => 'Afbeelding is gedownload en toegevoegd aan de medialibrary.',
            'media' => [
                'id' => $stored->id,
                'url' => $stored->url,
                'fallback_url' => $stored->fallback_url,
                'width' => $stored->width,
                'height' => $stored->height,
                'size_bytes' => $stored->size_bytes,
                'hint' => 'Gebruik de waarde van "url" als cover_url bij create_post of update_post.',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('Publieke http(s)-URL van de afbeelding. Moet rechtstreeks naar het afbeeldingsbestand wijzen (niet naar een pagina die de afbeelding toont). Max 15 MB.')
                ->required(),
            'filename' => $schema->string()
                ->description('Optionele oorspronkelijke bestandsnaam voor in de library; standaard afgeleid van de URL.'),
        ];
    }
}
