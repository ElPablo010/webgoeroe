<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SeoGeoCheck;
use App\Models\SeoKeyword;
use App\Models\SeoSiteSnapshot;
use App\Models\Setting;
use App\Support\Url;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zet de verzamelde SEO-cijfers om in een concrete, geprioriteerde
 * Nederlandse actielijst via de Anthropic API (key uit Setting, zoals
 * de bestaande vertaalservices).
 */
class SeoAdvisorService
{
    protected string $model = 'claude-sonnet-5';

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

        $pages = Page::where('published', true)->orderBy('title')->get(['slug', 'title', 'meta_description', 'is_homepage']);
        if ($pages->isNotEmpty()) {
            $lines[] = "\nBestaande gepubliceerde pagina's (slug — titel — meta?):";
            foreach ($pages as $p) {
                $slug = $p->is_homepage ? '/' : $p->slug;
                $meta = filled($p->meta_description) ? 'meta ✓' : 'GEEN meta-description';
                $lines[] = "- {$slug} — {$p->title} — {$meta}";
            }
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
            $pageId = $page->id;
            $proposed = [
                'section_type' => 'faq',
                'content' => ['heading' => 'Veelgestelde vragen', 'items' => $faq],
            ];
            $fpKey = 'page-' . $page->id;
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
            if (! $proposed) {
                return null;
            }
            $fpKey = 'page-' . $page->id;
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
     * Leidt herbruikbare ankers af uit de homepage: de eerste CTA-knop (uit een
     * hero- of cta-sectie) en enkele tekstfragmenten als huisstijl-toon. Alles
     * uit echte, gepubliceerde content — geen hardcoded slugs.
     *
     * Webgoeroe-specifiek: CTA's zijn een PageLinkField ({link_type, page_id,
     * href}). Bij `link_type: 'page'` is `href` leeg — de echte bestemming lossen
     * we live op via {@see Url::resolveCtaHref()}. Homepage-lokale ankers (#…)
     * slaan we over: die bestaan enkel op de homepage en zijn dus een dode link
     * op een nieuwe pagina.
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
                        $href = Url::resolveCtaHref($cta, '');
                        if ($href === '' || str_starts_with($href, '#')) {
                            continue;
                        }
                        $anchors['cta'] ??= ['label' => (string) $cta['label'], 'href' => $href];
                        break;
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
