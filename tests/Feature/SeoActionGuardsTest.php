<?php

use App\Models\Page;
use App\Models\Setting;
use App\Services\SeoAdvisorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * De SEO-suggestietool stelde FAQ-vragen voor die al (in andere woorden) op de
 * doelpagina stonden, bleef FAQ-blokken bijvullen tot 8+ vragen, en hield geen
 * rekening met wat er net al gebeurd was: een pagina die vorige week gemaakt of
 * herschreven werd rankt nog niet (Google heeft weken nodig), dus de cijfers
 * bleven "kans!" roepen en de tool bleef hetzelfde werk opnieuw voorstellen.
 *
 * Wat hier vastligt (de vier guards in normalizeAction):
 *  - een voorgestelde FAQ-vraag die inhoudelijk overlapt met een bestaande
 *    wordt weggefilterd; blijft er niets over, dan vervalt de hele actie;
 *  - een vol FAQ-blok (FAQ_MAX_QUESTIONS) krijgt geen add_section meer, en
 *    nieuwe vragen worden afgetopt tot de grens;
 *  - geen create_page voor een keyword dat een bestaande pagina al dekt;
 *  - geen optimize_meta die een bestaande meta herschrijft op een pagina die
 *    minder dan RECENT_DAYS geleden aangeraakt is (lege velden invullen mag).
 */
beforeEach(function () {
    Setting::set('anthropic_api_key', 'test-key');
    Setting::set('dataforseo_login', 'test-login');
    Setting::set('dataforseo_password', 'test-password');
    Setting::set('seo_target_domain', 'dewebgoeroe.be');
});

function seoGuardContext(): array
{
    return [
        'target' => 'dewebgoeroe.be',
        'latest' => null,
        'previous' => null,
        'stats' => ['tracked' => 0, 'top3' => 0, 'top10' => 0, 'avg_position' => null, 'in_ai_overview' => 0, 'ai_cited' => 0],
        'up' => [],
        'down' => [],
        'opportunities' => [],
        'geo' => [],
    ];
}

function seoGuardPage(array $attributes, ?int $daysOld = null): Page
{
    $page = Page::create(array_merge(['published' => true], $attributes));

    if ($daysOld !== null) {
        // Via de query builder: een Eloquent-save zou updated_at meteen
        // weer op "nu" zetten.
        DB::table('pages')->where('id', $page->id)->update([
            'created_at' => Carbon::now()->subDays($daysOld),
            'updated_at' => Carbon::now()->subDays($daysOld),
        ]);
    }

    return $page->refresh();
}

function seoGuardPageWithFaq(string $slug, array $questions): Page
{
    $page = seoGuardPage(['title' => ucfirst($slug), 'slug' => $slug]);

    $page->sections()->create([
        'section_type' => 'faq',
        'position' => 0,
        'content' => [
            'heading' => 'Veelgestelde vragen',
            'items' => array_map(fn ($q) => ['question' => $q, 'answer' => 'Bij De Webgoeroe.'], $questions),
        ],
    ]);

    return $page;
}

function seoGuardFakeProposes(array $actions): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'report_actions', 'input' => ['actions' => $actions]]],
            'stop_reason' => 'tool_use',
            'usage' => ['output_tokens' => 500],
        ]),
    ]);
}

it('filters out a question that is already answered on the page', function () {
    seoGuardPageWithFaq('webdesign', ['Wat kost een website?']);

    seoGuardFakeProposes([[
        'action_type' => 'add_section',
        'priority' => 'high',
        'title' => 'FAQ uitbreiden',
        'problem' => 'Vragen zonder antwoord.',
        'target_slug' => 'webdesign',
        'faq' => [
            ['question' => 'Wat kost een website laten maken in Antwerpen?', 'answer' => 'Vanaf een vaste prijs.'],
            ['question' => 'Doen jullie ook onderhoud na de lancering?', 'answer' => 'Ja, via een onderhoudscontract.'],
        ],
    ]]);

    $actions = app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    expect($actions)->toHaveCount(1)
        ->and(array_column($actions[0]['proposed']['content']['items'], 'question'))
        ->toBe(['Doen jullie ook onderhoud na de lancering?']);
});

it('drops the whole action when no genuinely new questions remain', function () {
    seoGuardPageWithFaq('webdesign', ['Wat kost een website?']);

    seoGuardFakeProposes([[
        'action_type' => 'add_section',
        'priority' => 'high',
        'title' => 'FAQ uitbreiden',
        'problem' => 'Vragen zonder antwoord.',
        'target_slug' => 'webdesign',
        'faq' => [['question' => 'Wat kost een website laten maken?', 'answer' => 'Vanaf een vaste prijs.']],
    ]]);

    expect(app(SeoAdvisorService::class)->generateActions(seoGuardContext()))->toBe([]);
});

it('refuses new questions on a full FAQ block and caps additions below it', function () {
    seoGuardPageWithFaq('vol', array_map(
        fn ($i) => "Bestaande vraag nummer {$i} over websites?",
        range(1, SeoAdvisorService::FAQ_MAX_QUESTIONS)
    ));
    seoGuardPageWithFaq('bijna-vol', array_map(
        fn ($i) => "Andere bestaande vraag nummer {$i} over hosting?",
        range(1, SeoAdvisorService::FAQ_MAX_QUESTIONS - 2)
    ));

    $newFaq = [
        ['question' => 'Kan ik zelf teksten aanpassen?', 'answer' => 'Ja.'],
        ['question' => 'Is hosting inbegrepen?', 'answer' => 'Ja.'],
        ['question' => 'Krijg ik een opleiding bij oplevering?', 'answer' => 'Ja.'],
    ];
    seoGuardFakeProposes([
        ['action_type' => 'add_section', 'priority' => 'high', 'title' => 'FAQ vol', 'problem' => 'x', 'target_slug' => 'vol', 'faq' => $newFaq],
        ['action_type' => 'add_section', 'priority' => 'high', 'title' => 'FAQ bijna vol', 'problem' => 'x', 'target_slug' => 'bijna-vol', 'faq' => $newFaq],
    ]);

    $actions = app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    // De volle pagina valt weg; de bijna-volle houdt maar 2 van de 3 vragen over.
    expect($actions)->toHaveCount(1)
        ->and($actions[0]['title'])->toBe('FAQ bijna vol')
        ->and($actions[0]['proposed']['content']['items'])->toHaveCount(2);
});

it('refuses a create_page for a keyword an existing page already covers', function () {
    seoGuardPage(['title' => 'Webdesigner in Antwerpen', 'slug' => 'webdesigner-antwerpen'], 7);

    $createPage = fn (string $keyword, string $slug) => [
        'action_type' => 'create_page',
        'priority' => 'high',
        'title' => "Landingspagina {$keyword}",
        'problem' => 'Keyword rankt niet.',
        'source_keyword' => $keyword,
        'slug' => $slug,
        'h1_title' => "Pagina over {$keyword}",
        'why_html' => '<p>Omdat een sterke site verkoopt.</p>',
    ];
    seoGuardFakeProposes([
        $createPage('webdesign antwerpen', 'webdesign-antwerpen'),
        $createPage('webshop laten maken mechelen', 'webshop-mechelen'),
    ]);

    $actions = app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['source_keyword'])->toBe('webshop laten maken mechelen');
});

it('leaves an existing meta alone on a recently touched page but fills empty fields', function () {
    seoGuardPage(['title' => 'Webdesign', 'slug' => 'webdesign', 'meta_title' => 'Webdesign | De Webgoeroe'], 7);

    seoGuardFakeProposes([[
        'action_type' => 'optimize_meta',
        'priority' => 'medium',
        'title' => 'Meta verbeteren',
        'problem' => 'Zwakke meta.',
        'target_slug' => 'webdesign',
        'meta_title' => 'Nieuwe titel | De Webgoeroe',
        'meta_description' => 'Een frisse nieuwe beschrijving.',
    ]]);

    $actions = app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    // De bestaande meta_title blijft met rust; enkel de lege description mag.
    expect($actions)->toHaveCount(1)
        ->and($actions[0]['proposed'])->toBe(['meta_description' => 'Een frisse nieuwe beschrijving.']);
});

it('allows a full meta rewrite on an old untouched page', function () {
    seoGuardPage([
        'title' => 'Webdesign',
        'slug' => 'webdesign',
        'meta_title' => 'Oude titel',
        'meta_description' => 'Oude beschrijving.',
    ], SeoAdvisorService::RECENT_DAYS + 30);

    seoGuardFakeProposes([[
        'action_type' => 'optimize_meta',
        'priority' => 'medium',
        'title' => 'Meta verbeteren',
        'problem' => 'Zwakke meta.',
        'target_slug' => 'webdesign',
        'meta_title' => 'Nieuwe titel | De Webgoeroe',
        'meta_description' => 'Een frisse nieuwe beschrijving.',
    ]]);

    $actions = app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['proposed'])->toHaveKeys(['meta_title', 'meta_description']);
});

it('feeds the prompt the existing FAQ questions and recency markers', function () {
    seoGuardPageWithFaq('webdesign', ['Wat kost een website?']);
    seoGuardPage(['title' => 'Hosting', 'slug' => 'hosting'], SeoAdvisorService::RECENT_DAYS + 30);

    seoGuardFakeProposes([]);
    app(SeoAdvisorService::class)->generateActions(seoGuardContext());

    Http::assertSent(function ($request) {
        $prompt = $request->data()['messages'][0]['content'] ?? '';

        return str_contains($prompt, 'Bestaande FAQ-vragen per pagina')
            && str_contains($prompt, 'Wat kost een website?')
            && preg_match('/webdesign.*NIEUW sinds/', $prompt)
            && ! preg_match('/hosting.*(NIEUW|RECENT)/', $prompt);
    });
});
