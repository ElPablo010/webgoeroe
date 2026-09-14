<?php

use App\Enums\UserRole;
use App\Filament\Pages\SearchConsole;
use App\Filament\Pages\SeoSettings;
use App\Models\Ga4DailyMetric;
use App\Models\Ga4DimensionMetric;
use App\Models\GscDailyMetric;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ga4Collector;
use App\Services\Google\GoogleApiClient;
use App\Services\GoogleAnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Google Analytics 4 (Groei-meetlaag): de sync met rollend venster, de
 * eigenaardigheden van de Data API (positionele rijen, datums zonder
 * streepjes, de "(other)"-emmer) en het tweede tabblad op de Verkeer-pagina.
 */
function gaConnected(): void
{
    Setting::set('google_oauth_client_id', 'client-id');
    Setting::set('google_oauth_client_secret', 'client-secret');
    Setting::set('google_refresh_token', 'refresh-token');
    Setting::set('google_oauth_scopes', implode(' ', GoogleApiClient::CONSENT_SCOPES));
    Setting::set('ga4_property_id', '123456789');
}

/** Eén rij zoals de Data API ze teruggeeft: positioneel, alles als string. */
function gaRow(array $dimensions, array $metrics): array
{
    return [
        'dimensionValues' => array_map(fn ($v) => ['value' => (string) $v], $dimensions),
        'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $metrics),
    ];
}

/**
 * Registreer de Data API één keer en geef een object terug waarin de test de
 * antwoorden kan wijzigen.
 *
 * Bewust niet Http::fake() per sync opnieuw aanroepen: Laravel vóégt stubs toe
 * in plaats van ze te vervangen, dus de eerste registratie blijft winnen en een
 * tweede sync zou de oude rijen terugkrijgen. En bewust dispatchen op de
 * opgevraagde dimensie in plaats van op volgnummer, zodat de test niet afhangt
 * van de volgorde waarin de collector zijn calls doet.
 */
function fakeGa(): object
{
    $state = new stdClass;
    $state->daily = [];
    $state->pages = [];
    $state->channels = [];
    $state->fail = false;

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'at-1', 'expires_in' => 3600]),
        'analyticsdata.googleapis.com/*' => function ($request) use ($state) {
            if ($state->fail) {
                return Http::response(['error' => 'boem'], 500);
            }

            $body = $request->body();

            $rows = match (true) {
                str_contains($body, 'pagePath') => $state->pages,
                str_contains($body, 'sessionDefaultChannelGroup') => $state->channels,
                default => $state->daily,
            };

            return Http::response(['rows' => $rows]);
        },
    ]);

    return $state;
}

it('leest dagcijfers in en zet de datum zonder streepjes correct om', function () {
    gaConnected();

    // GA4 geeft "20260910", Search Console zou "2026-09-10" geven.
    $ga = fakeGa();
    $ga->daily = [gaRow(['20260910'], [12, 9, 25, 7, 95.5])];

    $result = app(Ga4Collector::class)->sync();

    expect($result['days'])->toBe(1)
        ->and($result['backfilled'])->toBeTrue();

    $row = Ga4DailyMetric::where('property_id', '123456789')->first();

    expect($row->date->toDateString())->toBe('2026-09-10')
        ->and($row->sessions)->toBe(12)
        ->and($row->active_users)->toBe(9)
        ->and($row->page_views)->toBe(25)
        ->and($row->engaged_sessions)->toBe(7)
        ->and($row->avg_session_seconds)->toBe(95.5);
});

it('overschrijft een dag bij een tweede sync in plaats van te verdubbelen', function () {
    gaConnected();

    $ga = fakeGa();
    $ga->daily = [gaRow(['20260910'], [12, 9, 25, 7, 95.5])];
    app(Ga4Collector::class)->sync();

    // Google herziet recente dagen; de tweede sync moet overschrijven.
    $ga->daily = [gaRow(['20260910'], [30, 22, 61, 18, 120.0])];
    app(Ga4Collector::class)->sync();

    expect(Ga4DailyMetric::where('property_id', '123456789')->count())->toBe(1)
        ->and(Ga4DailyMetric::first()->sessions)->toBe(30);
});

it('bewaart pagina\'s en kanalen, maar niet de "(other)"-emmer', function () {
    gaConnected();

    $ga = fakeGa();
    $ga->daily = [gaRow(['20260910'], [12, 9, 25, 7, 95.5])];
    $ga->pages = [
        gaRow(['/diensten'], [8, 14, 6, 88.0]),
        // GA4 bundelt de staart in één rij; dat is geen pagina.
        gaRow(['(other)'], [3, 4, 1, 12.0]),
    ];
    $ga->channels = [gaRow(['Organic Search'], [9, 18, 7, 91.0])];

    $result = app(Ga4Collector::class)->sync();

    expect($result['pages'])->toBe(1)
        ->and($result['channels'])->toBe(1)
        ->and(Ga4DimensionMetric::pages()->pluck('value')->all())->toBe(['/diensten'])
        ->and(Ga4DimensionMetric::channels()->pluck('value')->all())->toBe(['Organic Search']);
});

it('vergelijkt de laatste 28 dagen met de 28 dagen ervoor', function () {
    gaConnected();

    // Recente periode: 10 sessies per dag. Periode ervoor: 5 per dag.
    foreach (range(0, 27) as $i) {
        Ga4DailyMetric::create([
            'property_id' => '123456789',
            'date' => Carbon::today()->subDays($i)->toDateString(),
            'sessions' => 10, 'active_users' => 8, 'page_views' => 20,
            'engaged_sessions' => 6, 'avg_session_seconds' => 60,
        ]);
    }
    foreach (range(28, 55) as $i) {
        Ga4DailyMetric::create([
            'property_id' => '123456789',
            'date' => Carbon::today()->subDays($i)->toDateString(),
            'sessions' => 5, 'active_users' => 4, 'page_views' => 10,
            'engaged_sessions' => 2, 'avg_session_seconds' => 40,
        ]);
    }

    $summary = app(Ga4Collector::class)->summary();

    expect($summary['current']['sessions'])->toBe(280)
        ->and($summary['previous']['sessions'])->toBe(140)
        ->and($summary['delta']['sessions'])->toBe(140)
        ->and($summary['has_comparison'])->toBeTrue()
        // Betrokkenheid gewogen op sessies: 6 van de 10.
        ->and($summary['current']['engagement_rate'])->toBe(60.0);
});

it('laat de bestaande cijfers staan wanneer de API faalt', function () {
    gaConnected();

    $ga = fakeGa();
    $ga->daily = [gaRow(['20260910'], [12, 9, 25, 7, 95.5])];
    app(Ga4Collector::class)->sync();

    $ga->fail = true;

    $result = app(Ga4Collector::class)->sync();

    expect($result['days'])->toBe(0)
        ->and(Ga4DailyMetric::first()->sessions)->toBe(12);
});

it('doet niets zolang het Analytics-recht ontbreekt', function () {
    Setting::set('google_oauth_client_id', 'client-id');
    Setting::set('google_oauth_client_secret', 'client-secret');
    Setting::set('google_refresh_token', 'refresh-token');
    Setting::set('ga4_property_id', '123456789');
    // Geen bewaarde scopes: een koppeling van vóór de uitbreiding.

    $ga = app(GoogleAnalyticsService::class);

    expect($ga->isConfigured())->toBeFalse()
        ->and($ga->needsReconsent())->toBeTrue();
});

it('kiest de property waarvan de naam bij het eigen domein hoort', function () {
    $properties = [
        ['id' => '111', 'name' => 'Een andere klant', 'account' => 'Bureau'],
        ['id' => '222', 'name' => 'webgoeroe.be', 'account' => 'Bureau'],
    ];

    expect(GoogleAnalyticsService::matchPropertyForDomain($properties, 'webgoeroe.be')['id'])->toBe('222')
        // "De Webgoeroe" hoort ook bij webgoeroe.be: leestekens en suffix tellen niet mee.
        ->and(GoogleAnalyticsService::matchPropertyForDomain($properties, 'De Webgoeroe')['id'])->toBe('222')
        ->and(GoogleAnalyticsService::matchPropertyForDomain($properties, 'onbekend.be'))->toBeNull();
});

describe('het tabblad op de Verkeer-pagina', function () {
    beforeEach(function () {
        actingAs(User::factory()->create(['role' => UserRole::Admin]));

        // Search Console moet data hebben, anders toont de pagina enkel de
        // koppel-instructies en komen de tabs niet in beeld.
        Setting::set('gsc_site_url', 'sc-domain:example.be');
        gaConnected();

        GscDailyMetric::create([
            'site_url' => 'sc-domain:example.be',
            'date' => Carbon::today()->subDays(3)->toDateString(),
            'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 7.0,
        ]);
    });

    it('toont standaard het Google-tabblad, niet dat van Analytics', function () {
        get(SearchConsole::getUrl())
            ->assertOk()
            ->assertSee('Uit Google Zoeken')
            ->assertSee('Op de site')
            ->assertSee('Kansen');
    });

    it('toont pagina\'s en kanalen na het wisselen van tabblad', function () {
        $period = [
            'property_id' => '123456789',
            'period_start' => Carbon::today()->subDays(28)->toDateString(),
            'period_end' => Carbon::today()->toDateString(),
        ];

        Ga4DailyMetric::create([
            'property_id' => '123456789',
            'date' => Carbon::today()->subDay()->toDateString(),
            'sessions' => 10, 'active_users' => 8, 'page_views' => 20,
            'engaged_sessions' => 6, 'avg_session_seconds' => 75,
        ]);
        Ga4DimensionMetric::create([...$period, 'dimension' => 'page', 'value' => '/diensten', 'value_hash' => md5('/diensten'), 'sessions' => 8, 'page_views' => 14, 'engaged_sessions' => 6, 'avg_session_seconds' => 88]);
        Ga4DimensionMetric::create([...$period, 'dimension' => 'channel', 'value' => 'Organic Social', 'value_hash' => md5('Organic Social'), 'sessions' => 4, 'page_views' => 6, 'engaged_sessions' => 2, 'avg_session_seconds' => 40]);

        Livewire::test(SearchConsole::class)
            ->assertSee('Kansen')
            ->call('setTab', 'site')
            ->assertSee('/diensten')
            ->assertSee('Organic Social')
            // De Google-tabellen zijn weg: enkel het actieve tabblad wordt opgehaald.
            ->assertDontSee('Kansen');
    });

    it('weigert een onbekend tabblad en valt terug op Google', function () {
        Livewire::test(SearchConsole::class)
            ->call('setTab', 'iets-anders')
            ->assertSet('tab', 'search');
    });
});

/** Gekoppeld voor Search Console, maar zonder het Analytics-recht. */
function searchConsoleOnly(): void
{
    Setting::set('google_oauth_client_id', 'client-id');
    Setting::set('google_oauth_client_secret', 'client-secret');
    Setting::set('google_refresh_token', 'refresh-token');
    Setting::set('gsc_site_url', 'sc-domain:example.be');

    GscDailyMetric::create([
        'site_url' => 'sc-domain:example.be',
        'date' => Carbon::today()->subDays(3)->toDateString(),
        'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 7.0,
    ]);
}

it('biedt op de SEO-instellingen opnieuw koppelen aan, zonder eerst te verbreken', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));
    searchConsoleOnly();

    get(SeoSettings::getUrl())
        ->assertOk()
        ->assertSee('Analytics mee koppelen')
        // Geen "Verbinden met Google": dat suggereert dat je eerst moet verbreken.
        ->assertDontSee('Verbinden met Google');
});

it('meldt op het cijferscherm dat Analytics ontbreekt en wijst naar de instellingen', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));
    searchConsoleOnly();

    get(SearchConsole::getUrl())
        ->assertOk()
        ->assertSee('Analytics hangt er nog niet aan')
        ->assertSee(SeoSettings::getUrl());
});

it('verbergt die knop weer zodra beide rechten binnen zijn', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));

    gaConnected();
    Setting::set('gsc_site_url', 'sc-domain:example.be');

    GscDailyMetric::create([
        'site_url' => 'sc-domain:example.be',
        'date' => Carbon::today()->subDays(3)->toDateString(),
        'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 7.0,
    ]);

    get(SearchConsole::getUrl())
        ->assertOk()
        ->assertDontSee('Analytics mee koppelen')
        ->assertDontSee('Analytics hangt er nog niet aan');
});
