<?php

use App\Enums\UserRole;
use App\Mcp\Servers\BlogServer;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\PublishPost;
use App\Mcp\Tools\UnpublishPost;
use App\Mcp\Tools\UpdatePost;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('creates a draft post from markdown by default', function () {
    BlogServer::actingAs($this->admin)
        ->tool(CreatePost::class, [
            'title' => 'Waarom snelheid telt',
            'body' => "## Inleiding\n\nEen **snelle** site converteert beter.",
        ])
        ->assertOk();

    $post = Post::firstWhere('title', 'Waarom snelheid telt');

    expect($post)->not->toBeNull()
        ->and($post->published)->toBeFalse()
        ->and($post->published_at)->toBeNull()
        ->and($post->slug)->toBe('waarom-snelheid-telt')
        ->and($post->body)->toContain('<h2>')
        ->and($post->body)->toContain('<strong>snelle</strong>')
        ->and($post->author_name)->toBe('De Webgoeroe');
});

it('publishes immediately when published is true and returns the public url', function () {
    BlogServer::actingAs($this->admin)
        ->tool(CreatePost::class, [
            'title' => 'Live artikel',
            'body' => 'Inhoud.',
            'published' => true,
            'tags' => ['seo', 'performance'],
        ])
        ->assertOk()
        ->assertSee('/blog/live-artikel');

    $post = Post::firstWhere('title', 'Live artikel');

    expect($post->published)->toBeTrue()
        ->and($post->published_at)->not->toBeNull()
        ->and($post->tags)->toBe(['seo', 'performance']);
});

it('generates a unique slug when the title collides', function () {
    Post::create(['title' => 'Dubbel', 'slug' => 'dubbel']);

    BlogServer::actingAs($this->admin)
        ->tool(CreatePost::class, ['title' => 'Dubbel', 'body' => 'x'])
        ->assertOk();

    expect(Post::where('slug', 'dubbel-2')->exists())->toBeTrue();
});

it('rejects a post without a title', function () {
    BlogServer::actingAs($this->admin)
        ->tool(CreatePost::class, ['body' => 'Geen titel.'])
        ->assertHasErrors();
});

it('updates only the provided fields', function () {
    $post = Post::create([
        'title' => 'Origineel',
        'slug' => 'origineel',
        'body' => '<p>oud</p>',
        'excerpt' => 'oude teaser',
    ]);

    BlogServer::actingAs($this->admin)
        ->tool(UpdatePost::class, [
            'id' => $post->id,
            'title' => 'Bijgewerkt',
        ])
        ->assertOk();

    $post->refresh();

    expect($post->title)->toBe('Bijgewerkt')
        ->and($post->excerpt)->toBe('oude teaser')
        ->and($post->body)->toBe('<p>oud</p>');
});

it('publishes and unpublishes an existing post', function () {
    $post = Post::create(['title' => 'Schakel', 'slug' => 'schakel', 'published' => false]);

    BlogServer::actingAs($this->admin)->tool(PublishPost::class, ['id' => $post->id])->assertOk();
    expect($post->fresh()->published)->toBeTrue()
        ->and($post->fresh()->published_at)->not->toBeNull();

    BlogServer::actingAs($this->admin)->tool(UnpublishPost::class, ['id' => $post->id])->assertOk();
    expect($post->fresh()->published)->toBeFalse();
});

it('returns an error for an unknown post id', function () {
    BlogServer::actingAs($this->admin)
        ->tool(PublishPost::class, ['id' => 99999])
        ->assertHasErrors();
});

it('lists posts filtered by status', function () {
    Post::create(['title' => 'Gepubliceerd', 'slug' => 'pub', 'published' => true, 'published_at' => now()]);
    Post::create(['title' => 'Concept', 'slug' => 'draft', 'published' => false]);

    BlogServer::actingAs($this->admin)
        ->tool(ListPosts::class, ['status' => 'draft'])
        ->assertOk()
        ->assertSee('Concept')
        ->assertDontSee('Gepubliceerd');
});

it('blocks unauthenticated requests to the mcp endpoint', function () {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

it('authenticates a real Sanctum bearer token through the http transport', function () {
    $token = $this->admin->createToken('test-client')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json, text/event-stream',
    ])->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'pest', 'version' => '1'],
        ],
    ]);

    // Geldig token => niet langer 401; de transport neemt de handshake aan.
    expect($response->getStatusCode())->not->toBe(401);
    $response->assertSee('Webgoeroe Blog');
});
