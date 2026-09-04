<?php

use App\Filament\Resources\SeoKeywords\Pages\ListSeoKeywords;
use App\Filament\Widgets\SeoKeywordSuggestions;
use App\Jobs\SuggestKeywordsJob;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Models\User;
use App\Services\SeoAdvisorService;
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

it('zet het keyword-onderzoek op de queue', function () {
    Queue::fake();
    Setting::set('anthropic_api_key', 'test-key');

    Livewire::test(ListSeoKeywords::class)
        ->callAction('suggest')
        ->assertNotified();

    Queue::assertPushed(SuggestKeywordsJob::class);
});
