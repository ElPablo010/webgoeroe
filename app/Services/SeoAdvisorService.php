<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SeoGeoCheck;
use App\Models\SeoKeyword;
use App\Models\SeoSiteSnapshot;
use App\Models\Setting;
use App\Support\FaqQuestionMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zet de verzamelde SEO-cijfers om in een concrete, geprioriteerde
 * Nederlandse actielijst via de Anthropic API (key uit Setting, zoals
 * de bestaande vertaalservices).
 */
class SeoAdvisorService
{
    protected string $model = 'claude-sonnet-5';

    /**
     * Hoeveel vragen een FAQ-blok hoogstens mag tellen. Boven deze grens
     * scant het blok niet meer voor een bezoeker; nieuwe voorstellen worden
     * dan geweigerd in plaats van blind aangevuld.
     */
    public const FAQ_MAX_QUESTIONS = 7;

    /**
     * Hoe lang een pagina na aanmaak of aanpassing als "recent" geldt.
     * Google heeft weken nodig om een contentwijziging te verwerken; zolang
     * mag een tegenvallende positie niet tot een nieuw voorstel voor
     * diezelfde pagina of hetzelfde keyword leiden — anders krijg je week
     * na week hetzelfde advies terug voor werk dat al gebeurd is.
     */
    public const RECENT_DAYS = 60;

    /** Gecachete ankers uit de homepage (herbruikbare CTA-link + huisstijl-toon). */
    protected ?array $homepageBlueprint = null;

    public function __construct(protected DataForSeoService $api)
    {
    }

    /**
     * Bouwt een gestructureerde samenvatting van de huidige stand van zaken.
     * Wordt zowel in de prompt als in de e-mail/rapport gebruikt.
     */
    public function buildContext(): array
    {
        $snapshots = SeoSiteSnapshot::where('target', $this->api->target)->orderBy('captured_at')->get();
        $latest = $snapshots->last();
        $previous = $snapshots->count() > 1 ? $snapshots[$snapshots->count() - 2] : null;

        $keywords = SeoKeyword::where('is_active', true)->with('latestResult')->get();
        $results = $keywords->map(fn ($k) => [
            'keyword' => $k->keyword,
            'result' => $k->latestResult,
        ])->filter(fn ($r) => $r['result']);

        $ranked = $results->filter(fn ($r) => $r['result']->rank_group);

        $movers = $results
            ->filter(fn ($r) => $r['result']->delta !== null && $r['result']->delta !== 0)
            ->map(fn ($r) => ['keyword' => $r['keyword'], 'rank' => $r['result']->rank_group, 'delta' => $r['result']->delta]);

        // Kansen: keywords met volume maar niet in top 10 (of niet rankend).
        $opportunities = $results
            ->filter(fn ($r) => ($r['result']->search_volume ?? 0) >= 30 && (!$r['result']->rank_group || $r['result']->rank_group > 10))
            ->sortByDesc(fn ($r) => $r['result']->search_volume ?? 0)
            ->take(15)
            ->map(fn ($r) => ['keyword' => $r['keyword'], 'rank' => $r['result']->rank_group, 'volume' => $r['result']->search_volume]);

        $geo = SeoGeoCheck::latest('checked_at')->limit(20)->get()
            ->map(fn ($c) => ['prompt' => $c->prompt, 'engine' => $c->engine, 'mentioned' => $c->brand_mentioned, 'cited' => $c->domain_cited]);

        return [
            'target' => $this->api->target,
            'latest' => $latest,
            'previous' => $previous,
            'stats' => [
                'tracked' => $results->count(),
                'top3' => $ranked->filter(fn ($r) => $r['result']->rank_group <= 3)->count(),
                'top10' => $ranked->filter(fn ($r) => $r['result']->rank_group <= 10)->count(),
                'avg_position' => $ranked->count() ? round($ranked->avg(fn ($r) => $r['result']->rank_group), 1) : null,
                'in_ai_overview' => $results->filter(fn ($r) => $r['result']->in_ai_overview)->count(),
                'ai_cited' => $results->filter(fn ($r) => $r['result']->ai_overview_cited)->count(),
            ],
            'up' => $movers->filter(fn ($m) => $m['delta'] > 0)->sortByDesc('delta')->take(8)->values()->all(),
            'down' => $movers->filter(fn ($m) => $m['delta'] < 0)->sortBy('delta')->take(8)->values()->all(),
            'opportunities' => $opportunities->values()->all(),
            'geo' => $geo->values()->all(),
        ];
    }

    /** Genereer de markdown-actielijst. Geeft null bij ontbrekende key of fout. */
    public function generateAdvice(array $context): ?string
    {
        $apiKey = Setting::get('anthropic_api_key') ?: config('services.anthropic.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $summary = $this->contextToText($context);

        $brand = Setting::get('brand_name') ?: config('app.name');
        $sector = Setting::get('business_description')
            ?: 'een lokale onderneming';

        $prompt = <<<PROMPT
Je bent een SEO-consultant voor {$brand} — {$sector} (domein {$context['target']}).
Hieronder staan de verse SEO-cijfers. Schrijf een beknopte stand van zaken + een concrete, geprioriteerde actielijst in het Nederlands.

Context over de beschikbare tooling — hou hier strikt rekening mee, beveel GEEN externe tools aan die dit dupliceren:
- Keyword-posities, organisch verkeer én AI-zichtbaarheid (GEO) worden in deze app al automatisch opgevolgd via DataForSEO. Adviseer dus NOOIT losse tools als Semrush, Ubersuggest of Google Search Console voor tracking. Verwijs in plaats daarvan naar "voeg dit keyword toe aan je opgevolgde keywords in het SEO-dashboard".
- De publieke website wordt beheerd met een ingebouwde page-builder in Filament: nieuwe pagina's en herbruikbare secties (hero, tekst, FAQ, CTA, ...) maakt de beheerder zelf in de admin. Verwijs bij content-acties naar "maak/optimaliseer deze pagina in de website builder", niet naar een externe CMS of developer.
- Elke pagina heeft in de admin een SEO-tab met meta-titel, meta-omschrijving, canonical, robots en een deel-afbeelding. Verwijs daarheen voor on-page aanpassingen.
- Let op het verschil: "Aantal keywords in Google" (organische keywords volgens Google) is iets ANDERS dan "Opgevolgde keywords" (de lijst die zelf getrackt wordt). Verwar ze niet. Staan er 0 opgevolgde keywords, dan is de actie "voeg kernzinnen toe aan de opgevolgde lijst in het dashboard" — niet "installeer een tracking-tool".
- Doe GEEN uitspraken over welke JSON-LD schema's de site al heeft; dat verschilt per project. Formuleer schema-advies als een voorstel ("overweeg X-markup toe te voegen"), niet als een vaststelling.

Structuur (gebruik markdown):
1. **Korte conclusie** (2-3 zinnen): hoe staan we ervoor, wat is de belangrijkste beweging?
2. **Top-prioriteiten** (max 5 bullets): de meest impactvolle acties, telkens concreet ("optimaliseer pagina X voor keyword Y") en uitvoerbaar deze week. Verwijs naar specifieke keywords/pagina's uit de data.
3. **GEO / AI-zichtbaarheid**: 1-2 zinnen over of we in AI-antwoorden verschijnen en wat eraan te doen.

Wees concreet en to-the-point, geen algemeenheden. Spreek de lezer aan met "je".

DATA:
{$summary}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 8000,
                // Adaptive thinking staat op dit model standaard aan; expliciet
                // meegeven maakt dat zichtbaar. `effort: medium` houdt het
                // denkwerk (en de kost) in de hand voor een weekrapport.
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'medium'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (!$response->successful()) {
                Log::warning('SEO-advies mislukt', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            return $this->firstTextBlock($response->json('content', []));
        } catch (\Throwable $e) {
            Log::error('SEO-advies fout', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Haal het eerste tekstblok uit een Messages-API-antwoord.
     *
     * Niet `content[0]` pakken: met adaptive thinking aan is het eerste blok een
     * thinking-blok (standaard met lege tekst), en dan zou het advies stilzwijgend
     * leeg terugkomen in plaats van te falen.
     *
     * @param  array<int, array<string, mixed>>  $content
     */
    protected function firstTextBlock(array $content): ?string
    {
        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'text' && filled($block['text'] ?? null)) {
                return trim($block['text']);
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     | Gestructureerde acties (voor het goedkeuringsdashboard)
     * ------------------------------------------------------------------- */

    /**
     * Zet de verse cijfers om in een lijst uitvoerbare content-acties
     * (create_page / add_section / optimize_meta), genormaliseerd en klaar om
     * als SeoActionItem te bewaren. Gebruikt Anthropic tool-use zodat de output
     * gestructureerd en betrouwbaar is, gegrond op de echte pagina's + de door
     * de beheerder ingevoerde feiten (SEO → Instellingen → "Feiten voor AI").
     *
     * @return array<int,array<string,mixed>>
     */
    public function generateActions(array $context): array
    {
        $apiKey = Setting::get('anthropic_api_key') ?: config('services.anthropic.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $brand = Setting::get('brand_name') ?: config('app.name');
        $sector = Setting::get('business_description') ?: 'een lokale onderneming';
        $summary = $this->contextToText($context);
        $grounding = $this->buildGroundingText();

        $prompt = <<<PROMPT
Je bent tegelijk een **SEO-strateeg**, een **conversie-copywriter** en een **landingspagina-expert** voor {$brand} — {$sector} (domein {$context['target']}).
Je schrijft in de huisstijl-toon van {$brand} (zie de voorbeelden bij de feiten): warm en persoonlijk, aansprekend met "je", concreet, nooit stijf of corporate.
Zet de onderstaande cijfers om in een korte lijst **uitvoerbare content-acties** die zowel de zichtbaarheid (SEO) als de **conversie** verhogen. Rapporteer ze via de tool `report_actions`.

Regels:
- Enkel content-acties, één van: `create_page` (nieuwe pagina voor een keyword zonder ranking), `add_section` (FAQ-blok toevoegen aan een bestaande pagina), `optimize_meta` (ontbrekende/zwakke meta-title of -description invullen).
- Maximaal 6 acties. Prioriteer op impact (zoekvolume, AI-zichtbaarheid). Verwijs naar specifieke keywords/pagina's uit de data.
- Schrijf alle klantgerichte tekst in het **Nederlands**, spreek aan met "je", in de huisstijl-toon van {$brand}.
- Voor `add_section` en `optimize_meta`: gebruik in `target_slug` de slug van een pagina uit de lijst hieronder ("/" voor de homepage). Verzin geen pagina's.
- Voor `add_section`: bekijk eerst de bestaande FAQ-vragen van de doelpagina (onder "Bestaande FAQ-vragen per pagina" bij de feiten). Stel GEEN vraag voor die daar inhoudelijk al beantwoord wordt — ook niet in andere bewoordingen: "Wat kost een website?" en "Wat kost een website laten maken in Antwerpen?" zijn qua intentie en antwoord dezelfde vraag. Liever géén actie dan een variant van een bestaande vraag.
- Een FAQ-blok moet scanbaar blijven: hoogstens zo'n 6-7 vragen per pagina. Staat een pagina als "VOL" gemarkeerd, stel er dan geen `add_section` meer voor voor. Zit ze er net onder, stel dan hoogstens het aantal vragen voor dat er nog bij kan.
- Pagina's gemarkeerd als "NIEUW" of "RECENT aangepast" zijn pas gewijzigd; Google heeft dat nog niet verwerkt (dat duurt weken). Dat het keyword van zo'n pagina nog niet (beter) rankt, betekent dus "nog even geduld", NIET "actie nodig". Stel voor zo'n pagina geen `optimize_meta` voor (tenzij de meta volledig ontbreekt) en maak geen `create_page` voor een keyword dat zo'n pagina al afdekt.
- Maak sowieso nooit een `create_page` voor een keyword dat al gedekt wordt door een bestaande pagina (kijk naar titels en slugs in de lijst hieronder): twee pagina's op hetzelfde keyword kannibaliseren elkaar. Verbeter dan liever die bestaande pagina, of sla het keyword over.
- Gebruik voor concrete feiten (adres, openingsuren, prijzen, USP's) **uitsluitend** de aangeleverde feiten. Weet je iets niet zeker, laat het veld dan leeg — verzin niets.
- Meta-description: max 155 tekens. FAQ-antwoorden: kort en concreet.
- GEO/AI-zichtbaarheid: verschijnen we niet in AI-antwoorden, geef dan letterlijke vraag-antwoord-FAQ's die die vragen beantwoorden.

Voor `create_page` denk je als **conversie-copywriter**: een bezoeker komt met concrete intentie binnen en moet binnen enkele seconden kunnen klikken. Lever een **volledige landingspagina** (niet enkel introtekst):
- `h1_title` + `hero_subtitle`: scherpe titel en een emotionele belofte van 1-2 zinnen.
- `why_title` + `why_html`: de echte, emotionele reden om hier te starten (2-3 korte alinea's, eenvoudige HTML).
- `faq`: 4-6 vraag-antwoord-paren, incl. de zoekvraag zelf.
- `closing_title` + `closing_body`: een afsluitende CTA met risico-omkering.
De hero- en afsluit-knop krijgen automatisch de bestaande CTA-link van de homepage; verzin zelf geen knop-URL's.

DATA:
{$summary}

BESCHIKBARE FEITEN (gebruik enkel deze):
{$grounding}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 8000,
                'tools' => [$this->actionsToolSchema()],
                'tool_choice' => ['type' => 'tool', 'name' => 'report_actions'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (! $response->successful()) {
                Log::warning('SEO-acties mislukt', ['status' => $response->status(), 'body' => $response->body()]);

                return [];
            }

            $toolUse = collect($response->json('content', []))->firstWhere('type', 'tool_use');
            $actions = $toolUse['input']['actions'] ?? [];

            return collect($actions)
                ->map(fn ($a) => is_array($a) ? $this->normalizeAction($a) : null)
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('SEO-acties fout', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Compacte feitenlijst tegen hallucinatie: de door de beheerder ingevoerde
     * feiten + de bestaande gepubliceerde pagina's. Domein-modellen (producten,
     * locaties, …) blijven hier bewust buiten — dit is project-agnostisch. Wil
     * je die meevoeden, breid deze methode dan per project uit.
     */
    protected function buildGroundingText(): string
    {
        $lines = [];

        if ($facts = trim((string) Setting::get('ai_facts'))) {
            $lines[] = "Feiten (door de beheerder ingevoerd):\n{$facts}";
        }

        $pages = Page::where('published', true)->orderBy('title')
            ->get(['id', 'slug', 'title', 'meta_description', 'is_homepage', 'created_at', 'updated_at']);
        if ($pages->isNotEmpty()) {
            $recentCutoff = Carbon::now()->subDays(self::RECENT_DAYS);
            $lines[] = "\nBestaande gepubliceerde pagina's (slug — titel — meta?):";
            foreach ($pages as $p) {
                $slug = $p->is_homepage ? '/' : $p->slug;
                $meta = filled($p->meta_description) ? 'meta ✓' : 'GEEN meta-description';
                // De leeftijd hoort erbij: zonder deze markering leest het
                // model "keyword rankt niet" als "actie nodig", ook wanneer
                // de pagina daarvoor vorige week pas gemaakt of herschreven was.
                $recent = '';
                if ($p->created_at?->gte($recentCutoff)) {
                    $recent = ' — NIEUW sinds ' . $p->created_at->format('d/m/Y') . ', nog niet verwerkt door Google';
                } elseif ($p->updated_at?->gte($recentCutoff)) {
                    $recent = ' — RECENT aangepast op ' . $p->updated_at->format('d/m/Y') . ', wijzigingen sijpelen nog door in Google';
                }
                $lines[] = "- {$slug} — {$p->title} — {$meta}{$recent}";
            }
        }

        // Wat er al beantwoord wordt: zonder dit stelt het model week na week
        // vragen voor die (in andere woorden) al op de pagina staan.
        $faqLines = [];
        foreach ($pages as $p) {
            $items = $this->pageFaqItems($p);
            if (! $items) {
                continue;
            }
            $slug = $p->is_homepage ? '/' : $p->slug;
            $count = count($items);
            $full = $count >= self::FAQ_MAX_QUESTIONS ? ' — VOL, stel hier geen add_section meer voor' : '';
            $faqLines[] = "- {$slug} ({$count} " . ($count === 1 ? 'vraag' : 'vragen') . "{$full}):";
            foreach ($items as $item) {
                $answer = Str::limit(trim(strip_tags((string) ($item['answer'] ?? ''))), 120);
                $faqLines[] = "  · \"{$item['question']}\"" . ($answer !== '' ? " — {$answer}" : '');
            }
        }
        if ($faqLines) {
            $lines[] = "\nBestaande FAQ-vragen per pagina (stel deze, of inhoudelijke varianten ervan, NIET opnieuw voor):";
            $lines = array_merge($lines, $faqLines);
        }

        $hp = $this->homepage();
        if ($hp['cta']) {
            $lines[] = "\nHerbruikbare CTA-knop van de homepage (wordt in de hero én de afsluit-CTA gebruikt): \"{$hp['cta']['label']}\" → {$hp['cta']['href']}";
        }
        if (! empty($hp['voice'])) {
            $lines[] = "\nToon & huisstijl (echte tekstfragmenten van de homepage — schrijf in deze stem):";
            foreach ($hp['voice'] as $sample) {
                $lines[] = '- "' . $sample . '"';
            }
        }

        return implode("\n", $lines) ?: 'Geen aanvullende feiten ingevoerd.';
    }

    /**
     * Stabiele sleutel voor de inhoud van een voorstel. Verschillen in
     * hoofdletters, spaties of volgorde mogen niet als "nieuw" tellen.
     *
     * @param  array<int|string,mixed>  $values
     */
    protected function contentKey(array $values): string
    {
        $normalized = collect($values)
            ->map(fn ($v) => preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $v))))
            ->filter()
            ->sort()
            ->values()
            ->all();

        return substr(sha1(implode('|', $normalized)), 0, 12);
    }

    /**
     * Alle FAQ-items (vraag + antwoord) die nu op de pagina staan, over alle
     * FAQ-secties heen.
     *
     * @return array<int,array{question:string,answer:string}>
     */
    protected function pageFaqItems(Page $page): array
    {
        return $page->sections()
            ->where('section_type', 'faq')
            ->orderBy('position')
            ->get()
            ->flatMap(fn ($s) => collect($s->content['items'] ?? [])
                ->map(fn ($item) => [
                    'question' => trim((string) ($item['question'] ?? '')),
                    'answer' => trim((string) ($item['answer'] ?? '')),
                ])
                ->filter(fn ($item) => $item['question'] !== ''))
            ->values()
            ->all();
    }

    /** Is deze pagina minder dan RECENT_DAYS geleden aangemaakt of aangepast? */
    protected function recentlyTouched(Page $page): bool
    {
        $cutoff = Carbon::now()->subDays(self::RECENT_DAYS);

        return (bool) ($page->updated_at?->gte($cutoff) || $page->created_at?->gte($cutoff));
    }

    /** Dekt een bestaande gepubliceerde pagina (titel of slug) dit keyword al? */
    protected function keywordCoveredByExistingPage(string $keyword): bool
    {
        $matcher = new FaqQuestionMatcher();

        return Page::where('published', true)
            ->get(['title', 'slug'])
            ->contains(fn ($p) => $matcher->keywordCoveredBy($keyword, $p->title . ' ' . $p->slug));
    }

    /** Tool-schema voor gestructureerde output. */
    protected function actionsToolSchema(): array
    {
        return [
            'name' => 'report_actions',
            'description' => 'Rapporteer de concrete SEO-verbeteracties in gestructureerde vorm.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'actions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'action_type' => ['type' => 'string', 'enum' => ['create_page', 'add_section', 'optimize_meta']],
                                'priority' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                                'title' => ['type' => 'string', 'description' => 'Korte titel van de actie.'],
                                'problem' => ['type' => 'string', 'description' => 'Het probleem of de kans, 1-2 zinnen.'],
                                'source_keyword' => ['type' => 'string', 'description' => 'Keyword dat dit adresseert; leeg indien n.v.t.'],
                                'target_slug' => ['type' => 'string', 'description' => 'Slug van de bestaande pagina (add_section/optimize_meta). "/" voor de homepage.'],
                                'slug' => ['type' => 'string', 'description' => 'Gewenste slug voor een nieuwe pagina (create_page).'],
                                'h1_title' => ['type' => 'string', 'description' => 'H1 / hero-titel (create_page).'],
                                'hero_subtitle' => ['type' => 'string', 'description' => 'create_page: korte, emotionele belofte onder de hero-titel (1-2 zinnen).'],
                                'why_title' => ['type' => 'string', 'description' => 'create_page: titel van het "waarom"-blok (de emotionele hook).'],
                                'why_html' => ['type' => 'string', 'description' => 'create_page: waarom hier starten — eenvoudige HTML, 2-3 alinea\'s.'],
                                'intro_html' => ['type' => 'string', 'description' => 'Alias voor why_html (create_page).'],
                                'closing_title' => ['type' => 'string', 'description' => 'create_page: titel van de afsluitende CTA-sectie.'],
                                'closing_body' => ['type' => 'string', 'description' => 'create_page: korte tekst boven de afsluitende CTA-knop.'],
                                'meta_title' => ['type' => 'string'],
                                'meta_description' => ['type' => 'string', 'description' => 'Max 155 tekens.'],
                                'faq' => [
                                    'type' => 'array',
                                    'description' => 'Vraag-antwoord-paren (create_page of add_section).',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'question' => ['type' => 'string'],
                                            'answer' => ['type' => 'string'],
                                        ],
                                        'required' => ['question', 'answer'],
                                    ],
                                ],
                            ],
                            'required' => ['action_type', 'priority', 'title', 'problem'],
                        ],
                    ],
                ],
                'required' => ['actions'],
            ],
        ];
    }

    /**
     * Zet één ruwe actie uit het model om naar een SeoActionItem-payload (met
     * opgebouwde content-secties en fingerprint). De sectie-content volgt het
     * builder-contract van dit project (`rich_text` = heading+body, `faq` = items).
     *
     * @return array<string,mixed>|null
     */
    protected function normalizeAction(array $a): ?array
    {
        $type = $a['action_type'] ?? null;
        if (! in_array($type, ['create_page', 'add_section', 'optimize_meta'], true)) {
            return null;
        }

        $priority = in_array($a['priority'] ?? '', ['high', 'medium', 'low'], true) ? $a['priority'] : 'medium';
        $keyword = trim((string) ($a['source_keyword'] ?? '')) ?: null;
        $pageId = null;
        $proposed = [];

        $faq = collect($a['faq'] ?? [])
            ->map(fn ($f) => [
                'question' => trim((string) ($f['question'] ?? '')),
                'answer' => trim((string) ($f['answer'] ?? '')),
            ])
            ->filter(fn ($f) => $f['question'] !== '' && $f['answer'] !== '')
            ->values()
            ->all();

        if ($type === 'create_page') {
            $h1 = trim((string) ($a['h1_title'] ?? $a['title'] ?? ''));
            $sections = $this->buildLandingSections($a, $h1, $faq);
            if (! $sections) {
                return null;
            }
            $slug = trim((string) ($a['slug'] ?? '')) ?: null;
            // Bestaat de pagina al, dan is dit geen nieuwe pagina meer. De
            // fingerprint alleen volstaat hier niet: die kijkt maar een
            // beperkt venster terug, terwijl de pagina blijft bestaan.
            if ($slug && $this->resolvePage($slug)) {
                return null;
            }
            // Dekt een bestaande pagina dit keyword al (titel of slug), dan
            // is een tweede pagina geen verbetering maar kannibalisatie — en
            // vaak is die pagina pas net gemaakt en heeft ze gewoon nog geen
            // tijd gehad om te ranken. De exacte-slug-check hierboven mist
            // dat: een nét andere slug voor hetzelfde keyword glipt er anders
            // week na week opnieuw door.
            if ($keyword && $this->keywordCoveredByExistingPage($keyword)) {
                return null;
            }
            $proposed = array_filter([
                'slug' => $slug,
                'meta_title' => trim((string) ($a['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string) ($a['meta_description'] ?? '')) ?: null,
                'sections' => $sections,
            ], fn ($v) => $v !== null);
            $fpKey = $slug ?: ($h1 !== '' ? $h1 : ($keyword ?: ($a['title'] ?? 'nieuw')));
        } elseif ($type === 'add_section') {
            if (! $faq) {
                return null;
            }
            $page = $this->resolvePage($a['target_slug'] ?? null);
            if (! $page) {
                return null;
            }

            $existing = array_column($this->pageFaqItems($page), 'question');

            // Vol is vol: boven de grens scant het blok niet meer, dus daar
            // valt met bijvullen niets te winnen.
            if (count($existing) >= self::FAQ_MAX_QUESTIONS) {
                return null;
            }

            // Vangnet tegen inhoudelijke herhaling: een vraag die (op een
            // detail na) al op de pagina beantwoord wordt, is geen verbetering
            // — ook al is de formulering net anders. De prompt vraagt het
            // model dit zelf te vermijden; dit filtert wat toch doorglipt.
            $matcher = new FaqQuestionMatcher();
            $faq = array_values(array_filter(
                $faq,
                fn ($f) => $matcher->firstOverlapping($f['question'], $existing) === null
            ));
            if (! $faq) {
                return null;
            }

            // En nooit voorbij de grens duwen: hou enkel zoveel nieuwe vragen
            // over als er nog bij kunnen.
            $faq = array_slice($faq, 0, self::FAQ_MAX_QUESTIONS - count($existing));

            $pageId = $page->id;
            $proposed = [
                'section_type' => 'faq',
                'content' => ['heading' => 'Veelgestelde vragen', 'items' => $faq],
            ];
            // De vragen zelf horen in de sleutel: een tweede FAQ met ándere
            // vragen op dezelfde pagina is een nieuw voorstel, geen duplicaat.
            // Enkel `page-{id}` maakte de dedup permanent: elke pagina had na
            // één voorstel haar FAQ voorgoed gehad.
            $fpKey = 'page-' . $page->id . '|' . $this->contentKey(array_column($faq, 'question'));
        } else { // optimize_meta
            $page = $this->resolvePage($a['target_slug'] ?? null);
            if (! $page) {
                return null;
            }
            $pageId = $page->id;
            $proposed = array_filter([
                'meta_title' => trim((string) ($a['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string) ($a['meta_description'] ?? '')) ?: null,
            ], fn ($v) => $v !== null);
            // Houd enkel over wat écht verandert: een voorstel dat woord voor
            // woord de huidige meta herhaalt is geen verbetering.
            $proposed = array_filter(
                $proposed,
                fn ($v, $field) => trim((string) ($page->{$field} ?? '')) !== $v,
                ARRAY_FILTER_USE_BOTH
            );
            // Cool-down: is de pagina recent aangemaakt of aangepast, dan is
            // het effect daarvan nog nergens meetbaar — een bestaande meta nu
            // alweer herschrijven is churn, geen optimalisatie. Een meta die
            // volledig ONTBREEKT invullen mag wél: daar valt niets af te
            // wachten. (Grof vangnet: updated_at beweegt bij elke bewerking,
            // maar liever even te lang zwijgen dan wekelijks hetzelfde
            // voorstel.)
            if ($this->recentlyTouched($page)) {
                $proposed = array_filter(
                    $proposed,
                    fn ($v, $field) => blank($page->{$field}),
                    ARRAY_FILTER_USE_BOTH
                );
            }
            if (! $proposed) {
                return null;
            }
            // Andere voorgestelde tekst = nieuw voorstel, geen duplicaat.
            $fpKey = 'page-' . $page->id . '|' . $this->contentKey($proposed);
        }

        return [
            'action_type' => $type,
            'priority' => $priority,
            'title' => trim((string) ($a['title'] ?? 'SEO-actie')),
            'problem' => trim((string) ($a['problem'] ?? '')),
            'proposed' => $proposed,
            'page_id' => $pageId,
            'source_keyword' => $keyword,
            'metric' => null,
            'fingerprint' => sha1($type . '|' . $fpKey),
        ];
    }

    /**
     * Bouwt een conversie-gerichte landingspagina uit de AI-velden, met de
     * generieke builder-blokken (hero → rich_text → faq → cta). De hero- en
     * afsluit-CTA hergebruiken de bestaande CTA-knop van de homepage (geen
     * verzonnen URL's); is die er niet, dan blijven die knoppen gewoon weg.
     *
     * Wil je nog rijker (bv. review- of stappen-blokken van de homepage klonen,
     * zoals in bl-members), voeg dat hier per project toe — het hangt af van
     * welke sectietypes dit project heeft.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function buildLandingSections(array $a, string $h1, array $faq): array
    {
        $cta = $this->homepage()['cta'] ?? null; // ['label','href'] of null
        $ctaButton = $cta ? [['label' => $cta['label'], 'href' => $cta['href'], 'variant' => 'primary']] : null;

        $sections = [];

        // 1. Hero — belofte + primaire CTA.
        if ($h1 !== '') {
            $sections[] = ['section_type' => 'hero', 'content' => array_filter([
                'heading' => $h1,
                'subtitle' => trim((string) ($a['hero_subtitle'] ?? '')) ?: null,
                'ctas' => $ctaButton,
            ], fn ($v) => $v !== null)];
        }

        // 2. Waarom — de emotionele hook.
        $whyTitle = trim((string) ($a['why_title'] ?? ''));
        $whyBody = trim((string) ($a['why_html'] ?? $a['intro_html'] ?? ''));
        if ($whyTitle !== '' || $whyBody !== '') {
            $sections[] = ['section_type' => 'rich_text', 'content' => array_filter([
                'heading' => $whyTitle ?: null,
                'body' => $whyBody ?: null,
            ], fn ($v) => $v !== null)];
        }

        // 3. FAQ.
        if ($faq) {
            $sections[] = ['section_type' => 'faq', 'content' => ['heading' => 'Veelgestelde vragen', 'items' => $faq]];
        }

        // 4. Afsluitende CTA met risico-omkering (enkel als er een CTA-link is).
        $closingTitle = trim((string) ($a['closing_title'] ?? ''));
        $closingBody = trim((string) ($a['closing_body'] ?? ''));
        if ($ctaButton && ($closingTitle !== '' || $closingBody !== '')) {
            $sections[] = ['section_type' => 'cta', 'content' => array_filter([
                'heading' => $closingTitle ?: null,
                'intro' => $closingBody ?: null,
                'ctas' => $ctaButton,
            ], fn ($v) => $v !== null)];
        }

        return $sections;
    }

    /**
     * Leidt herbruikbare ankers af uit de homepage: de eerste bruikbare CTA-knop
     * (uit een hero- of cta-sectie) en enkele tekstfragmenten als huisstijl-toon.
     * Alles uit echte, gepubliceerde content — geen hardcoded slugs.
     */
    protected function homepage(): array
    {
        if ($this->homepageBlueprint !== null) {
            return $this->homepageBlueprint;
        }

        $anchors = ['cta' => null, 'voice' => []];
        $page = Page::where('is_homepage', true)->first();

        if ($page) {
            foreach ($page->sections()->orderBy('position')->get() as $s) {
                $c = is_array($s->content) ? $s->content : (array) json_decode((string) $s->content, true);

                if (in_array($s->section_type, ['hero', 'cta'], true)) {
                    foreach ($c['ctas'] ?? [] as $cta) {
                        if (empty($cta['label'])) {
                            continue;
                        }
                        if ($href = $this->resolveCtaHref($cta)) {
                            $anchors['cta'] ??= ['label' => (string) $cta['label'], 'href' => $href];
                            break;
                        }
                    }
                }

                $sample = $s->section_type === 'hero' ? ($c['subtitle'] ?? '') : ($s->section_type === 'cta' ? ($c['intro'] ?? '') : '');
                $sample = trim(strip_tags((string) $sample));
                if ($sample !== '') {
                    $anchors['voice'][] = $sample;
                }
            }
        }

        return $this->homepageBlueprint = $anchors;
    }

    /**
     * Bepaalt de definitieve, herbruikbare bestemming van een CTA uit de
     * page-builder. Robuust tegen beide manieren waarop een project CTA's
     * opslaat:
     *  - de new-website `PageLinkField` ({link_type: 'page', page_id, href}),
     *    waar `href` bij een page-link vaak leeg is omdat Filament het verborgen
     *    veld niet altijd bewaart → dan lossen we de slug live op uit page_id;
     *  - een kale `href`-string (oudere/andere projecten) → die gebruiken we direct.
     *
     * Ankers (#diensten) bestaan enkel op de bronpagina en zijn dus nutteloos
     * als knop op een nieuwe pagina — die geven we niet terug (null).
     *
     * @param  array<string,mixed>  $cta
     */
    protected function resolveCtaHref(array $cta): ?string
    {
        $href = trim((string) ($cta['href'] ?? ''));

        // Page-link zonder betrouwbare href: leid het pad af uit de pagina zelf.
        if ($href === '' && ! empty($cta['page_id']) && ($p = Page::find($cta['page_id']))) {
            $href = $p->is_homepage ? '/' : '/'.$p->slug;
        }

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        return $href;
    }

    /** Zoek een bestaande pagina op slug ("/" of "home" → homepage). */
    protected function resolvePage(?string $slug): ?Page
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return null;
        }
        if ($slug === '/' || strtolower($slug) === 'home') {
            return Page::where('is_homepage', true)->first();
        }

        return Page::where('slug', ltrim($slug, '/'))->first();
    }

    protected function contextToText(array $c): string
    {
        $l = $c['latest'];
        $p = $c['previous'];
        $lines = [];
        $lines[] = "Domein: {$c['target']}";
        if ($l) {
            $etvPrev = $p ? " (vorige: {$p->organic_etv})" : '';
            $kwPrev = $p ? " (vorige: {$p->organic_keywords_count})" : '';
            $lines[] = "Geschat organisch verkeer/maand: {$l->organic_etv}{$etvPrev}";
            $lines[] = "Aantal keywords in Google: {$l->organic_keywords_count}{$kwPrev}";
        }
        $s = $c['stats'];
        $lines[] = "Opgevolgde keywords: {$s['tracked']} | top 3: {$s['top3']} | top 10: {$s['top10']} | gem. positie: " . ($s['avg_position'] ?? 'n/a');
        $lines[] = "AI Overview aanwezig bij {$s['in_ai_overview']} keywords, ons domein geciteerd bij {$s['ai_cited']}.";

        if ($c['up']) {
            $lines[] = "\nGestegen: " . collect($c['up'])->map(fn ($m) => "{$m['keyword']} (+{$m['delta']} → #{$m['rank']})")->implode(', ');
        }
        if ($c['down']) {
            $lines[] = "Gedaald: " . collect($c['down'])->map(fn ($m) => "{$m['keyword']} ({$m['delta']} → #{$m['rank']})")->implode(', ');
        }
        if ($c['opportunities']) {
            $lines[] = "\nKansen (volume maar niet in top 10): " . collect($c['opportunities'])->map(fn ($o) => "{$o['keyword']} (vol {$o['volume']}, " . ($o['rank'] ? "#{$o['rank']}" : 'niet rankend') . ")")->implode(', ');
        }
        if ($c['geo']) {
            $cited = collect($c['geo'])->filter(fn ($g) => $g['cited'])->count();
            $lines[] = "\nGEO: {$cited}/" . count($c['geo']) . " AI-checks citeren ons domein. Vragen: " . collect($c['geo'])->take(5)->map(fn ($g) => "\"{$g['prompt']}\" (" . ($g['cited'] ? 'gelinkt' : ($g['mentioned'] ? 'vermeld' : 'afwezig')) . ")")->implode('; ');
        }

        return implode("\n", $lines);
    }
}
