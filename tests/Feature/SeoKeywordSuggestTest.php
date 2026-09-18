<?php

use App\Filament\Resources\SeoKeywords\Pages\ListSeoKeywords;
use App\Filament\Widgets\SeoKeywordSuggestions;
use App\Jobs\SuggestKeywordsJob;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Models\User;
use App\Services\SeoAdvisorService;
use App\Support\JobStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Keyword-onderzoek: voorstellen worden aangevinkt en toegevoegd, nooit
 * automatisch. Al opgevolgde keywords verdwijnen uit het blok, toegevoegde
 * kandidaten uit de bewaarde voorstellen, en de knop zet het onderzoek op de
 * queue (te traag voor een web-request).
 */
beforeEach(function () {
    actingAs(User::factory()->create());

    Setting::set(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING, json_encode([
        'generated_at' => '2026-09-01 08:00:00',
        'items' => [
            ['keyword' => 'website laten maken', 'search_volume' => 1900],
            ['keyword' => 'webdesign antwerpen', 'search_volume' => 320],
            ['keyword' => 'seo bureau', 'search_volume' => 210],
        ],
    ]));
});

it('verbergt keywords die al opgevolgd worden', function () {
    SeoKeyword::create(['keyword' => 'SEO Bureau', 'location_code' => 2056, 'language_code' => 'nl', 'is_active' => true]);

    $widget = Livewire::test(SeoKeywordSuggestions::class);

    expect(collect($widget->instance()->suggestions())->pluck('keyword')->all())
        ->toBe(['website laten maken', 'webdesign antwerpen']);
});

it('voegt aangevinkte voorstellen toe aan de opvolging en haalt ze uit de voorstellen', function () {
    Livewire::test(SeoKeywordSuggestions::class)
        ->set('selected', ['webdesign antwerpen'])
        ->call('addSelected')
        ->assertNotified()
        ->assertDispatched('seo-keywords-added');

    expect(SeoKeyword::where('keyword', 'webdesign antwerpen')->exists())->toBeTrue()
        ->and(SeoKeyword::count())->toBe(1);

    $stored = json_decode((string) Setting::get(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING), true);
    expect(collect($stored['items'])->pluck('keyword')->all())->toBe(['website laten maken', 'seo bureau']);
});

it('doet niets zonder selectie', function () {
    Livewire::test(SeoKeywordSuggestions::class)
        ->call('addSelected')
        ->assertNotified();

    expect(SeoKeyword::count())->toBe(0);
});

it('zet het keyword-onderzoek op de queue en noteert de stand', function () {
    Queue::fake();
    Setting::set('anthropic_api_key', 'test-key');

    Livewire::test(ListSeoKeywords::class)
        ->callAction('suggest')
        ->assertNotified();

    Queue::assertPushed(SuggestKeywordsJob::class);

    expect(JobStatus::for(SuggestKeywordsJob::STATUS_KEY)->state())->toBe(JobStatus::QUEUED);
});

/*
 * De voortgangsmelding. Ze staat in `settings` en niet in een Livewire-
 * property, precies omdat ze een paginabezoek moet overleven: je start het
 * onderzoek, gaat weg, komt terug en moet dán nog zien dat het loopt.
 */
it('toont dat het onderzoek loopt, ook zonder voorstellen', function () {
    Setting::set(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING, null);
    JobStatus::for(SuggestKeywordsJob::STATUS_KEY)->running();

    expect(SeoKeywordSuggestions::canView())->toBeTrue();

    Livewire::test(SeoKeywordSuggestions::class)
        ->assertSee('Keyword-onderzoek loopt')
        ->assertDontSee('Alle voorstellen zijn al opgevolgd')
        ->assertSeeHtml('wire:poll.10s');
});

it('waarschuwt dat de wachtrij niet draait wanneer de taak blijft hangen', function () {
    $this->travelTo(now()->subMinutes(20));
    JobStatus::for(SuggestKeywordsJob::STATUS_KEY)->queued();
    $this->travelBack();

    $status = JobStatus::for(SuggestKeywordsJob::STATUS_KEY);
    expect($status->isBusy())->toBeFalse()
        ->and($status->isStale())->toBeTrue();

    Livewire::test(SeoKeywordSuggestions::class)
        ->assertSee('blijven hangen')
        ->assertSee('wachtrij-worker niet draait')
        ->assertDontSeeHtml('wire:poll');
});

it('zegt waarom het onderzoek niets opleverde', function () {
    JobStatus::for(SuggestKeywordsJob::STATUS_KEY)->failed('DataForSEO gaf fout 40200: saldo op.');

    Livewire::test(SeoKeywordSuggestions::class)
        ->assertSee('Het onderzoek leverde niets op')
        ->assertSee('saldo op')
        ->call('dismissStatus');

    expect(JobStatus::for(SuggestKeywordsJob::STATUS_KEY)->state())->toBeNull();
});

/*
 * Dit was de échte oorzaak van "ik druk op de knop en er gebeurt niets".
 * Het model heeft adaptive thinking aan, dus `content[0]` is een thinking-blok
 * met lege tekst. De code las `content.0.text`, kreeg een lege string, en gaf
 * nul seeds terug — waarna DataForSEO enkel het kale domein als zoekterm
 * voorgeschoteld kreeg en het hele onderzoek zonder één foutmelding leeg
 * terugkwam.
 */
it('leest de zoektermen uit het tekstblok, niet uit het thinking-blok', function () {
    Setting::set('anthropic_api_key', 'test-key');
    Setting::set('dataforseo_login', null);
    Setting::set('dataforseo_password', null);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'thinking', 'thinking' => 'even nadenken…'],
                ['type' => 'text', 'text' => '["website laten maken", "webdesign brugge"]'],
            ],
        ]),
    ]);

    $suggestions = app(SeoAdvisorService::class)->suggestKeywords();

    expect(collect($suggestions)->pluck('keyword')->all())
        ->toBe(['website laten maken', 'webdesign brugge']);
});

it('zegt het wanneer het antwoord alleen een thinking-blok bevat', function () {
    Setting::set('anthropic_api_key', 'test-key');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'max_tokens',
            'content' => [['type' => 'thinking', 'thinking' => 'nog aan het denken…']],
        ]),
    ]);

    $advisor = app(SeoAdvisorService::class);
    $advisor->suggestKeywords();

    expect($advisor->lastError())->toContain('geen bruikbaar antwoord')
        ->and($advisor->lastError())->toContain('max_tokens');
});

it('markeert een leeg resultaat als mislukt in plaats van stil te blijven', function () {
    Setting::set('anthropic_api_key', null);
    Setting::set('dataforseo_login', null);
    Setting::set('dataforseo_password', null);

    (new SuggestKeywordsJob)->handle(app(SeoAdvisorService::class));

    $status = JobStatus::for(SuggestKeywordsJob::STATUS_KEY);
    expect($status->state())->toBe(JobStatus::FAILED)
        ->and($status->message())->toContain('Anthropic-key');
});
