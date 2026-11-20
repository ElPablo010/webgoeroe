<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\AiCoaching;
use Webgoeroe\Core\Agenda\Agenda;
use Webgoeroe\Core\Support\Modules;

/**
 * Pagina /ai-coaching (migraties 2026_11_19_120000_create_ai_coaching_page en
 * 2026_11_19_220000_ai_coaching_paid_sessions): betaalde sessies, gepubliceerd.
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
    (require database_path('migrations/2026_11_19_220000_ai_coaching_paid_sessions.php'))->up();
});

it('publiceert de pagina met elf blokken', function () {
    $page = Page::query()->where('slug', 'ai-coaching')->sole();

    expect($page->published)->toBeTrue()
        ->and($page->sections)->toHaveCount(11);

    $this->get('/ai-coaching')->assertOk();
});

it('rendert de pagina met de tekst, de knoppen naar de agenda en de keuze tussen beide soorten', function () {
    $html = $this->get('/ai-coaching')
        ->assertOk()
        ->assertSee('Een AI-coach die het samen met je opzet.')
        ->assertSee('2 uur online · € 250 excl. btw')
        ->assertSee('Twee prijzen, geen abonnement')
        ->assertSee('Is dit iets voor jou?')
        ->assertSee('Boek je sessie')
        ->assertSee('Liever eerst een vraag stellen?')
        ->assertSee('Startsessie')
        ->assertSee('Vervolguur')
        ->getContent();

    // Geen geld-terug-garantie (beslist 19/11/2026): nergens op de pagina, ook niet in meta, FAQ of schema.
    expect(mb_strtolower($html))->not->toContain('geld terug')->not->toContain('garantie')->not->toContain('terugbetaal');

    // Hero, na sectie 4 en bij de prijzen: altijd dezelfde knop naar de agenda onderaan.
    expect(substr_count($html, 'href="#agenda"'))->toBe(3)
        ->and($html)->toContain('id="agenda"');
});

it('zet de betaalde soorten klaar in de eigen agenda: startsessie voorgeselecteerd, vervolguur erbij', function () {
    expect(Modules::enabled(Modules::AGENDA))->toBeTrue()
        ->and(Modules::enabled(Modules::PAYMENTS))->toBeTrue();

    $start = Agenda::type('startsessie');
    $follow = Agenda::type('vervolguur');

    expect($start->duration)->toBe(120)
        ->and($start->priceCents)->toBe(25000)
        ->and($start->vatRate)->toBe(21)
        ->and($start->totalCents())->toBe(30250)
        ->and($follow->duration)->toBe(60)
        ->and($follow->priceCents)->toBe(7500)
        ->and($follow->vatRate)->toBe(21)
        ->and(Agenda::type()->isPaid())->toBeFalse();

    $sections = Page::query()->where('slug', 'ai-coaching')->sole()->sections->where('section_type', 'booking')->values();

    expect($sections)->toHaveCount(1)
        ->and($sections[0]->content['agenda_type'])->toBe('startsessie')
        ->and(array_keys(Agenda::choicesFor($sections[0]->content)))->toBe(['startsessie', 'vervolguur'])
        ->and($sections[0]->content)->not->toHaveKey('swap_with');

    // Opnieuw draaien maakt niets dubbel en verandert niets.
    (require database_path('migrations/2026_11_19_120000_create_ai_coaching_page.php'))->up();
    (require database_path('migrations/2026_11_19_220000_ai_coaching_paid_sessions.php'))->up();
    expect(collect(Agenda::setting('types'))->pluck('key')->all())->toBe(['startsessie', 'vervolguur'])
        ->and(Page::query()->where('slug', 'ai-coaching')->sole()->sections()->where('section_type', 'booking')->count())->toBe(1);
});

it('vraagt bedrijfsnaam en btw-nummer als optionele velden voor de factuur, zonder dubbels', function () {
    $migration = require database_path('migrations/2026_11_20_120000_ai_coaching_booking_invoice_fields.php');
    $migration->up();
    $migration->up();

    $section = Page::query()->where('slug', 'ai-coaching')->sole()->sections()->where('section_type', 'booking')->sole();
    $fields = collect($section->content['agenda_fields'])->keyBy('label');

    expect($fields->keys()->all())->toBe(['Voornaam', 'E-mailadres', 'Telefoon', 'Bedrijfsnaam', 'Btw-nummer', 'Website', 'Wat doe je?', 'Welke taak kost je elke week het meeste tijd?'])
        ->and($fields['Bedrijfsnaam']['required'])->toBeFalse()
        ->and($fields['Btw-nummer']['required'])->toBeFalse();
});

it('bevat de schema-data: Service met twee prijzen, FAQPage en BreadcrumbList', function () {
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

it('toont het menu-item en houdt het weg zolang de pagina een concept is', function () {
    $item = MenuItem::query()->where('label', 'AI-coaching')->sole();
    expect($item->parent->label)->toBe('Diensten')->and($item->position)->toBe(4);

    $this->get('/bottleneck-scan')->assertOk()->assertSee('AI-coaching');

    Page::query()->where('slug', 'ai-coaching')->update(['published' => false]);

    $this->get('/bottleneck-scan')->assertOk()->assertDontSee('AI-coaching');
});

it('zet de regel "Liever zelf leren?" onder de slotknop van drie pagina\'s', function () {
    foreach (['ai-automatisering-zelfstandigen', 'ai-automatisering-kmo', 'business-automation'] as $slug) {
        $this->get('/'.$slug)->assertOk()
            ->assertSee('Liever zelf leren?')
            ->assertSee('Bekijk onze AI-coaching.')
            ->assertSee('href="/ai-coaching"', false);
    }
});
