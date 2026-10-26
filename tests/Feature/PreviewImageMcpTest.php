<?php

use App\Mcp\Servers\CmsServer;
use App\Mcp\Tools\PreviewImage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Webgoeroe\Core\Enums\UserRole;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

function fakePng(int $w, int $h): string
{
    $im = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($im);

    return ob_get_clean();
}

it('returns a thumbnail and a large preview as data uris', function () {
    Http::fake(['d8j0ntlcm91z4.cloudfront.net/*' => Http::response(fakePng(1600, 900), 200, ['Content-Type' => 'image/png'])]);

    CmsServer::actingAs($this->admin)
        ->tool(PreviewImage::class, ['url' => 'https://d8j0ntlcm91z4.cloudfront.net/user_x/hf_test.png'])
        ->assertOk()
        ->assertSee('data:image/jpeg;base64,');
});

it('refuses hosts that are not on the allow list', function () {
    Http::fake();

    CmsServer::actingAs($this->admin)
        ->tool(PreviewImage::class, ['url' => 'https://example.com/foto.png'])
        ->assertHasErrors();

    Http::assertNothingSent();
});

it('refuses a response that is not an image', function () {
    Http::fake(['images.pexels.com/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

    CmsServer::actingAs($this->admin)
        ->tool(PreviewImage::class, ['url' => 'https://images.pexels.com/photos/1/x.jpeg'])
        ->assertHasErrors();
});
