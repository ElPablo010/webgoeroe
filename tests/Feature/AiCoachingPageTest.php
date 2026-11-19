<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\AiCoaching;
use Webgoeroe\Core\Agenda\Agenda;
use Webgoeroe\Core\Support\Modules;

/**
 * Pagina /ai-coaching (migratie 2026_11_19_120000_create_ai_coaching_page): concept tot
 * betalen bij het boeken werkt, daarna publiek.
 */
beforeEach(function () {
    Page::query()->create(['title' => 'Bottleneck scan', 'slug' => 'bottleneck-scan', 'locale' => 'nl', 'published' => true]);
    $menu = Menu::query()->create(['location' => 'main', 'name' => 'Hoofdmenu']);
    $diensten = MenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'Diensten', 'position' => 0]);
    foreach (['ai-automatisering-zelfstandigen', 'ai-automatisering-kmo', 'business-automation'] as $slug) {
        $other = Page::query()->create(['title' => $slug, 'slug' => $slug, 'locale' => 'nl', 'published' => true]);
        $other->sections()->create(['section_type' => 'cta', 'position' => 0, 'locale' => 'nl', 'content' => ['heading' => 'Slot', 'ctas' => []]]);
    }
    MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $diensten->id, 'label' => 'Business Automation', 'position' => 3]);

    (require database_path('migrations/2026_11_19_120000_create_ai_coaching_page.php'))->up();
});

it('zet de pagina op concept: niet publiek bereikbaar', function () {
    $page = Page::query()->where('slug', 'ai-coaching')->sole();

    expect($page->published)->toBeFalse()
        ->and($page->sections)->toHaveCount(12);

    $this->get('/ai-coaching')->assertNotFound();
});

it('rendert de pagina met de tekst, de knoppen naar de agenda en beide soorten afspraak', function () {
    Page::query()->where('slug', 'ai-coaching')->update(['published' => true]);

    $html = $this->get('/ai-coaching')
        ->assertOk()
        ->assertSee('Een AI-coach die het samen met je opzet.')
        ->assertSee('2 uur online · € 250 excl. btw · niet tevreden, geld terug')
        ->assertSee('Twee prijzen, geen abonnement')
        ->assertSee('Is dit iets voor jou?')
        ->assertSee('Boek je startsessie')
        ->assertSee('Boek een vervolguur')
        ->assertSee('Startsessie · 2 uur · € 250')
        ->assertSee('Vervolguur · 1 uur · € 75')
        ->getContent();

    // Hero, na sectie 4 en bij de prijzen: altijd dezelfde knop naar de agenda onderaan.
    expect(substr_count($html, 'href="#agenda"'))->toBe(3)
        ->and($html)->toContain('id="agenda"');
});

it('zet de twee soorten afspraak klaar in de eigen agenda, de startsessie voorgeselecteerd', function () {
    expect(Modules::enabled(Modules::AGENDA))->toBeTrue()
        ->and(Agenda::type('startsessie')->duration)->toBe(120)
        ->and(Agenda::type('vervolguur')->duration)->toBe(60);

    $sections = Page::query()->where('slug', 'ai-coaching')->sole()->sections->where('section_type', 'booking')->values();

    expect($sections->pluck('content.agenda_type')->all())->toBe(['startsessie', 'vervolguur'])
        ->and($sections[0]->content['swap_hidden'])->toBeFalse()
        ->and($sections[1]->content['swap_hidden'])->toBeTrue();

    // Een tweede keer draaien maakt niets dubbel.
    (require database_path('migrations/2026_11_19_120000_create_ai_coaching_page.php'))->up();
    expect(collect(Agenda::setting('types'))->pluck('key')->all())->toBe(['startsessie', 'vervolguur']);
});

it('bevat de schema-data: Service met twee prijzen, FAQPage en BreadcrumbList', function () {
    Page::query()->where('slug', 'ai-coaching')->update(['published' => true]);

    $html = $this->get('/ai-coaching')->assertOk()->getContent();
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $nodes = collect($m[1])->flatMap(function (string $json): array {
        $data = json_decode($json, true);

        return $data['@graph'] ?? [$data];
    });

    $service = $nodes->firstWhere('@type', 'Service');

    expect($service)->not->toBeNull()
        ->and(collect($service['offers'])->pluck('price')->all())->toBe([(string) AiCoaching::START_PRICE, (string) AiCoaching::FOLLOW_UP_PRICE])
        ->and($service['offers'][0]['priceCurrency'])->toBe('EUR')
        ->and($nodes->firstWhere('@type', 'FAQPage')['mainEntity'])->toHaveCount(8)
        ->and($nodes->firstWhere('@type', 'BreadcrumbList'))->not->toBeNull();

    // Indexeerbaar, met de title en description uit het plan.
    expect($html)->toContain("AI-coach voor zelfstandigen en kmo's | De WebGoeroe")
        ->and($html)->toContain('Startsessie € 250, daarna € 75 per uur.')
        ->and($html)->not->toContain('noindex');
});

it('houdt de prijzen in de tekst gelijk aan die in het schema', function () {
    $texts = Page::query()->where('slug', 'ai-coaching')->sole()->sections
        ->where('section_type', 'cards')->pluck('content.cards')->flatten(1)->pluck('subtitle')->filter()->implode(' ');

    expect($texts)->toContain('€ '.AiCoaching::START_PRICE)->toContain('€ '.AiCoaching::FOLLOW_UP_PRICE);
});

it('toont het menu-item pas als de pagina gepubliceerd is', function () {
    $item = MenuItem::query()->where('label', 'AI-coaching')->sole();
    expect($item->parent->label)->toBe('Diensten')->and($item->position)->toBe(4);

    $this->get('/bottleneck-scan')->assertOk()->assertDontSee('AI-coaching');

    Page::query()->where('slug', 'ai-coaching')->update(['published' => true]);

    $this->get('/bottleneck-scan')->assertOk()->assertSee('AI-coaching');
});

it('zet de regel "Liever zelf leren?" onder de slotknop van drie pagina\'s, maar pas na publicatie', function () {
    foreach (['ai-automatisering-zelfstandigen', 'ai-automatisering-kmo', 'business-automation'] as $slug) {
        $this->get('/'.$slug)->assertOk()->assertDontSee('Liever zelf leren?');
    }

    Page::query()->where('slug', 'ai-coaching')->update(['published' => true]);

    foreach (['ai-automatisering-zelfstandigen', 'ai-automatisering-kmo', 'business-automation'] as $slug) {
        $this->get('/'.$slug)->assertOk()
            ->assertSee('Liever zelf leren?')
            ->assertSee('Bekijk onze AI-coaching.')
            ->assertSee('href="/ai-coaching"', false);
    }
});
