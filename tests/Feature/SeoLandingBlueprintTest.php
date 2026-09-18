<?php

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Setting;
use App\Services\Seo\LandingPageBlueprint;
use App\Services\SeoAdvisorService;
use Illuminate\Support\Facades\Http;

/**
 * Een goedgekeurde "nieuwe pagina"-actie leverde vier secties: hero → tekst →
 * FAQ → CTA. De dienstenpagina's die we met de hand bouwen hebben er acht, en
 * precies de vier die ontbraken (probleemherkenning, voordelen, werkwijze,
 * cases) dragen de conversie. Bovendien werd de knoptekst letterlijk van de
 * homepage gekopieerd, zodat er twee keer dezelfde zin op de pagina stond —
 * over een onderwerp waar ze niet bij hoorde.
 *
 * Wat hier vastligt:
 *  - de opbouw komt uit een aangewezen sjabloonpagina, niet uit code;
 *  - het sjabloon levert het skelet (volgorde, achtergrond, anker-id's,
 *    knopstructuur en -bestemming), het model levert álle tekst incl. labels;
 *  - knoppen behouden de `link_type: page` + `page_id`-vorm, zodat ze blijven
 *    werken als de doelpagina hernoemd wordt;
 *  - een sectie zonder inhoud valt weg i.p.v. leeg te verschijnen, en een
 *    ankerknop verdwijnt mee met de sectie waar hij heen scrolt;
 *  - zonder sjabloon blijft de generieke opbouw werken.
 */
function blueprintTemplate(): Page
{
    $page = Page::create(['title' => 'Sales automation', 'slug' => 'sales-automation', 'published' => true]);

    $sections = [
        ['hero', [
            'heading' => 'Haal meer uit iedere lead.',
            'ctas' => [
                ['label' => 'Ontdek waar je vandaag leads verliest', 'variant' => 'primary', 'link_type' => 'page', 'page_id' => 99],
                ['label' => 'Bekijk onze aanpak', 'variant' => 'ghost', 'link_type' => 'url', 'href' => '#aanpak'],
            ],
        ]],
        ['problem_recognition', ['heading' => 'Leads komen binnen.', 'background' => 'dark']],
        ['advantages', ['heading' => 'Meer afspraken', 'background' => 'light']],
        ['process_steps', ['heading' => 'Onze aanpak', 'background' => 'dark', 'section_id' => 'aanpak']],
        ['cases_grid', [
            'heading' => 'Cases',
            'background' => 'light',
            'limit' => 3,
            'filter_tags' => ['Sales Automation'],
            'cta' => [['label' => 'Bekijk alle cases', 'variant' => 'secondary', 'link_type' => 'url', 'href' => '/cases']],
        ]],
        ['cards', ['heading' => 'Mogelijkheden', 'background' => 'dark', 'columns' => 3, 'section_id' => 'mogelijkheden']],
        ['faq', ['heading' => 'Veelgestelde vragen']],
        ['cta', [
            'heading' => 'Ontdek waar je leads verliest',
            'ctas' => [['label' => 'Plan je Bottleneck Scan', 'variant' => 'primary', 'link_type' => 'page', 'page_id' => 99]],
        ]],
    ];

    foreach ($sections as $position => [$type, $content]) {
        $page->sections()->create(['section_type' => $type, 'position' => $position, 'content' => $content]);
    }

    Setting::set(LandingPageBlueprint::SETTING_KEY, 'sales-automation');

    return $page;
}

/** De velden die het model aanlevert voor een volledige landingspagina. */
function blueprintAiFields(array $overrides = []): array
{
    return array_replace([
        'h1_title' => 'Waarom een AI-telefoonassistent jou tijd teruggeeft',
        'hero_subtitle' => 'Een gemiste oproep is vaak een gemiste klant.',
        'hero_cta_labels' => ['Hoor wat je vandaag misloopt', 'Bekijk hoe we werken'],
        'closing_title' => 'Klaar om geen oproep meer te missen?',
        'closing_body' => 'We kijken vrijblijvend mee.',
        'closing_cta_label' => 'Plan je Bottleneck Scan',
        'problems' => [
            'heading' => 'Herken je dit?',
            'items' => [
                ['title' => 'Je mist oproepen', 'icon' => 'phone-missed', 'description' => 'Je staat bij een klant.', 'tags' => ['Bereikbaarheid']],
                ['title' => 'Voicemail werkt niet', 'description' => 'Bellers spreken zelden in.'],
            ],
        ],
        'benefits' => [
            'heading' => 'Wat verandert er?',
            'items' => [['title' => 'Altijd bereikbaar', 'description' => 'Ook buiten de uren.']],
        ],
        'steps' => [
            'heading' => 'Onze aanpak',
            'items' => [['title' => 'We luisteren mee', 'description' => 'We brengen je oproepen in kaart.']],
        ],
        'cases' => ['heading' => 'Dit leverde het op'],
        'cards' => [
            'heading' => 'Concrete mogelijkheden',
            'items' => [['title' => 'Afspraken inplannen', 'subtitle' => 'Rechtstreeks in je agenda', 'description' => 'De assistent boekt zelf.']],
        ],
    ], $overrides);
}

function blueprintFaq(): array
{
    return [['question' => 'Wat doet een AI-telefoonassistent?', 'answer' => 'Hij neemt op en plant afspraken in.']];
}

it('follows the section order of the template page', function () {
    blueprintTemplate();
    CaseStudy::create(['title' => 'Een case', 'slug' => 'een-case', 'published' => true]);

    $sections = (new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq());

    expect(array_column($sections, 'section_type'))->toBe([
        'hero', 'problem_recognition', 'advantages', 'process_steps',
        'cases_grid', 'cards', 'faq', 'cta',
    ]);
});

it('copies look and feel from the template but none of its copy', function () {
    blueprintTemplate();
    CaseStudy::create(['title' => 'Een case', 'slug' => 'een-case', 'published' => true]);

    $sections = collect((new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq()))
        ->keyBy('section_type');

    // Skelet: achtergronden, anker-id's en structuurknoppen reizen mee.
    expect($sections['problem_recognition']['content']['background'])->toBe('dark')
        ->and($sections['advantages']['content']['background'])->toBe('light')
        ->and($sections['process_steps']['content']['section_id'])->toBe('aanpak')
        ->and($sections['cards']['content']['columns'])->toBe(3)
        ->and($sections['cases_grid']['content']['limit'])->toBe(3);

    // Inhoud: van het model, niet van het sjabloon.
    expect($sections['problem_recognition']['content']['heading'])->toBe('Herken je dit?')
        ->and($sections['cards']['content']['cards'])->toHaveCount(1);

    // Onderwerpgebonden filters nemen we juist NIET over: die zouden cases van
    // het sjabloononderwerp tonen op een pagina die over iets anders gaat.
    expect($sections['cases_grid']['content'])->not->toHaveKey('filter_tags');
});

it('keeps the page-link form of a button so it survives a slug rename', function () {
    blueprintTemplate();

    $sections = collect((new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq()))
        ->keyBy('section_type');

    $primary = $sections['hero']['content']['ctas'][0];

    expect($primary['link_type'])->toBe('page')
        ->and($primary['page_id'])->toBe(99)
        ->and($primary)->not->toHaveKey('href');
});

it('writes new button labels instead of copying the template ones', function () {
    blueprintTemplate();

    $sections = collect((new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq()))
        ->keyBy('section_type');

    expect($sections['hero']['content']['ctas'][0]['label'])->toBe('Hoor wat je vandaag misloopt')
        ->and($sections['hero']['content']['ctas'][1]['label'])->toBe('Bekijk hoe we werken')
        ->and($sections['cta']['content']['ctas'][0]['label'])->toBe('Plan je Bottleneck Scan');
});

it('falls back to the template label when the model supplies none', function () {
    blueprintTemplate();

    $sections = collect((new LandingPageBlueprint)->build(
        blueprintAiFields(['hero_cta_labels' => []]),
        blueprintFaq(),
    ))->keyBy('section_type');

    expect($sections['hero']['content']['ctas'][0]['label'])->toBe('Ontdek waar je vandaag leads verliest');
});

it('drops a section the model left empty', function () {
    blueprintTemplate();

    $sections = (new LandingPageBlueprint)->build(
        blueprintAiFields(['benefits' => ['heading' => 'Wat verandert er?', 'items' => []]]),
        blueprintFaq(),
    );

    expect(array_column($sections, 'section_type'))->not->toContain('advantages');
});

it('removes an anchor button when its target section is gone', function () {
    blueprintTemplate();

    // Zonder stappen valt de werkwijze-sectie weg — en daarmee het anker
    // #aanpak waar de tweede hero-knop heen scrolt.
    $sections = collect((new LandingPageBlueprint)->build(
        blueprintAiFields(['steps' => ['heading' => 'Onze aanpak', 'items' => []]]),
        blueprintFaq(),
    ))->keyBy('section_type');

    expect($sections)->not->toHaveKey('process_steps')
        ->and($sections['hero']['content']['ctas'])->toHaveCount(1)
        ->and($sections['hero']['content']['ctas'][0]['label'])->toBe('Hoor wat je vandaag misloopt');
});

it('skips the cases grid when there are no published cases', function () {
    blueprintTemplate();

    $sections = (new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq());

    expect(array_column($sections, 'section_type'))->not->toContain('cases_grid');
});

it('uses the generic structure when no template is configured', function () {
    Page::create(['title' => 'Home', 'slug' => 'home', 'published' => true, 'is_homepage' => true]);

    $sections = (new LandingPageBlueprint(['label' => 'Plan een gesprek', 'href' => '/contact']))->build(
        blueprintAiFields(['why_title' => 'Waarom nu', 'why_html' => '<p>Omdat het loont.</p>']),
        blueprintFaq(),
    );

    expect(array_column($sections, 'section_type'))->toBe(['hero', 'rich_text', 'faq', 'cta'])
        ->and($sections[0]['content']['ctas'][0]['href'])->toBe('/contact')
        ->and($sections[0]['content']['ctas'][0]['label'])->toBe('Hoor wat je vandaag misloopt');
});

it('builds the full landing page through a create_page action', function () {
    blueprintTemplate();
    CaseStudy::create(['title' => 'Een case', 'slug' => 'een-case', 'published' => true]);

    Setting::set('anthropic_api_key', 'test-key');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'report_actions', 'input' => ['actions' => [
                array_merge([
                    'action_type' => 'create_page',
                    'priority' => 'high',
                    'title' => 'Pagina voor AI-telefoonassistent',
                    'problem' => 'Geen pagina voor dit keyword.',
                    'slug' => 'ai-telefoonassistent',
                    'faq' => blueprintFaq(),
                ], blueprintAiFields()),
            ]]]],
            'stop_reason' => 'tool_use',
            'usage' => ['output_tokens' => 500],
        ]),
    ]);

    $actions = app(SeoAdvisorService::class)->generateActions([
        'target' => 'dewebgoeroe.be',
        'latest' => null,
        'previous' => null,
        'stats' => ['tracked' => 0, 'top3' => 0, 'top10' => 0, 'avg_position' => null, 'in_ai_overview' => 0, 'ai_cited' => 0],
        'up' => [], 'down' => [], 'opportunities' => [], 'geo' => [],
    ]);

    expect($actions)->toHaveCount(1);

    expect(array_column($actions[0]['proposed']['sections'], 'section_type'))->toBe([
        'hero', 'problem_recognition', 'advantages', 'process_steps',
        'cases_grid', 'cards', 'faq', 'cta',
    ]);
});

it('tells the model which sections the template expects', function () {
    blueprintTemplate();
    Setting::set('anthropic_api_key', 'test-key');

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'report_actions', 'input' => ['actions' => []]]],
            'stop_reason' => 'tool_use',
            'usage' => ['output_tokens' => 10],
        ]),
    ]);

    app(SeoAdvisorService::class)->generateActions([
        'target' => 'dewebgoeroe.be',
        'latest' => null,
        'previous' => null,
        'stats' => ['tracked' => 0, 'top3' => 0, 'top10' => 0, 'avg_position' => null, 'in_ai_overview' => 0, 'ai_cited' => 0],
        'up' => [], 'down' => [], 'opportunities' => [], 'geo' => [],
    ]);

    Http::assertSent(function ($request) {
        $prompt = $request->data()['messages'][0]['content'];

        return str_contains($prompt, 'Probleemherkenning → Voordelen → Werkwijze')
            && str_contains($prompt, '`problems`')
            && str_contains($prompt, '2 knoptekst(en)')
            && str_contains($prompt, 'scrolt naar het blok "Werkwijze"');
    });
});

/**
 * De aliassen zijn wat deze klasse overdraagbaar maakt naar andere projecten:
 * hetzelfde blok heet daar anders (`prose`/`text`/`free_text` i.p.v.
 * `rich_text`, `cta_section` i.p.v. `cta`). De blueprint herkent ze via de rol
 * en schrijft de sectie weg onder de naam die dát project gebruikt.
 */
it('recognises section names from other projects through their role', function () {
    $page = Page::create(['title' => 'Dienst', 'slug' => 'dienst', 'published' => true]);

    foreach ([
        ['hero', ['heading' => 'Kop', 'ctas' => [['label' => 'Doe iets', 'variant' => 'primary', 'link_type' => 'page', 'page_id' => 7]]]],
        ['prose', ['heading' => 'Waarom']],          // elders: rich_text
        ['tiles_grid', ['heading' => 'Aanbod']],     // elders: cards
        ['faq', ['heading' => 'FAQ']],
        ['cta_section', ['heading' => 'Slot', 'ctas' => [['label' => 'Slotknop', 'variant' => 'primary', 'link_type' => 'page', 'page_id' => 7]]]],  // elders: cta
    ] as $position => [$type, $content]) {
        $page->sections()->create(['section_type' => $type, 'position' => $position, 'content' => $content]);
    }

    Setting::set(LandingPageBlueprint::SETTING_KEY, 'dienst');

    $sections = (new LandingPageBlueprint)->build(
        blueprintAiFields(['why_title' => 'Waarom nu', 'why_html' => '<p>Daarom.</p>']),
        blueprintFaq(),
    );

    // De sectienamen van dít project blijven behouden, niet de canonieke.
    expect(array_column($sections, 'section_type'))->toBe(['hero', 'prose', 'tiles_grid', 'faq', 'cta_section']);

    // En de inhoud belandt in de juiste sleutel van dat sectietype.
    expect($sections[1]['content']['heading'])->toBe('Waarom nu')
        ->and($sections[2]['content']['cards'])->toHaveCount(1)
        ->and($sections[4]['content']['ctas'][0]['label'])->toBe('Plan je Bottleneck Scan');
});

it('skips a template section it cannot fill', function () {
    $page = Page::create(['title' => 'Dienst', 'slug' => 'dienst', 'published' => true]);

    foreach ([
        ['hero', ['heading' => 'Kop', 'ctas' => [['label' => 'Doe iets', 'variant' => 'primary', 'href' => '/contact']]]],
        // Een formulier en echte klantcitaten kan een model niet aanleveren.
        ['form', ['heading' => 'Contacteer ons']],
        ['testimonials', ['heading' => 'Wat klanten zeggen']],
        ['faq', ['heading' => 'FAQ']],
    ] as $position => [$type, $content]) {
        $page->sections()->create(['section_type' => $type, 'position' => $position, 'content' => $content]);
    }

    Setting::set(LandingPageBlueprint::SETTING_KEY, 'dienst');

    $sections = (new LandingPageBlueprint)->build(blueprintAiFields(), blueprintFaq());

    expect(array_column($sections, 'section_type'))->toBe(['hero', 'faq']);
});
