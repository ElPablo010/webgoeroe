<?php

use App\Models\Setting;
use App\Services\Google\GoogleApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * De gedeelde Google-basisklaag (Groei-meetlaag).
 *
 * Deze tests gebruiken bewust een verzonnen subklasse in plaats van Search
 * Console: ze moeten aantonen dat de laag écht losstaat van één API, zodat er
 * straks een tweede dienst (Analytics) op kan erven zonder het inlogwerk te
 * kopiëren. Het gedrag van Search Console zelf staat in SearchConsoleTest.
 */
class FakeGoogleClient extends GoogleApiClient
{
    public function __construct(protected string $prefix = 'demo')
    {
        parent::__construct();
    }

    protected function serviceAccountScope(): string
    {
        return 'https://www.googleapis.com/auth/demo.readonly';
    }

    protected function apiBase(): string
    {
        return 'https://demo.googleapis.com/v1';
    }

    protected function settingPrefix(): string
    {
        return $this->prefix;
    }

    protected function label(): string
    {
        return 'Demo';
    }

    /** Het beschermde request() openzetten, zodat de test de HTTP-laag kan uitlokken. */
    public function call(string $path): ?array
    {
        return $this->request('get', $path);
    }

    public function cacheKey(): string
    {
        return $this->tokenCacheKey();
    }
}

function demoConnected(string $prefix = 'demo'): void
{
    Setting::set($prefix.'_oauth_client_id', 'client-id');
    Setting::set($prefix.'_oauth_client_secret', 'client-secret');
    Setting::set($prefix.'_refresh_token', 'refresh-token');
}

it('leidt alle setting-sleutels af uit het voorvoegsel van de subklasse', function () {
    demoConnected();

    expect((new FakeGoogleClient)->hasOAuth())->toBeTrue();

    // Dezelfde waarden onder een ánder voorvoegsel tellen niet mee.
    expect((new FakeGoogleClient('anders'))->hasOAuth())->toBeFalse();
});

it('vraagt in één keer alle rechten van de gedeelde koppeling', function () {
    demoConnected();

    $url = (new FakeGoogleClient)->authorizationUrl('https://voorbeeld.be/callback', 'state-123');

    // Search Console én Analytics in dezelfde consent: anders zou de gebruiker
    // twee keer door dezelfde flow moeten.
    foreach (GoogleApiClient::CONSENT_SCOPES as $scope) {
        expect($url)->toContain(urlencode($scope));
    }

    expect($url)->toContain('access_type=offline')
        ->toContain('prompt=consent')
        ->toContain('state=state-123');
});

it('onthoudt welke rechten Google toekende', function () {
    Setting::set('demo_oauth_client_id', 'client-id');
    Setting::set('demo_oauth_client_secret', 'client-secret');

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response([
            'refresh_token' => 'nieuw-token',
            'scope' => implode(' ', GoogleApiClient::CONSENT_SCOPES),
        ]),
    ]);

    (new FakeGoogleClient)->exchangeCodeForRefreshToken('code', 'https://voorbeeld.be/callback');

    $client = new FakeGoogleClient;

    expect($client->hasGrantedScope(GoogleApiClient::CONSENT_SCOPES[1]))->toBeTrue();
});

it('gaat bij een koppeling van vóór de uitbreiding uit van enkel Search Console', function () {
    demoConnected();

    // Geen bewaarde scope-lijst: die installatie koppelde toen Analytics nog
    // niet meegevraagd werd, dus mogen we het recht niet veronderstellen.
    $client = new FakeGoogleClient;

    expect($client->hasGrantedScope(GoogleApiClient::CONSENT_SCOPES[0]))->toBeTrue()
        ->and($client->hasGrantedScope(GoogleApiClient::CONSENT_SCOPES[1]))->toBeFalse();
});

it('praat met de basis-URL van de subklasse en stuurt het access token mee', function () {
    demoConnected();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
        'demo.googleapis.com/*' => Http::response(['ok' => true]),
    ]);

    expect((new FakeGoogleClient)->call('/dingen'))->toBe(['ok' => true]);

    Http::assertSent(fn ($request) => $request->url() === 'https://demo.googleapis.com/v1/dingen'
        && $request->hasHeader('Authorization', 'Bearer at-1'));
});

it('geeft twee diensten een eigen tokencache, zodat ze elkaars token niet gebruiken', function () {
    demoConnected('een');
    demoConnected('twee');

    expect((new FakeGoogleClient('een'))->cacheKey())
        ->not->toBe((new FakeGoogleClient('twee'))->cacheKey());
});

it('verbreekt enkel de koppeling van de eigen dienst', function () {
    demoConnected('een');
    demoConnected('twee');

    (new FakeGoogleClient('een'))->disconnect();

    expect(Setting::get('een_refresh_token'))->toBeEmpty()
        ->and(Setting::get('twee_refresh_token'))->toBe('refresh-token');
});

it('vergeet het token wanneer Google de toestemming niet meer erkent', function () {
    demoConnected();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect((new FakeGoogleClient)->call('/dingen'))->toBeNull()
        ->and(Setting::get('demo_refresh_token'))->toBeEmpty();
});

it('bewaart het refresh token onder het eigen voorvoegsel na de consent-flow', function () {
    Setting::set('demo_oauth_client_id', 'client-id');
    Setting::set('demo_oauth_client_secret', 'client-secret');

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['refresh_token' => 'nieuw-token']),
    ]);

    $result = (new FakeGoogleClient)->exchangeCodeForRefreshToken('code', 'https://voorbeeld.be/callback');

    expect($result['ok'])->toBeTrue()
        ->and(Setting::get('demo_refresh_token'))->toBe('nieuw-token');
});

it('doet geen enkele call zonder inloggegevens', function () {
    Http::fake();

    expect((new FakeGoogleClient)->call('/dingen'))->toBeNull();

    Http::assertNothingSent();
});

afterEach(fn () => Cache::flush());
