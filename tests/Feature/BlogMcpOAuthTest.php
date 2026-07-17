<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('exposes the OAuth protected-resource metadata (RFC 9728)', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported'])
        ->assertJsonPath('scopes_supported', ['mcp:use']);
});

it('exposes the OAuth authorization-server metadata (RFC 8414)', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
        ->assertJsonStructure(['authorization_endpoint', 'token_endpoint', 'registration_endpoint']);
});

it('lets claude.ai register itself as an OAuth client (RFC 7591)', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])
        ->assertCreated()
        ->assertJsonPath('redirect_uris', ['https://claude.ai/api/mcp/auth_callback'])
        ->assertJsonPath('token_endpoint_auth_method', 'none') // public client + PKCE
        ->assertJsonStructure(['client_id']);
});

it('rejects client registration from a non-allowlisted redirect domain', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Kwaadaardig',
        'redirect_uris' => ['https://evil.example.com/callback'],
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

it('shows the branded consent screen at /oauth/authorize', function () {
    // 1. claude.ai registreert zich dynamisch.
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->json('client_id');

    // 2. Ingelogde gebruiker (web-guard) wordt door claude.ai naar authorize gestuurd.
    $this->actingAs($this->admin)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'test-state',
            'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Toegang verlenen')
        ->assertSee($this->admin->email);
});

it('authenticates on /mcp through the Passport api guard', function () {
    Passport::actingAs($this->admin, ['mcp:use']);

    $response = $this->withHeaders(['Accept' => 'application/json, text/event-stream'])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'pest', 'version' => '1'],
            ],
        ]);

    // De api-guard accepteert de gebruiker => route is niet langer 401.
    expect($response->getStatusCode())->not->toBe(401);
    $response->assertSee('Webgoeroe CMS');
});
