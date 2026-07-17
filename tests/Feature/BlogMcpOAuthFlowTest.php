<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

/**
 * Loopt de volledige OAuth 2.1 authorization-code + PKCE flow door, precies zoals
 * de claude.ai-connector dat doet, en gebruikt het resulterende access-token op /mcp.
 *
 * Dit dekt het gat dat Passport::actingAs() laat: die faket de guard, waardoor een
 * écht token nooit door de guard-lijst 'sanctum,api' gehaald werd.
 */
it('completes the real OAuth flow and calls tools/list with the issued token', function () {
    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';

    // 1. Dynamische client-registratie (claude.ai registreert zichzelf).
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [$redirectUri],
    ])->assertCreated()->json('client_id');

    // 2. PKCE-paar.
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    // 3. Gebruiker landt op het consentscherm.
    $authorize = $this->actingAs($this->admin)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));
    $authorize->assertOk()->assertSee('Toegang verlenen');

    // 4. Gebruiker klikt "Toegang verlenen" -> redirect met ?code=...
    $authToken = $authorize->viewData('authToken');

    $approve = $this->actingAs($this->admin)->post('/oauth/authorize', [
        'state' => 'state-123',
        'client_id' => $clientId,
        'auth_token' => $authToken,
    ]);

    $approve->assertRedirect();
    parse_str((string) parse_url($approve->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['code'] ?? null)->not->toBeNull('Geen authorization code ontvangen');

    // 5. Code inwisselen voor een access-token (public client + PKCE, geen secret).
    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json('access_token');

    expect($token)->not->toBeNull();

    // 6. Het ECHTE token gebruiken op /mcp -- dit is wat claude.ai doet.
    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json, text/event-stream',
    ];

    $this->withHeaders($headers)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'claude-ai', 'version' => '1'],
        ],
    ])->assertOk();

    $tools = $this->withHeaders($headers)->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
    ]);

    $tools->assertOk();

    $names = collect($tools->json('result.tools') ?? [])->pluck('name')->all();

    expect($names)->toContain('list_posts')
        ->and($names)->toContain('create_post')
        ->and($names)->toContain('upload_media_from_url');
});
