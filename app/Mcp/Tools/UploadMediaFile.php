<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;
use Webgoeroe\Core\Services\WebsiteMediaService;

#[Description('Zet een afbeelding die je als base64 meegeeft in de medialibrary van de site (bv. een eigen foto die niet online staat). Geeft de interne media-URL terug, net als upload_media_from_url.')]
// Schrijft (nieuwe media), maakt niets stuk en haalt niets extern op.
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class UploadMediaFile extends Tool
{
    protected string $name = 'upload_media_file';

    /** Zelfde byte-cap als de URL-upload (15 MB). */
    private const MAX_BYTES = 15 * 1024 * 1024;

    public function handle(Request $request, WebsiteMediaService $media): Response
    {
        $validated = $request->validate([
            'data' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
        ]);

        // Een data-URI ("data:image/jpeg;base64,...") mag ook.
        $data = preg_replace('/^data:[^,]*,/', '', $validated['data']);
        $bytes = base64_decode((string) $data, true);

        if ($bytes === false || $bytes === '') {
            return Response::error('De afbeelding is geen geldige base64.');
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            return Response::error('De afbeelding is groter dan 15 MB.');
        }

        $path = tempnam(sys_get_temp_dir(), 'mcp-upload-');
        file_put_contents($path, $bytes);

        try {
            $stored = $media->store(new UploadedFile($path, basename($validated['filename']), null, null, true));
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        } finally {
            @unlink($path);
        }

        return Response::json([
            'message' => 'Afbeelding is toegevoegd aan de medialibrary.',
            'media' => [
                'id' => $stored->id,
                'url' => $stored->url,
                'fallback_url' => $stored->fallback_url,
                'width' => $stored->width,
                'height' => $stored->height,
                'size_bytes' => $stored->size_bytes,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data' => $schema->string()
                ->description('De afbeelding als base64 (of als data-URI). Max 15 MB.')
                ->required(),
            'filename' => $schema->string()
                ->description('Oorspronkelijke bestandsnaam, bv. foto.jpg.')
                ->required(),
        ];
    }
}
