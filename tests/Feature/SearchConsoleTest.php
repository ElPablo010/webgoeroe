<?php

use App\Enums\UserRole;
use App\Filament\Pages\SearchConsole;
use App\Models\GscDailyMetric;
use App\Models\GscDimensionMetric;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleSearchConsoleService;
use App\Services\GscCollector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Google Search Console (Groei-meetlaag): de OAuth-koppeling met
 * state-controle, de automatische property-keuze, de sync (rollend venster,
 * upsert per dag, backfill bij de eerste run) en de cijfers die het
 * Verkeer-scherm toont.
 */
function gscConnected(): void
{
    Setting::set('gsc_oauth_client_id', 'client-id');
    Setting::set('gsc_oauth_client_secret', 'client-secret');
    Setting::set('gsc_refresh_token', 'refresh-token');
    Setting::set('gsc_site_url', 'sc-domain:example.be');
}

/** @var array<string, array<int, array<string, mixed>>> */
$GLOBALS['gscFakeRows'] = [];

/**
 * Eén Http::fake voor de hele test: latere stubs voor dezelfde URL winnen
 * niet van eerdere, dus de rijen komen uit een variabele die per stap
 * aangepast wordt.
 */
function fakeGoogle(array $dailyRows, array $queryRows = [], array $pageRows = []): void
{
    $GLOBALS['gscFakeRows'] = ['date' => $dailyRows, 'query' => $queryRows, 'page' => $pageRows];

    if (! empty($GLOBALS['gscFakeInstalled'])) {
        return;
    }
    $GLOBALS['gscFakeInstalled'] = true;

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at', 'expires_in' => 3600]),
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:example.be'], ['siteUrl' => 'https://www.other.be/']]]),
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => function ($request) {
            $dimension = $request->data()['dimensions'][0] ?? 'date';

            return Http::response(['rows' => $GLOBALS['gscFakeRows'][$dimension] ?? []]);
        },
    ]);
}

beforeEach(fn () => $GLOBALS['gscFakeInstalled'] = false);

it('kiest de domein-property voor het eigen domein, en anders https/www', function () {
    $sites = ['http://example.be/', 'https://www.example.be/', 'sc-domain:other.be'];
    expect(GoogleSearchConsoleService::matchSiteForDomain($sites, 'www.example.be'))->toBe('https://www.example.be/')
        ->and(GoogleSearchConsoleService::matchSiteForDomain([...$sites, 'sc-domain:example.be'], 'https://example.be'))->toBe('sc-domain:example.be')
        ->and(GoogleSearchConsoleService::matchSiteForDomain($sites, 'nomatch.be'))->toBeNull();
});

it('leidt het eigen domein af uit APP_URL wanneer er geen doeldomein ingesteld is', function () {
    config(['app.url' => 'https://www.example.be']);
    expect(GoogleSearchConsoleService::defaultDomain())->toBe('example.be');

    Setting::set('seo_target_domain', 'ander.be');
    expect(GoogleSearchConsoleService::defaultDomain())->toBe('ander.be');
});

it('leest bij de eerste sync de historiek in en overschrijft daarna per dag', function () {
    gscConnected();
    fakeGoogle(
        [['keys' => ['2026-08-01'], 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 8.2],
         ['keys' => ['2026-08-02'], 'clicks' => 7, 'impressions' => 120, 'ctr' => 0.0583, 'position' => 7.9]],
        [['keys' => ['salsa antwerpen'], 'clicks' => 4, 'impressions' => 80, 'ctr' => 0.05, 'position' => 6.0]],
        [['keys' => ['https://example.be/lessen'], 'clicks' => 3, 'impressions' => 60, 'ctr' => 0.05, 'position' => 5.0]],
    );

    $first = app(GscCollector::class)->sync();
    expect($first)->toMatchArray(['days' => 2, 'queries' => 1, 'pages' => 1, 'backfilled' => true])
        ->and(GscDailyMetric::count())->toBe(2)
        ->and(GscDimensionMetric::queries()->sole()->value)->toBe('salsa antwerpen');

    // Google herziet recente dagen: dezelfde dag opnieuw → één rij, nieuwe cijfers.
    fakeGoogle([['keys' => ['2026-08-02'], 'clicks' => 9, 'impressions' => 130, 'ctr' => 0.069, 'position' => 7.5]]);
    $second = app(GscCollector::class)->sync();

    expect($second['backfilled'])->toBeFalse()
        ->and(GscDailyMetric::count())->toBe(2)
        ->and(GscDailyMetric::whereDate('date', '2026-08-02')->sole()->clicks)->toBe(9);
});

it('vat de laatste 28 dagen samen tegenover de 28 dagen ervoor, gewogen op vertoningen', function () {
    gscConnected();
    $end = Carbon::parse('2026-08-31');
    for ($i = 0; $i < 56; $i++) {
        $date = $end->copy()->subDays($i);
        $recent = $i < 28;
        GscDailyMetric::create([
            'site_url' => 'sc-domain:example.be', 'date' => $date->toDateString(),
            'clicks' => $recent ? 10 : 5, 'impressions' => $recent ? 100 : 100, 'ctr' => $recent ? 0.1 : 0.05, 'position' => $recent ? 6.0 : 9.0,
        ]);
    }

    $summary = app(GscCollector::class)->summary();

    expect($summary['current'])->toMatchArray(['clicks' => 280, 'impressions' => 2800, 'ctr' => 10.0, 'position' => 6.0])
        ->and($summary['delta'])->toMatchArray(['clicks' => 140, 'position' => 3.0])
        ->and($summary['has_comparison'])->toBeTrue()
        ->and($summary['period_end'])->toBe('2026-08-31');
});

describe('OAuth-flow', function () {
    beforeEach(fn () => actingAs(User::factory()->create(['role' => UserRole::Admin])));

    it('stuurt naar Google met offline-toegang, consent en een state in de sessie', function () {
        Setting::set('gsc_oauth_client_id', 'client-id');
        Setting::set('gsc_oauth_client_secret', 'client-secret');

        $response = get(route('seo.gsc.oauth.redirect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
            ->toContain('access_type=offline')
            ->toContain('prompt=consent')
            ->toContain('state=' . session('gsc_oauth_state'))
            ->toContain(urlencode(route('seo.gsc.oauth.callback')));
    });

    it('weigert een callback met een verkeerde state', function () {
        session(['gsc_oauth_state' => 'expected']);

        get(route('seo.gsc.oauth.callback', ['state' => 'wrong', 'code' => 'abc']))
            ->assertRedirect(SearchConsole::getUrl());

        expect(Setting::get('gsc_refresh_token'))->toBeEmpty();
    });

    it('wisselt de code in voor een refresh token en kiest de eigen property', function () {
        Setting::set('gsc_oauth_client_id', 'client-id');
        Setting::set('gsc_oauth_client_secret', 'client-secret');
        config(['app.url' => 'https://www.example.be']);
        session(['gsc_oauth_state' => 'expected']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['refresh_token' => 'new-refresh', 'access_token' => 'at1', 'expires_in' => 3600])
                ->push(['access_token' => 'at2', 'expires_in' => 3600]),
            'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'https://www.other.be/'], ['siteUrl' => 'sc-domain:example.be']]]),
        ]);

        get(route('seo.gsc.oauth.callback', ['state' => 'expected', 'code' => 'the-code']))
            ->assertRedirect(SearchConsole::getUrl());

        expect(Setting::get('gsc_refresh_token'))->toBe('new-refresh')
            ->and(Setting::get('gsc_site_url'))->toBe('sc-domain:example.be');
    });

    it('is enkel voor beheerders', function () {
        actingAs(User::factory()->create(['role' => UserRole::Staff]));

        get(route('seo.gsc.oauth.redirect'))->assertForbidden();
    });
});

describe('Verkeer-scherm', function () {
    beforeEach(fn () => actingAs(User::factory()->create(['role' => UserRole::Admin])));

    it('toont de koppel-instructies zolang er geen koppeling is', function () {
        get(SearchConsole::getUrl())
            ->assertOk()
            ->assertSee('Nog niet gekoppeld')
            ->assertSee(route('seo.gsc.oauth.callback'));
    });

    it('toont cijfers, zoektermen en kansen zodra er data is', function () {
        gscConnected();
        for ($i = 0; $i < 10; $i++) {
            GscDailyMetric::create(['site_url' => 'sc-domain:example.be', 'date' => Carbon::today()->subDays($i + 3)->toDateString(), 'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 7.0]);
        }
        $period = ['site_url' => 'sc-domain:example.be', 'period_start' => Carbon::today()->subDays(28)->toDateString(), 'period_end' => Carbon::today()->toDateString()];
        GscDimensionMetric::create([...$period, 'dimension' => 'query', 'value' => 'salsa antwerpen', 'value_hash' => md5('salsa antwerpen'), 'clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 6.3]);
        GscDimensionMetric::create([...$period, 'dimension' => 'page', 'value' => 'https://example.be/lessen', 'value_hash' => md5('https://example.be/lessen'), 'clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 6.3]);

        get(SearchConsole::getUrl())
            ->assertOk()
            ->assertSee('salsa antwerpen')
            ->assertSee('/lessen')
            ->assertSee('Kansen');
    });

    it('bewaart de instellingen', function () {
        Livewire::test(SearchConsole::class)
            ->fillForm(['gsc_oauth_client_id' => ' id ', 'gsc_oauth_client_secret' => 'secret', 'gsc_site_url' => 'sc-domain:example.be'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified();

        expect(Setting::get('gsc_oauth_client_id'))->toBe('id')
            ->and(Setting::get('gsc_site_url'))->toBe('sc-domain:example.be');
    });

    it('haalt de cijfers op via "Ververs nu"', function () {
        gscConnected();
        fakeGoogle([['keys' => ['2026-08-01'], 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 8.2]]);

        Livewire::test(SearchConsole::class)
            ->call('syncNow')
            ->assertNotified('Eerste 16 maanden ingelezen');

        expect(GscDailyMetric::count())->toBe(1);
    });
});
