<?php

namespace App\Services\Seo;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Setting;

/**
 * Bouwt een gegenereerde landingspagina naar het beeld van een bestaande
 * pagina — het "sjabloon" dat je aanwijst op Groei → SEO-instellingen.
 *
 * De reden dat de opbouw uit een pagina komt en niet uit code: elk project
 * heeft een andere conversieflow én andere sectienamen. Door een echte pagina
 * als sjabloon te nemen, bepaalt de beheerder de opbouw in de admin en werkt
 * dezelfde code overal. Wijzig je de flow van je dienstenpagina's, dan volgen
 * nieuwe gegenereerde pagina's vanzelf.
 *
 * Verdeling van verantwoordelijkheid — het sjabloon levert het **skelet**, de
 * AI levert de **inhoud**:
 *
 *   sjabloon : welke secties, in welke volgorde, achtergrond (licht/donker),
 *              anker-id's, hoeveel knoppen per sectie, hun stijl én bestemming,
 *              en structuurknoppen zoals kolomaantal of aantal cases.
 *   AI       : alle zichtbare tekst — inclusief de knoplabels.
 *
 * Dat onderscheid is het hele punt. Kopieerde je ook de knoptekst mee, dan
 * stond er "Ontdek waar je vandaag leads verliest" op een pagina over
 * telefonie. Neem je de bestemming níét mee, dan verlies je de
 * `link_type: page` + `page_id`-vorm en breekt de knop zodra iemand de slug
 * van de doelpagina hernoemt.
 *
 * ---
 *
 * AANPASSEN PER PROJECT — alles wat projectafhankelijk is staat in de twee
 * tabellen hieronder, `ROLES` en `LIST_SHAPE`. Daarbuiten hoef je niets te
 * wijzigen.
 *
 * `ROLES` koppelt een **rol** (wat een blok dóét in de conversieflow) aan de
 * **sectietypes** die die rol in een project kan hebben. Dat is nodig omdat
 * hetzelfde blok overal anders heet: een tekstblok is `rich_text`, `text`,
 * `prose` of `free_text`, afhankelijk van het project. Herkent de blueprint
 * een sectietype niet, dan slaat hij dat blok gewoon over in plaats van het
 * leeg te genereren — voeg de naam dan toe aan de juiste rol.
 *
 * De AI kent enkel de rolnamen; de sectienaam van het project komt uit het
 * sjabloon. Zo blijft de prompt hetzelfde over projecten heen.
 */
class LandingPageBlueprint
{
    /** Setting-sleutel met de slug van de sjabloonpagina. */
    public const SETTING_KEY = 'seo_landing_template_slug';

    /**
     * Rol => sectietypes die die rol kan hebben. De eerste naam is de
     * canonieke (die van de new-website-skill); de rest zijn namen die in
     * bestaande projecten voorkomen.
     *
     * Rollen die hier NIET staan worden overgeslagen. Dat is bewust zo voor
     * blokken die echte gegevens nodig hebben — testimonials/reviews bevatten
     * citaten van echte klanten, en die mag een model niet verzinnen.
     */
    protected const ROLES = [
        'hero' => ['hero'],
        'problems' => ['problem_recognition'],
        'benefits' => ['advantages', 'benefits'],
        'steps' => ['process_steps', 'steps'],
        'cases' => ['cases_grid'],
        'cards' => ['cards', 'tiles_grid', 'tiles'],
        'faq' => ['faq'],
        'cta' => ['cta', 'cta_section'],
        'text' => ['rich_text', 'text', 'prose', 'free_text'],
    ];

    /**
     * Rollen met een herhaalde lijst: onder welke sleutel de builder die lijst
     * verwacht, en welke velden een item heeft. Het model levert de lijst
     * altijd als `items`; hier vertalen we die naar de sleutel die de sectie
     * van dít project gebruikt.
     */
    protected const LIST_SHAPE = [
        'problems' => ['key' => 'problems', 'fields' => ['title', 'icon', 'description', 'tags']],
        'benefits' => ['key' => 'items', 'fields' => ['title', 'icon', 'description']],
        'steps' => ['key' => 'steps', 'fields' => ['title', 'description']],
        'cards' => ['key' => 'cards', 'fields' => ['title', 'subtitle', 'icon', 'description']],
    ];

    /** Leesbare rolnamen voor het goedkeuringsscherm en de prompt. */
    public const ROLE_LABELS = [
        'hero' => 'Hero + CTA',
        'problems' => 'Probleemherkenning',
        'benefits' => 'Voordelen',
        'steps' => 'Werkwijze',
        'cases' => 'Cases',
        'cards' => 'Mogelijkheden',
        'faq' => 'FAQ',
        'cta' => 'Afsluitende CTA',
        'text' => 'Tekst',
    ];

    /**
     * Terugval zonder sjabloonpagina: de generieke opbouw. We nemen de
     * canonieke naam per rol — bestaat die niet in dit project, dan levert de
     * applier gewoon een sectie die de front-end niet rendert, en dat merk je
     * meteen. Wijs dus een sjabloon aan.
     */
    protected const FALLBACK_ROLES = ['hero', 'text', 'faq', 'cta'];

    /** @var array<int,array<string,mixed>>|null */
    protected ?array $skeleton = null;

    /**
     * @param  array{label:string,href:string}|null  $fallbackCta  Knop van de
     *                                                             homepage, gebruikt wanneer het sjabloon zelf geen knop heeft.
     */
    public function __construct(protected ?array $fallbackCta = null) {}

    /** De rol van een sectietype, of null als we er geen inhoud voor kunnen maken. */
    public static function roleFor(string $sectionType): ?string
    {
        foreach (self::ROLES as $role => $types) {
            if (in_array($sectionType, $types, true)) {
                return $role;
            }
        }

        return null;
    }

    /** Leesbaar label voor een sectietype (valt terug op het type zelf). */
    public static function labelFor(string $sectionType): string
    {
        $role = self::roleFor($sectionType);

        return $role ? self::ROLE_LABELS[$role] : $sectionType;
    }

    /** De ingestelde sjabloonpagina, of null wanneer er geen (geldige) is. */
    public function templatePage(): ?Page
    {
        $slug = trim((string) Setting::get(self::SETTING_KEY));
        if ($slug === '') {
            return null;
        }

        return Page::query()
            ->where('slug', ltrim($slug, '/'))
            ->where('published', true)
            ->first();
    }

    /**
     * Het skelet: per sectie het type, de rol, de look-and-feel en de
     * knopstructuur — zonder één woord tekst.
     *
     * @return array<int,array<string,mixed>>
     */
    public function skeleton(): array
    {
        if ($this->skeleton !== null) {
            return $this->skeleton;
        }

        $page = $this->templatePage();

        if (! $page) {
            return $this->skeleton = $this->fallbackSkeleton();
        }

        $skeleton = [];

        foreach ($page->sections()->orderBy('position')->get() as $section) {
            $type = (string) $section->section_type;
            $role = self::roleFor($type);
            if ($role === null) {
                continue;
            }

            $content = is_array($section->content)
                ? $section->content
                : (array) json_decode((string) $section->content, true);

            $entry = ['section_type' => $type, 'role' => $role];

            foreach (['background', 'section_id'] as $key) {
                if (filled($content[$key] ?? null)) {
                    $entry[$key] = $content[$key];
                }
            }

            // Structuurknoppen — vorm, geen inhoud. Onderwerpgebonden filters
            // (filter_tags, filter_industry) nemen we bewust NIET over: die
            // zouden cases van het sjabloononderwerp tonen op een pagina die
            // over iets anders gaat.
            foreach (['columns', 'max_visible', 'limit'] as $key) {
                if (filled($content[$key] ?? null)) {
                    $entry[$key] = $content[$key];
                }
            }

            if ($buttons = $this->buttonSkeleton($content['ctas'] ?? [])) {
                $entry['ctas'] = $buttons;
            }

            // De cases-grid gebruikt `cta` (enkelvoud) voor z'n overzichtslink.
            // Die is niet onderwerpgebonden ("Bekijk alle cases"), dus die
            // nemen we mét label over.
            if ($role === 'cases' && filled($content['cta'] ?? null)) {
                $entry['cta'] = $content['cta'];
            }

            $skeleton[] = $entry;
        }

        return $this->skeleton = $skeleton ?: $this->fallbackSkeleton();
    }

    /** @return array<int,array<string,mixed>> */
    protected function fallbackSkeleton(): array
    {
        return array_map(
            fn (string $role): array => [
                'section_type' => self::ROLES[$role][0],
                'role' => $role,
            ],
            self::FALLBACK_ROLES,
        );
    }

    /** De rollen in volgorde (voor de prompt en de badges). */
    public function roles(): array
    {
        return array_column($this->skeleton(), 'role');
    }

    /**
     * Bouwt de definitieve secties: skelet + AI-inhoud. Secties waarvoor het
     * model niets aanleverde vallen weg — een kortere kloppende pagina is beter
     * dan een volledige met lege blokken.
     *
     * @param  array<string,mixed>  $ai  De ruwe velden van het model.
     * @param  array<int,array{question:string,answer:string}>  $faq
     * @return array<int,array<string,mixed>>
     */
    public function build(array $ai, array $faq): array
    {
        $sections = [];

        foreach ($this->skeleton() as $entry) {
            $content = $this->contentFor($entry, $ai, $faq);
            if ($content === null) {
                continue;
            }

            foreach (['background', 'section_id'] as $key) {
                if (isset($entry[$key])) {
                    $content[$key] = $entry[$key];
                }
            }

            $sections[] = ['section_type' => $entry['section_type'], 'content' => $content];
        }

        return $this->dropDeadAnchors($sections);
    }

    /**
     * Inhoud voor één sectie, of null wanneer het model er niets voor
     * aanleverde.
     *
     * @return array<string,mixed>|null
     */
    protected function contentFor(array $entry, array $ai, array $faq): ?array
    {
        $role = $entry['role'];
        $block = is_array($ai[$role] ?? null) ? $ai[$role] : [];

        return match ($role) {
            'hero' => $this->heroContent($entry, $ai),
            'problems', 'benefits', 'steps', 'cards' => $this->listContent($role, $entry, $block),
            'cases' => $this->casesContent($entry, $block),
            'faq' => $faq ? ['heading' => 'Veelgestelde vragen', 'items' => $faq] : null,
            'cta' => $this->ctaContent($entry, $ai),
            'text' => $this->textContent($ai),
            default => null,
        };
    }

    /** Hero — de belofte plus de knop(pen) uit het sjabloon. */
    protected function heroContent(array $entry, array $ai): ?array
    {
        $heading = $this->text($ai['h1_title'] ?? $ai['title'] ?? '');
        if ($heading === '') {
            return null;
        }

        $labels = array_values(array_filter(array_map(
            fn ($l) => $this->text($l),
            (array) ($ai['hero_cta_labels'] ?? []),
        )));

        return array_filter([
            'heading' => $heading,
            'subtitle' => $this->text($ai['hero_subtitle'] ?? '') ?: null,
            'ctas' => $this->buttons($entry, $labels),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Gemeenschappelijke vorm van probleemherkenning, voordelen, werkwijze en
     * mogelijkheden: kop + intro + een lijst items + afsluitende boodschap.
     * Het model levert de lijst altijd als `items`; de sleutel waaronder de
     * sectie ze verwacht staat in LIST_SHAPE.
     */
    protected function listContent(string $role, array $entry, array $block): ?array
    {
        $shape = self::LIST_SHAPE[$role];
        $heading = $this->text($block['heading'] ?? '');
        $items = $this->items($block['items'] ?? [], $shape['fields']);

        if ($heading === '' || ! $items) {
            return null;
        }

        if ($role === 'cards') {
            $items = array_map(fn (array $c): array => $c + ['media_type' => 'icon'], $items);
        }

        $content = array_filter([
            'eyebrow' => $this->text($block['eyebrow'] ?? '') ?: null,
            'heading' => $heading,
            'intro' => $this->text($block['intro'] ?? '') ?: null,
            'closing' => $this->text($block['closing'] ?? '') ?: null,
            'columns' => $entry['columns'] ?? null,
            'max_visible' => $entry['max_visible'] ?? null,
        ], fn ($v) => $v !== null);

        $content[$shape['key']] = $items;

        // De reis-strip boven de probleemkaarten is puur visueel; laat ze weg
        // wanneer het model ze niet aanleverde.
        if ($role === 'problems' && $journey = $this->items($block['journey'] ?? [], ['label', 'icon'])) {
            $content['journey'] = $journey;
        }

        return $content;
    }

    /**
     * Cases — de items komen live uit de database, dus het model levert enkel
     * de kop. Zonder gepubliceerde cases laten we het blok weg in plaats van
     * een lege grid te tonen. Heeft dit project geen cases-model, dan valt de
     * sectie eveneens weg.
     */
    protected function casesContent(array $entry, array $block): ?array
    {
        $heading = $this->text($block['heading'] ?? '');
        if ($heading === '' || ! $this->hasPublishedCases()) {
            return null;
        }

        return array_filter([
            'eyebrow' => $this->text($block['eyebrow'] ?? '') ?: null,
            'heading' => $heading,
            'intro' => $this->text($block['intro'] ?? '') ?: null,
            'limit' => $entry['limit'] ?? null,
            'cta' => $entry['cta'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** Niet elk project heeft een cases-model; dan is er niets te tonen. */
    protected function hasPublishedCases(): bool
    {
        return class_exists(CaseStudy::class) && CaseStudy::where('published', true)->exists();
    }

    /** Afsluitende CTA met risico-omkering. */
    protected function ctaContent(array $entry, array $ai): ?array
    {
        $heading = $this->text($ai['closing_title'] ?? '');
        $intro = $this->text($ai['closing_body'] ?? '');

        if ($heading === '' && $intro === '') {
            return null;
        }

        $label = $this->text($ai['closing_cta_label'] ?? '');
        $buttons = $this->buttons($entry, $label !== '' ? [$label] : []);

        if (! $buttons) {
            return null;
        }

        return array_filter([
            'heading' => $heading ?: null,
            'intro' => $intro ?: null,
            'ctas' => $buttons,
        ], fn ($v) => $v !== null);
    }

    /** Generieke tekstsectie — de terugval wanneer er geen sjabloon is. */
    protected function textContent(array $ai): ?array
    {
        $heading = $this->text($ai['why_title'] ?? '');
        $body = $this->text($ai['why_html'] ?? $ai['intro_html'] ?? '');

        if ($heading === '' && $body === '') {
            return null;
        }

        return array_filter([
            'heading' => $heading ?: null,
            'body' => $body ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Haalt de knopstructuur uit een sjabloonsectie: stijl en bestemming
     * blijven, het label niet. De `link_type`/`page_id`-vorm houden we intact
     * zodat de knop blijft werken als de doelpagina later hernoemd wordt.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function buttonSkeleton(mixed $ctas): array
    {
        $out = [];

        foreach ((array) $ctas as $cta) {
            if (! is_array($cta)) {
                continue;
            }

            $button = array_filter([
                'variant' => $cta['variant'] ?? 'primary',
                'link_type' => $cta['link_type'] ?? null,
                'page_id' => $cta['page_id'] ?? null,
                'href' => $cta['href'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            // Een knop zonder bestemming kunnen we niet overnemen.
            if (! isset($button['page_id']) && ! isset($button['href'])) {
                continue;
            }

            $button['fallback_label'] = trim((string) ($cta['label'] ?? ''));
            $out[] = $button;
        }

        return $out;
    }

    /**
     * Zet het knopskelet om naar echte knoppen, met de AI-labels erop. Levert
     * het model minder labels dan er knoppen zijn, dan valt de knop terug op
     * het sjabloonlabel — dat is beter dan een knop zonder tekst.
     *
     * @param  array<int,string>  $labels
     * @return array<int,array<string,mixed>>
     */
    protected function buttons(array $entry, array $labels): array
    {
        $skeleton = $entry['ctas'] ?? [];

        // Geen knop in het sjabloon? Val terug op die van de homepage, zodat
        // een pagina nooit zonder call-to-action eindigt.
        if (! $skeleton && $this->fallbackCta) {
            $skeleton = [[
                'variant' => 'primary',
                'href' => $this->fallbackCta['href'],
                'fallback_label' => $this->fallbackCta['label'],
            ]];
        }

        $out = [];

        foreach ($skeleton as $i => $button) {
            $label = $labels[$i] ?? '';
            if ($label === '') {
                $label = (string) ($button['fallback_label'] ?? '');
            }
            if ($label === '') {
                continue;
            }

            unset($button['fallback_label']);
            $out[] = ['label' => $label] + $button;
        }

        return $out;
    }

    /**
     * Verwijdert ankerknoppen (#aanpak) die nergens heen scrollen omdat de
     * sectie met dat id niet in de gegenereerde pagina zit — bijvoorbeeld
     * omdat het model er geen inhoud voor aanleverde.
     *
     * @param  array<int,array<string,mixed>>  $sections
     * @return array<int,array<string,mixed>>
     */
    protected function dropDeadAnchors(array $sections): array
    {
        $ids = array_filter(array_column(array_column($sections, 'content'), 'section_id'));

        foreach ($sections as $i => $section) {
            $ctas = $section['content']['ctas'] ?? null;
            if (! is_array($ctas)) {
                continue;
            }

            $kept = array_values(array_filter($ctas, function (array $cta) use ($ids): bool {
                $href = (string) ($cta['href'] ?? '');

                return ! str_starts_with($href, '#') || in_array(ltrim($href, '#'), $ids, true);
            }));

            if ($kept) {
                $sections[$i]['content']['ctas'] = $kept;
            } else {
                unset($sections[$i]['content']['ctas']);
            }
        }

        return array_values($sections);
    }

    /**
     * Normaliseert een repeater-lijst van het model: enkel de velden die de
     * builder kent, en enkel items met een gevulde eerste kolom.
     *
     * @param  array<int,string>  $fields
     * @return array<int,array<string,mixed>>
     */
    protected function items(mixed $raw, array $fields): array
    {
        $required = $fields[0];
        $out = [];

        foreach ((array) $raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $clean = [];
            foreach ($fields as $field) {
                $value = $item[$field] ?? null;

                if ($field === 'tags') {
                    $tags = array_values(array_filter(array_map(
                        fn ($t) => $this->text($t),
                        (array) $value,
                    )));
                    if ($tags) {
                        $clean['tags'] = $tags;
                    }

                    continue;
                }

                if (($text = $this->text($value)) !== '') {
                    $clean[$field] = $text;
                }
            }

            if (($clean[$required] ?? '') !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    protected function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
