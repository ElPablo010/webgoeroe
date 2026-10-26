<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Haal een afbeelding van een toegelaten bron op (Higgsfield, Pexels, onze eigen site) en geef een verkleinde versie terug als data-URI: een thumbnail (480 px breed) en een grote versie (1200 px). Slaat niets op. Bedoeld voor voorbeelden in het Contentoverzicht-artifact, dat zelf geen externe beelden mag laden.')]
// Leest enkel: niets wordt opgeslagen. Wel open-world, want haalt een externe URL op;
// daarom een vaste lijst toegelaten hosts, zodat dit geen open proxy wordt.
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PreviewImage extends Tool
{
    protected string $name = 'preview_image';

    private const ALLOWED_HOSTS = [
        'd8j0ntlcm91z4.cloudfront.net', // Higgsfield
        'images.pexels.com',
        'dewebgoeroe.be',
    ];

    private const MAX_BYTES = 15 * 1024 * 1024;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048', 'starts_with:https://'],
        ]);

        $host = strtolower((string) parse_url($validated['url'], PHP_URL_HOST));
        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            return Response::error('Deze bron is niet toegelaten voor een voorbeeld.');
        }

        try {
            $download = Http::timeout(15)->withoutRedirecting()->get($validated['url']);
        } catch (ConnectionException) {
            return Response::error('De afbeelding kon niet opgehaald worden.');
        }

        if (! $download->successful() || ! str_starts_with((string) $download->header('Content-Type'), 'image/')) {
            return Response::error('De URL wijst niet naar een afbeelding.');
        }

        $bytes = $download->body();
        if (strlen($bytes) > self::MAX_BYTES) {
            return Response::error('De afbeelding is te groot (max 15 MB).');
        }

        // Zelfde pixelgrens als de medialibrary: een klein bestand kan enorme afmetingen hebben.
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] * $size[1] > (int) config('core.media.max_pixels', 12_000_000)) {
            return Response::error('Het bestand is geen geldige afbeelding of is te groot.');
        }

        try {
            $images = new ImageManager(new Driver);
            $thumb = $images->decodeBinary($bytes)->scaleDown(width: 480)->encode(new JpegEncoder(quality: 70));
            $large = $images->decodeBinary($bytes)->scaleDown(width: 1200)->encode(new JpegEncoder(quality: 80));
        } catch (\Throwable) {
            return Response::error('Het bestand is geen geldige afbeelding.');
        }

        return Response::json([
            'thumb' => (string) $thumb->toDataUri(),
            'large' => (string) $large->toDataUri(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('Publieke https-URL van de afbeelding (Higgsfield-cloudfront, images.pexels.com of dewebgoeroe.be).')
                ->required(),
        ];
    }
}
