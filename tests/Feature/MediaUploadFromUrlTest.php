<?php

use App\Enums\UserRole;
use App\Mcp\Servers\CmsServer;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\UploadMediaFromUrl;
use App\Models\Post;
use App\Models\User;
use App\Models\WebsiteMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

/**
 * Echte afbeeldingsbytes, zodat Intervention ze ook echt kan decoderen.
 * Het UploadedFile-object moet in een variabele blijven staan: valt de referentie
 * weg, dan ruimt PHP het temp-bestand op vóór we het uitlezen.
 */
function fakeImageBytes(int $width = 800, int $height = 600): string
{
    $file = UploadedFile::fake()->image('photo.jpg', $width, $height);

    return (string) file_get_contents($file->getRealPath());
}

it('downloads a remote image into the media library and returns its internal url', function () {
    // Publiek IP-literal: geen DNS nodig, dus de test blijft offline werken.
    Http::fake(['*' => Http::response(fakeImageBytes(), 200, ['Content-Type' => 'image/jpeg'])]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/photo.jpg'])
        ->assertOk()
        ->assertSee('/storage/website-media/');

    $media = WebsiteMedia::latest('id')->first();

    expect($media)->not->toBeNull()
        ->and($media->url)->toStartWith('/storage/website-media/')
        ->and($media->url)->toEndWith('.webp')
        ->and($media->fallback_url)->toEndWith('.jpg')
        ->and($media->width)->toBe(800)
        ->and($media->height)->toBe(600)
        ->and($media->original_filename)->toBe('photo.jpg');

    // Beide varianten staan echt op de disk.
    Storage::disk('public')->assertExists($media->path);
    Storage::disk('public')->assertExists($media->fallback_path);
});

it('scales down an oversized image to the 2400px cap', function () {
    Http::fake(['*' => Http::response(fakeImageBytes(3000, 1500), 200, ['Content-Type' => 'image/jpeg'])]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/big.jpg'])
        ->assertOk();

    expect(WebsiteMedia::latest('id')->first()->width)->toBe(2400);
});

it('rejects urls pointing at loopback, private or metadata addresses', function (string $url) {
    Http::fake(['*' => Http::response(fakeImageBytes(), 200, ['Content-Type' => 'image/jpeg'])]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => $url])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/photo.jpg',
    'private range' => 'http://192.168.1.10/photo.jpg',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/photo.jpg',
]);

it('rejects a non-http scheme', function () {
    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'file:///etc/passwd'])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
});

it('rejects a url that does not serve an image', function () {
    Http::fake(['*' => Http::response('<html>geen afbeelding</html>', 200, ['Content-Type' => 'text/html'])]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/pagina.html'])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
});

/**
 * Een PNG die in z'n header enorme afmetingen claimt, zonder pixeldata.
 * Precies het "decompression bomb"-scenario: klein bestand, gigantisch uitgepakt.
 */
function fakePngHeaderClaiming(int $width, int $height): string
{
    $ihdr = 'IHDR'.pack('N2', $width, $height).pack('C5', 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
}

it('rejects an image whose dimensions would blow up the server memory', function () {
    // 20000x20000 = 400 MP; uitgepakt zou GD hier ~1,6 GB voor vragen.
    Http::fake(['*' => Http::response(
        fakePngHeaderClaiming(20000, 20000), 200, ['Content-Type' => 'image/png']
    )]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/bom.png'])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
});

it('rejects bytes that claim to be an image but are not decodable', function () {
    Http::fake(['*' => Http::response('dit zijn geen afbeeldingsbytes', 200, ['Content-Type' => 'image/jpeg'])]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/kapot.jpg'])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
});

it('surfaces an http error from the source', function () {
    Http::fake(['*' => Http::response('not found', 404)]);

    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/weg.jpg'])
        ->assertHasErrors();

    expect(WebsiteMedia::count())->toBe(0);
});

it('accepts a library media path as cover_url on a post', function () {
    // Dit is de hele reden van de MediaUrl-regel: /storage/... is geen absolute URL,
    // maar moet wel als cover geaccepteerd worden.
    CmsServer::actingAs($this->admin)
        ->tool(CreatePost::class, [
            'title' => 'Met cover',
            'body' => 'Inhoud.',
            'cover_url' => '/storage/website-media/01ABC.webp',
            'cover_alt' => 'Een testafbeelding',
        ])
        ->assertOk();

    expect(Post::firstWhere('title', 'Met cover')->cover_url)
        ->toBe('/storage/website-media/01ABC.webp');
});

it('still rejects a nonsense cover_url', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreatePost::class, [
            'title' => 'Kapotte cover',
            'body' => 'Inhoud.',
            'cover_url' => 'javascript:alert(1)',
        ])
        ->assertHasErrors();
});
