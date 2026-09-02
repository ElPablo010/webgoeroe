<?php

namespace Database\Seeders;

use App\Http\Middleware\HandleRedirects;
use App\Models\CaseStudy;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Restructures the services into exactly four benefit-first pages:
 *
 *   /websites-conversie   (was /websites-en-leadgeneratie, same page row)
 *   /leadgeneratie        (new)
 *   /sales-automation     (was /ai-telefoonassistenten, same page row)
 *   /business-automation  (kept)
 *
 * Every page follows the same 8-section persuasion flow:
 * hero → problem recognition → benefits → approach → cases → concrete
 * possibilities → FAQ → final CTA (always the Bottleneck Scan).
 *
 * Idempotent: renamed pages are matched on old OR new slug, sections are
 * rebuilt, redirects/menu items are upserted, case tags are merged.
 */
class ServicesRestructureSeeder extends Seeder
{
    public function run(): void
    {
        $scanId = Page::where('slug', 'bottleneck-scan')->value('id');

        $websites = $this->upsertPage(['websites-en-leadgeneratie', 'websites-conversie'], [
            'slug'             => 'websites-conversie',
            'title'            => 'Websites & Conversie',
            'meta_title'       => 'Websites & Conversie: meer leads en klanten uit je website | De Webgoeroe',
            'meta_description' => 'Je website krijgt bezoekers, maar te weinig aanvragen? Wij maken van je website een bron van leads en klanten: duidelijke propositie, betere conversie, meetbaar resultaat.',
        ]);
        $leadgen = $this->upsertPage(['leadgeneratie'], [
            'slug'             => 'leadgeneratie',
            'title'            => 'Leadgeneratie',
            'meta_title'       => 'Leadgeneratie: meer van de juiste potentiële klanten | De Webgoeroe',
            'meta_description' => 'Te weinig nieuwe aanvragen of te afhankelijk van mond-tot-mondreclame? Wij zorgen voor een voorspelbare instroom van de juiste potentiële klanten, meetbaar en met controle over je budget.',
        ]);
        $sales = $this->upsertPage(['ai-telefoonassistenten', 'sales-automation'], [
            'slug'             => 'sales-automation',
            'title'            => 'Sales Automation',
            'meta_title'       => 'Sales Automation: haal meer uit iedere lead | De Webgoeroe',
            'meta_description' => 'Leads die te laat opgevolgd worden, gemiste oproepen, offertes zonder follow-up? Wij automatiseren je opvolging zodat je meer afspraken en klanten haalt uit dezelfde instroom.',
        ]);
        $business = $this->upsertPage(['business-automation'], [
            'slug'             => 'business-automation',
            'title'            => 'Business Automation',
            'meta_title'       => 'Business Automation: minder manueel werk, slimmere processen | De Webgoeroe',
            'meta_description' => 'Repetitieve administratie, dubbele invoer, systemen die niet communiceren? Wij automatiseren je processen zodat je tijd bespaart, minder fouten maakt en meer overzicht krijgt.',
        ]);

        $this->rebuildSections($websites, $this->websitesSections($scanId));
        $this->rebuildSections($leadgen, $this->leadgenSections($scanId));
        $this->rebuildSections($sales, $this->salesSections($scanId));
        $this->rebuildSections($business, $this->businessSections($scanId));

        $this->tagCases();
        $this->updateMenu($websites, $leadgen, $sales, $business);
        $this->updateRedirects();
        $this->updateHomepageServiceCards($websites, $leadgen, $sales, $business);
        $this->updateFooterTagline();

        Cache::forget(HandleRedirects::CACHE_KEY);

        $this->command?->info('Diensten geherstructureerd naar 4 pagina\'s: '.implode(', ', [
            '/'.$websites->slug, '/'.$leadgen->slug, '/'.$sales->slug, '/'.$business->slug,
        ]));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** @param  array<int, string>  $slugs  old and/or new slugs that may identify the row */
    private function upsertPage(array $slugs, array $attributes): Page
    {
        $page = Page::where('locale', 'nl')->whereIn('slug', $slugs)->orderByRaw(
            'FIELD(slug, '.implode(',', array_fill(0, count($slugs), '?')).')', $slugs
        )->first() ?? new Page(['locale' => 'nl']);

        $page->fill($attributes + [
            'is_homepage' => false,
            'published'   => true,
            'meta_robots' => $page->meta_robots ?? 'index, follow',
        ]);
        $page->locale = 'nl';
        $page->save();

        return $page;
    }

    /** @param  array<int, array{section_type: string, content: array}>  $sections */
    private function rebuildSections(Page $page, array $sections): void
    {
        $page->sections()->delete();

        foreach ($sections as $position => $section) {
            $page->sections()->create([
                'section_type' => $section['section_type'],
                'position'     => $position,
                'locale'       => 'nl',
                'content'      => $section['content'],
            ]);
        }
    }

    private function scanCta(?int $scanId, string $label): array
    {
        return $scanId
            ? ['label' => $label, 'variant' => 'primary', 'link_type' => 'page', 'page_id' => $scanId]
            : ['label' => $label, 'variant' => 'primary', 'link_type' => 'url', 'href' => '/bottleneck-scan'];
    }

    private function hero(string $eyebrow, string $heading, string $subtitle, array $primaryCta): array
    {
        return ['section_type' => 'hero', 'content' => [
            'section_id' => null,
            'eyebrow'    => $eyebrow,
            'heading'    => $heading,
            'subtitle'   => $subtitle,
            'image'      => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
            'ctas'       => [
                $primaryCta,
                ['label' => 'Bekijk onze aanpak', 'variant' => 'ghost', 'link_type' => 'url', 'href' => '#aanpak'],
            ],
        ]];
    }

    private function problems(string $heading, string $intro, array $problems, string $closing, array $journey = []): array
    {
        return ['section_type' => 'problem_recognition', 'content' => [
            'section_id' => null,
            'background' => 'dark',
            'eyebrow'    => 'Herken je dit?',
            'heading'    => $heading,
            'intro'      => $intro,
            'journey'    => $journey,
            'problems'   => array_map(fn (array $p) => [
                'icon' => $p[0], 'title' => $p[1], 'description' => $p[2], 'tags' => $p[3],
            ], $problems),
            'closing'    => $closing,
            'cta_label'  => null,
            'link_type'  => null,
            'page_id'    => null,
        ]];
    }

    private function benefits(string $heading, ?string $intro, array $items, string $closing): array
    {
        return ['section_type' => 'advantages', 'content' => [
            'section_id' => null,
            'background' => 'light',
            'eyebrow'    => 'Wat het oplevert',
            'heading'    => $heading,
            'intro'      => $intro,
            'items'      => array_map(fn (array $i) => ['icon' => $i[0], 'title' => $i[1], 'description' => $i[2]], $items),
            'closing'    => $closing,
            'cta_label'  => null,
            'link_type'  => null,
            'page_id'    => null,
        ]];
    }

    private function approach(string $heading, ?string $intro, array $steps, string $closing): array
    {
        return ['section_type' => 'process_steps', 'content' => [
            'section_id' => 'aanpak',
            'background' => 'dark',
            'eyebrow'    => 'Onze aanpak',
            'heading'    => $heading,
            'intro'      => $intro,
            'steps'      => array_map(fn (array $s) => ['title' => $s[0], 'description' => $s[1]], $steps),
            'closing'    => $closing,
            'cta_label'  => null,
            'link_type'  => null,
            'page_id'    => null,
        ]];
    }

    private function cases(string $heading, string $intro, string $tag): array
    {
        return ['section_type' => 'cases_grid', 'content' => [
            'section_id'      => null,
            'background'      => 'light',
            'eyebrow'         => 'Cases & resultaten',
            'heading'         => $heading,
            'intro'           => $intro,
            'filter_industry' => null,
            'filter_tags'     => [$tag],
            'limit'           => 3,
            'cta'             => [
                ['label' => 'Bekijk alle cases', 'variant' => 'secondary', 'link_type' => 'url', 'href' => '/cases'],
            ],
        ]];
    }

    private function possibilities(string $heading, string $intro, array $cards): array
    {
        return ['section_type' => 'cards', 'content' => [
            'section_id'  => 'mogelijkheden',
            'background'  => 'dark',
            'eyebrow'     => 'Concrete mogelijkheden',
            'heading'     => $heading,
            'intro'       => $intro,
            'columns'     => 3,
            'max_visible' => null,
            'cards'       => array_map(fn (array $c) => [
                'media_type'  => 'icon',
                'icon'        => $c[0],
                'title'       => $c[1],
                'subtitle'    => $c[2],
                'description' => $c[3],
                'features'    => [],
                'cta_label'   => null,
                'link_type'   => null,
                'page_id'     => null,
            ], $cards),
        ]];
    }

    private function faq(string $heading, array $items): array
    {
        return ['section_type' => 'faq', 'content' => [
            'section_id' => 'faq',
            'background' => 'light',
            'eyebrow'    => 'Veelgestelde vragen',
            'heading'    => $heading,
            'intro'      => null,
            'items'      => array_map(fn (array $i) => ['question' => $i[0], 'answer' => $i[1]], $items),
        ]];
    }

    private function finalCta(string $heading, string $intro, ?int $scanId): array
    {
        return ['section_type' => 'cta', 'content' => [
            'section_id' => null,
            'background' => null,
            'eyebrow'    => 'Bottleneck Scan · Gratis · ± 30 minuten',
            'heading'    => $heading,
            'intro'      => $intro,
            'ctas'       => [$this->scanCta($scanId, 'Plan je Bottleneck Scan')],
            'note'       => 'Vrijblijvend · Geen voorbereiding nodig · Concrete volgende stap',
        ]];
    }

    // ---------------------------------------------------------------------
    // 1. Websites & Conversie
    // ---------------------------------------------------------------------

    private function websitesSections(?int $scanId): array
    {
        return [
            $this->hero(
                'Websites & Conversie',
                'Maak van je website een bron van leads en klanten.',
                '<p>Je website krijgt bezoekers. De vraag is hoeveel daarvan contact opnemen, een offerte vragen of iets boeken. Wij zorgen dat meer van je bestaande bezoekers de volgende stap zetten, en dat je dat resultaat ook ziet.</p>',
                $this->scanCta($scanId, 'Ontdek hoeveel kansen je website laat liggen'),
            ),

            $this->problems(
                'Bezoekers genoeg, aanvragen te weinig',
                '<p>Veel websites zien er prima uit, maar leveren commercieel weinig op. Meestal zit het probleem niet in het design, maar in wat er met een bezoeker gebeurt zodra hij op je site komt.</p>',
                [
                    ['mouse-pointer-click', 'Verkeer, maar geen aanvragen', 'Je ziet bezoekers in je statistieken, maar de telefoon gaat niet en het contactformulier blijft stil.', ['Conversie', 'Call-to-action']],
                    ['signpost', 'Bezoekers weten niet wat de volgende stap is', 'Je aanbod is niet in één oogopslag duidelijk, en nergens staat wat een bezoeker nu moet doen.', ['Propositie', 'Customer journey']],
                    ['megaphone', 'Advertentieverkeer dat niet oplevert', 'Je betaalt voor klikken uit Google of Meta, maar de pagina waar ze landen zet die klikken niet om in aanvragen.', ['Landingpagina', 'Advertenties']],
                    ['eye-off', 'Geen zicht op wat werkt', 'Je weet niet welke pagina\'s aanvragen opleveren, waar bezoekers afhaken of hoe je site het doet op mobiel.', ['Conversietracking', 'Mobiel']],
                ],
                '<p>Elk van deze punten kost je klanten die er eigenlijk al waren. <strong>Ze zaten op je website.</strong></p>',
            ),

            $this->benefits(
                'Dezelfde bezoekers, meer klanten',
                '<p>Een website die converteert, hoeft niet meer bezoekers te krijgen om meer op te leveren. Dit verandert er wanneer de basis goed zit.</p>',
                [
                    ['trending-up', 'Meer aanvragen uit je huidige verkeer', 'Bezoekers die vandaag afhaken, nemen wel contact op. Zonder extra advertentiebudget.'],
                    ['route', 'Een duidelijke route voor elke bezoeker', 'Iemand die op je site komt, begrijpt meteen wat je doet, voor wie, en wat de volgende stap is.'],
                    ['coins', 'Meer rendement uit je marketing', 'Elke euro die je in verkeer investeert, levert meer op omdat de pagina erachter haar werk doet.'],
                    ['chart-line', 'Resultaten die je kan meten', 'Je ziet welke pagina\'s aanvragen opleveren en waar je nog kan verbeteren. Beslissen op cijfers, niet op gevoel.'],
                ],
                '<p>Je website wordt zo een onderdeel van je groei, in plaats van een kostenpost die er "moet zijn".</p>',
            ),

            $this->approach(
                'In drie stappen naar een website die klanten oplevert',
                null,
                [
                    ['We analyseren waar bezoekers afhaken', 'We bekijken je huidige website, je verkeer en je doelgroep: wat komt iemand zoeken, en waar loopt het vast tussen aankomen en aanvragen?'],
                    ['We bouwen of verbeteren wat het meeste oplevert', 'Soms is dat een nieuwe website, vaker een scherpere propositie, betere pagina\'s of een duidelijke volgende stap. We beginnen bij de grootste winst.'],
                    ['We meten en optimaliseren verder', 'Na livegang volgen we de conversies op en verbeteren we gericht wat de cijfers aangeven.'],
                ],
                '<p><strong>Geen herbouw om te herbouwen.</strong> Als je huidige website een goede basis is, vertrekken we daarvan.</p>',
            ),

            $this->cases(
                'Websites die aantoonbaar meer opleveren',
                '<p>Echte projecten, echte cijfers. Bekijk hoe deze bedrijven meer boekingen, bestellingen en aanvragen uit hun website halen.</p>',
                'Websites & Conversie',
            ),

            $this->possibilities(
                'Wat we inzetten om je website te laten renderen',
                '<p>Welke van deze we inzetten, hangt af van waar jouw website vandaag kansen laat liggen. Nooit alles tegelijk, altijd wat het verschil maakt.</p>',
                [
                    ['layout', 'Websites op maat', 'Nieuw of herbouwd', 'Een website die vertrekt van je aanbod en je klant, gebouwd om aanvragen op te leveren en makkelijk zelf te beheren.'],
                    ['mouse-pointer-click', 'Landingpagina\'s & funnels', 'Eén boodschap per pagina', 'Gerichte pagina\'s voor een specifiek aanbod of campagne, zodat bezoekers meteen zien wat voor hen relevant is.'],
                    ['sliders-horizontal', 'Conversie-optimalisatie (CRO)', 'Verbeteren wat er al is', 'We analyseren waar bezoekers afhaken, testen verbeteringen en blijven optimaliseren na livegang. Onderhoud inbegrepen.'],
                    ['pen-tool', 'UX & copy', 'Duidelijk en overtuigend', 'Structuur en teksten die je aanbod helder maken en bezoekers naar de volgende stap leiden.'],
                    ['chart-bar', 'Analytics & conversietracking', 'Weten wat werkt', 'Correcte meting van aanvragen, telefoontjes en boekingen, zodat je ziet welke pagina\'s en kanalen renderen.'],
                    ['search', 'Technische SEO', 'Gevonden worden, snel laden', 'Een technisch gezonde, snelle website die zoekmachines goed kunnen lezen. De basis voor organische bezoekers.'],
                ],
            ),

            $this->faq('Twijfels over je website?', [
                ['Heb ik een nieuwe website nodig, of kan mijn huidige beter?', '<p>Dat bekijken we eerst. Vaak zit de grootste winst in een scherpere propositie, betere pagina\'s of een duidelijke volgende stap op je bestaande site. Een volledige herbouw doen we alleen als de basis technisch of structureel niet meer volstaat.</p>'],
                ['Wat kost een website die converteert?', '<p>Dat hangt af van wat er nodig is: een optimalisatie van je huidige site kost iets anders dan een volledig nieuwe website met meerdere pagina\'s. Na een eerste analyse krijg je een concreet voorstel met een vaste prijs, geen verrassingen achteraf.</p>'],
                ['Hoe lang duurt het?', '<p>Gerichte verbeteringen staan meestal binnen enkele weken live. Een nieuwe website duurt doorgaans drie tot zes weken, afhankelijk van de omvang en hoe snel je feedback geeft.</p>'],
                ['Wanneer zie ik resultaat?', '<p>Conversieverbeteringen zijn snel meetbaar: vanaf de eerste weken zie je of meer bezoekers de volgende stap zetten. Organische groei via SEO vraagt meer tijd, meestal enkele maanden.</p>'],
                ['Kan ik de website zelf beheren?', '<p>Ja. Je past teksten, afbeeldingen en pagina\'s zelf aan via een eenvoudige beheeromgeving, zonder technische kennis. Wil je dat liever niet zelf doen, dan volgen wij het op.</p>'],
                ['Doen jullie ook advertenties?', '<p>Ja, maar dat is een aparte dienst. Op deze pagina gaat het over wat er gebeurt zodra iemand op je website komt. Wil je meer van de juiste bezoekers aantrekken, kijk dan bij <a href="/leadgeneratie">Leadgeneratie</a>.</p>'],
            ]),

            $this->finalCta(
                'Ontdek hoeveel kansen je website laat liggen',
                '<p>In een Bottleneck Scan van 30 minuten bekijken we samen je website en je cijfers. Je hoort waar bezoekers afhaken, wat dat je kost en wat de meest logische volgende stap is.</p>',
                $scanId,
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // 2. Leadgeneratie
    // ---------------------------------------------------------------------

    private function leadgenSections(?int $scanId): array
    {
        return [
            $this->hero(
                'Leadgeneratie',
                'Meer van de juiste potentiële klanten bereiken.',
                '<p>Niet zoveel mogelijk bezoekers, maar mensen die echt op zoek zijn naar wat jij aanbiedt. Wij zorgen voor een voorspelbare instroom van relevante aanvragen, en maken meetbaar wat elke lead je kost en oplevert.</p>',
                $this->scanCta($scanId, 'Ontdek waar jouw leadgeneratie kan groeien'),
            ),

            $this->problems(
                'Nieuwe klanten komen te toevallig binnen',
                '<p>Groeien is lastig als je niet weet waar je volgende klant vandaan komt. Dit zijn de signalen die we het vaakst zien.</p>',
                [
                    ['message-circle', 'Afhankelijk van mond-tot-mondreclame', 'Nieuwe klanten komen via je netwerk. Fijn, maar je kan het niet sturen en niet opschalen.', ['Instroom', 'Voorspelbaarheid']],
                    ['users', 'Te weinig relevante aanvragen', 'Er komen wel aanvragen binnen, maar te weinig, of van mensen die niet bij je aanbod passen.', ['Doelgroep', 'Kwaliteit']],
                    ['badge-euro', 'Campagnes die geld kosten maar weinig opleveren', 'Je hebt al advertenties geprobeerd. Er kwamen klikken, maar geen klanten, en je weet niet goed waarom.', ['Advertenties', 'Rendement']],
                    ['eye-off', 'Geen zicht op kost per lead', 'Je weet niet wat een nieuwe klant je kost via welk kanaal, dus ook niet waar je meer in moet investeren.', ['Tracking', 'Attributie']],
                ],
                '<p>Het resultaat: een instroom die schommelt en groei die je niet kan plannen. <strong>Dat hoeft niet.</strong></p>',
            ),

            $this->benefits(
                'Een instroom die je kan voorspellen en sturen',
                null,
                [
                    ['target', 'Meer relevante potentiële klanten', 'Je bereikt mensen die actief zoeken naar wat jij doet, in jouw regio of niche. Minder ruis, betere gesprekken.'],
                    ['calendar-check', 'Voorspelbaardere instroom', 'Je weet ongeveer hoeveel aanvragen een maand oplevert, en kan daarop plannen: personeel, voorraad, capaciteit.'],
                    ['chart-line', 'Meetbare acquisitie', 'Je ziet wat een lead kost per kanaal en welke campagnes klanten opleveren, niet alleen klikken.'],
                    ['sliders-horizontal', 'Meer controle over je groei', 'Meer klanten nodig? Dan draai je de kraan verder open. Even vol? Dan schaal je terug. Jij bepaalt het tempo.'],
                ],
                '<p>Leadgeneratie draait voor ons niet om zoveel mogelijk verkeer, maar om <strong>de juiste mensen aantrekken en dat resultaat meetbaar maken</strong>.</p>',
            ),

            $this->approach(
                'Strategie, uitvoering en optimalisatie',
                null,
                [
                    ['We bepalen wie je wil bereiken en met welk aanbod', 'Welke klant wil je meer? Waar zoekt die, en welk aanbod overtuigt hem om contact op te nemen? Daar begint elke campagne.'],
                    ['We zetten de campagnes en pagina\'s op', 'We bouwen de campagnes, de landingpagina\'s en de meting, zodat elke aanvraag traceerbaar is naar zijn bron.'],
                    ['We meten en optimaliseren', 'We volgen kost per lead en kwaliteit van de aanvragen op, en sturen bij: budget, doelgroep, boodschap en pagina.'],
                ],
                '<p>Advertenties werken alleen als de pagina erachter haar werk doet. Daarom kijken we altijd naar <strong>campagne én bestemming</strong> samen.</p>',
            ),

            $this->cases(
                'Meer bereik, meer aanvragen',
                '<p>Bekijk hoe deze bedrijven meer relevante bezoekers en aanvragen aantrekken.</p>',
                'Leadgeneratie',
            ),

            $this->possibilities(
                'Wat we inzetten om de juiste klanten te bereiken',
                '<p>Welke kanalen we inzetten, hangt af van waar jouw klant zoekt en wat je budget toelaat. Dit is de gereedschapskist.</p>',
                [
                    ['target', 'Campagnestrategie, doelgroep & aanbod', 'De basis', 'Wie wil je bereiken, wat bied je aan en welke boodschap overtuigt? Zonder dit antwoord verbrandt elk kanaal budget.'],
                    ['megaphone', 'Meta Ads', 'Facebook & Instagram', 'Gerichte campagnes op basis van interesses, gedrag en regio. Sterk om vraag te creëren bij mensen die je nog niet kennen.'],
                    ['search', 'Google Ads', 'Zoekadvertenties', 'Zichtbaar zijn op het moment dat iemand actief zoekt naar jouw dienst. Hoge intentie, direct meetbaar.'],
                    ['mouse-pointer-click', 'Campagnefunnels & landingpagina\'s', 'Van klik naar aanvraag', 'Eén pagina per campagne, afgestemd op de advertentie, zodat bezoekers meteen herkennen wat ze zochten en sneller reageren.'],
                    ['file-text', 'SEO & content', 'Waar relevant', 'Organisch gevonden worden op de zoektermen die klanten opleveren. Trager dan advertenties, maar duurzaam.'],
                    ['chart-bar', 'Tracking, attributie & A/B-testing', 'Meten en verbeteren', 'Elke aanvraag gekoppeld aan haar bron, en systematisch testen wat beter werkt: boodschap, doelgroep of pagina.'],
                ],
            ),

            $this->faq('Twijfels over leadgeneratie?', [
                ['Wat is een realistisch budget?', '<p>Dat hangt af van je sector, je regio en wat een klant je oplevert. Voor betaalde campagnes rekenen we doorgaans op een mediabudget vanaf enkele honderden euro per maand, los van onze begeleiding. In de Bottleneck Scan geven we een eerlijke inschatting van wat voor jou zinvol is.</p>'],
                ['Hoe snel zie ik resultaat?', '<p>Betaalde campagnes leveren doorgaans binnen enkele weken de eerste aanvragen op. De eerste maand gebruiken we vooral om te leren welke doelgroep, boodschap en pagina het beste werken. Organische groei via SEO en content vraagt enkele maanden.</p>'],
                ['Ik heb al advertenties geprobeerd en het werkte niet. Waarom nu wel?', '<p>Meestal lag het niet aan het kanaal, maar aan de combinatie: een te brede doelgroep, een boodschap die niet onderscheidt, of een pagina die de klik niet omzet. We bekijken eerst wat er toen misging voor we opnieuw budget inzetten.</p>'],
                ['Werkt dit ook in mijn sector?', '<p>Als je klanten online zoeken of te bereiken zijn, in principe wel. De aanpak verschilt: een lokale vakman heeft andere kanalen nodig dan een B2B-dienstverlener. Twijfel je? Dan zeggen we dat eerlijk tijdens de scan.</p>'],
                ['Wat als mijn website de leads niet omzet?', '<p>Dan pakken we dat eerst aan. Advertentiebudget sturen naar een pagina die niet converteert, is weggegooid geld. Kijk daarvoor bij <a href="/websites-conversie">Websites & Conversie</a>.</p>'],
            ]),

            $this->finalCta(
                'Ontdek waar jouw leadgeneratie kan groeien',
                '<p>In een Bottleneck Scan van 30 minuten bekijken we hoe je vandaag klanten aantrekt, waar de instroom stokt en welk kanaal voor jou de meeste kans op resultaat biedt.</p>',
                $scanId,
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // 3. Sales Automation
    // ---------------------------------------------------------------------

    private function salesSections(?int $scanId): array
    {
        return [
            $this->hero(
                'Sales Automation',
                'Haal meer uit iedere lead.',
                '<p>Elke aanvraag, oproep of offerte is een kans die je al betaald hebt. Wij zorgen dat je sneller reageert, consequent opvolgt en geen opportuniteiten meer laat liggen. Zonder dat jij of je team meer uren in opvolging moet steken.</p>',
                $this->scanCta($scanId, 'Ontdek waar je vandaag leads verliest'),
            ),

            $this->problems(
                'Leads komen binnen. Klanten worden het niet.',
                '<p>Het probleem zit zelden in te weinig leads, maar in wat ermee gebeurt na het eerste contact. Op elke overgang hieronder kan een lead verloren gaan.</p>',
                [
                    ['timer', 'Leads worden te laat gecontacteerd', 'Een aanvraag van vrijdagavond krijgt maandag antwoord. Tegen dan heeft de klant al met een concurrent gesproken.', ['Speed-to-lead']],
                    ['phone-missed', 'Gemiste oproepen', 'Je staat bij een klant of op de werf. De telefoon gaat, je kan niet opnemen, en de beller probeert de volgende in de lijst.', ['Bereikbaarheid']],
                    ['shuffle', 'Opvolging hangt af van wie er tijd heeft', 'Leads zitten in mailboxen en spreadsheets. Wie volgt wat op? Afspraken worden niet consequent geboekt, offertes niet nagebeld.', ['Opvolging', 'Offertes']],
                    ['repeat', 'Verkopers verliezen tijd aan repetitief werk', 'Herinneringen sturen, gegevens overtypen, afspraken plannen: uren per week die niet naar verkopen gaan.', ['Repetitief werk']],
                ],
                '<p>Elke lead die stilvalt, is omzet die je al betaald hebt met marketing, tijd of reputatie. <strong>Die winst ligt voor het rapen.</strong></p>',
                [
                    ['icon' => 'inbox', 'label' => 'Lead'],
                    ['icon' => 'phone-call', 'label' => 'Opvolging'],
                    ['icon' => 'filter', 'label' => 'Kwalificatie'],
                    ['icon' => 'calendar-check', 'label' => 'Afspraak'],
                    ['icon' => 'repeat', 'label' => 'Follow-up'],
                    ['icon' => 'handshake', 'label' => 'Klant'],
                ],
            ),

            $this->benefits(
                'Meer afspraken en klanten uit dezelfde instroom',
                null,
                [
                    ['zap', 'Sneller reageren, altijd', 'Elke lead krijgt binnen minuten een reactie, ook \'s avonds en in het weekend. Ook als jij niet kan opnemen.'],
                    ['list-checks', 'Consequente opvolging', 'Geen lead valt nog stil. Herinneringen, offerte-follow-up en afspraken lopen automatisch, tot er een antwoord is.'],
                    ['clock', 'Minder repetitief saleswerk', 'Jij en je team steken tijd in gesprekken die ertoe doen, niet in het overtypen van gegevens of het najagen van reacties.'],
                    ['kanban', 'Overzicht over je pipeline', 'Je ziet in één blik welke leads waar staan, wat er vandaag moet gebeuren en waar het stokt.'],
                ],
                '<p>Het resultaat: meer afspraken, meer klanten en minder gemiste opportuniteiten, <strong>zonder extra leads te moeten kopen</strong>.</p>',
            ),

            $this->approach(
                'We bekijken je hele salesproces, van lead tot klant',
                null,
                [
                    ['We brengen je salesproces in kaart', 'Hoe komt een lead binnen, wie reageert, hoe snel, en waar vallen leads stil? We meten dat, in plaats van te gokken.'],
                    ['We automatiseren de grootste lekken eerst', 'Snellere eerste reactie, automatische opvolging of een assistent die de telefoon opneemt: we beginnen waar de meeste leads verloren gaan.'],
                    ['We meten en breiden uit', 'We volgen op hoeveel leads een afspraak worden, en hoeveel afspraken klant. Daarna automatiseren we stap voor stap verder waar dat loont.'],
                ],
                '<p>We vertrekken van hoe jij vandaag werkt en van de tools die je al gebruikt. <strong>Niet alles moet vervangen worden.</strong></p>',
            ),

            $this->cases(
                'Van aanvraag naar bevestigde klant, automatisch',
                '<p>Deze bedrijven verwerken aanvragen en boekingen automatisch, van eerste contact tot bevestiging. Bekijk wat het hen opleverde.</p>',
                'Sales Automation',
            ),

            $this->possibilities(
                'Wat we inzetten om meer uit je leads te halen',
                '<p>Niet elke klant heeft alles nodig. We kiezen wat past bij jouw salesproces en jouw volume.</p>',
                [
                    ['zap', 'Speed-to-lead', 'Binnen minuten reageren', 'Elke nieuwe aanvraag krijgt automatisch een eerste reactie en wordt meteen bij de juiste persoon gelegd. Dag en nacht.'],
                    ['phone-call', 'AI-telefoonassistent', 'Nooit meer een gemiste oproep', 'Een assistent die in jouw naam opneemt, vragen beantwoordt, afspraken inplant en je na elk gesprek een samenvatting stuurt. 24/7, ook op je drukste dag.'],
                    ['mail', 'Automatische leadopvolging', 'E-mail, sms of WhatsApp', 'Opvolgberichten die automatisch vertrekken op het juiste moment, in jouw toon, tot de lead reageert of een afspraak boekt.'],
                    ['calendar-check', 'Automatisch afspraken boeken', 'Geen heen-en-weer', 'Leads kiezen zelf een moment in je agenda. Bevestiging en herinnering vertrekken automatisch, no-shows dalen.'],
                    ['file-text', 'Offerte-follow-up & lead nurturing', 'Geen offerte blijft liggen', 'Verstuurde offertes worden automatisch opgevolgd. Leads die nog niet klaar zijn, blijven warm tot ze dat wel zijn.'],
                    ['kanban', 'CRM, pipelines & sales workflows', 'Overzicht en structuur', 'Eén plek waar elke lead staat, met automatische taken en statussen. Werkt met je bestaande CRM, of we zetten er één op.'],
                ],
            ),

            $this->faq('Twijfels over sales automation?', [
                ['Heb ik dit nodig als ik maar enkele leads per week krijg?', '<p>Juist dan telt elke lead. Bij een klein volume is één gemiste oproep of één vergeten offerte meteen een groot deel van je omzet. Automatisering hoeft ook niet groot te zijn: een snelle eerste reactie en een consequente follow-up maken al het verschil.</p>'],
                ['Moet ik mijn huidige CRM of tools vervangen?', '<p>Meestal niet. We koppelen wat je al gebruikt: je mailbox, agenda, CRM of boekhoudsoftware. Alleen als er echt geen centrale plek voor leads is, stellen we voor om die op te zetten.</p>'],
                ['Verliest mijn opvolging zo niet haar persoonlijke karakter?', '<p>Nee, als je het goed aanpakt. Automatisering zorgt dat het eerste contact snel en correct is en dat niets vergeten wordt. De gesprekken die ertoe doen, blijf jij voeren, met meer tijd en betere voorbereiding.</p>'],
                ['Is een AI-telefoonassistent iets voor mij?', '<p>Alleen als gemiste oproepen een echt probleem zijn, bijvoorbeeld omdat je overdag bij klanten of op de werf staat. Krijg je vooral aanvragen via je website of e-mail, dan leveren snelle opvolging en afspraakautomatisering meer op. Dat bekijken we samen.</p>'],
                ['Wat kost dit en hoe snel is het live?', '<p>Een eerste automatisering, zoals speed-to-lead of automatisch afspraken boeken, staat vaak binnen twee weken live. Een volledig ingericht salesproces met CRM en meerdere workflows vraagt vier tot zes weken. Je krijgt vooraf een vast voorstel, afgestemd op wat het je oplevert.</p>'],
            ]),

            $this->finalCta(
                'Ontdek waar je vandaag leads verliest',
                '<p>In een Bottleneck Scan van 30 minuten lopen we samen door je salesproces, van eerste contact tot klant. Je hoort waar leads stilvallen, wat dat je kost en welke stap het snelst resultaat geeft.</p>',
                $scanId,
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // 4. Business Automation
    // ---------------------------------------------------------------------

    private function businessSections(?int $scanId): array
    {
        return [
            $this->hero(
                'Business Automation',
                'Minder manueel werk. Slimmere processen.',
                '<p>Elk uur dat jij of je team kwijt is aan overtypen, lijstjes bijhouden of documenten verwerken, is een uur dat niet naar klanten of groei gaat. Wij automatiseren die processen, zodat je bedrijf vlotter draait en jij het overzicht houdt.</p>',
                $this->scanCta($scanId, 'Ontdek waar automatisering jou tijd kan besparen'),
            ),

            $this->problems(
                'Je bedrijf groeit, het manuele werk groeit mee',
                '<p>Processen die bij vijf klanten prima werkten, kraken bij vijftig. Dit zijn de signalen die we het vaakst zien.</p>',
                [
                    ['copy', 'Dezelfde gegevens meerdere keren invoeren', 'Een klant staat in je mailbox, je offertetool, je boekhouding en je planning. Vier keer ingetypt, vier kansen op fouten.', ['Dubbele invoer', 'Integraties']],
                    ['unplug', 'Systemen die niet met elkaar praten', 'Je tools werken elk apart. Informatie overbrengen gebeurt met de hand, via export en import of copy-paste.', ['Koppelingen']],
                    ['file-spreadsheet', 'Excel-, lijstjes- en documentenwerk', 'Rapporten die je maandelijks manueel samenstelt, documenten die iemand moet nalezen en overtypen, planningen in spreadsheets.', ['Rapportering', 'Documenten']],
                    ['user-x', 'Processen die afhangen van één persoon', 'Als die collega ziek of op vakantie is, weet niemand hoe het werkt. Kennis zit in hoofden, niet in systemen.', ['Continuïteit']],
                ],
                '<p>Het gevolg: uren die verdwijnen in administratie, fouten die klanten merken, en een bedrijf dat alleen groeit als je meer mensen aanneemt. <strong>Dat kan anders.</strong></p>',
            ),

            $this->benefits(
                'Meer gedaan met hetzelfde team',
                null,
                [
                    ['clock', 'Tijd besparen, elke week opnieuw', 'Repetitieve taken lopen automatisch. Uren die vrijkomen voor klanten, verkoop of gewoon een normale werkdag.'],
                    ['shield-check', 'Minder fouten', 'Gegevens worden één keer ingevoerd en automatisch doorgegeven. Geen typfouten, geen vergeten stappen.'],
                    ['plug', 'Systemen die samenwerken', 'Je website, CRM, boekhouding en planning wisselen automatisch informatie uit. Alles staat waar het moet staan.'],
                    ['layout-dashboard', 'Overzicht en controle', 'Je ziet je cijfers en je processen in realtime, in plaats van achteraf in een handgemaakt rapport.'],
                ],
                '<p>Zo kan je bedrijf groeien zonder dat de administratie evenredig meegroeit, en komt je team vrij voor <strong>werk dat waarde toevoegt</strong>.</p>',
            ),

            $this->approach(
                'Van analyse naar automatisering in drie stappen',
                null,
                [
                    ['We analyseren je processen', 'Welke taken herhalen jij en je team elke dag of week? Waar gaat tijd verloren, waar sluipen fouten in? We brengen het samen in kaart.'],
                    ['We bepalen de grootste efficiëntiewinst en bouwen die', 'Niet alles tegelijk. We starten met het proces dat het meeste tijd of ergernis kost, en bouwen daar een oplossing voor die past bij je huidige tools.'],
                    ['We meten, verbeteren en automatiseren verder', 'We volgen op wat de automatisering oplevert, sturen bij en pakken het volgende proces aan wanneer het loont.'],
                ],
                '<p>Maatwerksoftware, AI of integraties zijn <strong>mogelijke oplossingen voor een bedrijfsprobleem</strong>, geen doel op zich. We kiezen wat het probleem oplost.</p>',
            ),

            $this->cases(
                'Uren per week terugverdiend',
                '<p>Deze bedrijven verwerken boekingen, inschrijvingen en administratie automatisch. Bekijk wat het hen opleverde.</p>',
                'Business Automation',
            ),

            $this->possibilities(
                'Wat we inzetten om je processen te automatiseren',
                '<p>Welke oplossing past, volgt uit de analyse. Dit is wat we in huis hebben.</p>',
                [
                    ['workflow', 'Procesautomatisering & administratieve workflows', 'Van A naar B zonder handwerk', 'Offertes, facturen, bevestigingen, onboarding: terugkerende flows die automatisch lopen van start tot afronding.'],
                    ['plug', 'Systeemintegraties & API-koppelingen', 'Tools die samenwerken', 'We verbinden je website, CRM, boekhouding, planning en communicatietools, zodat gegevens één keer ingevoerd worden.'],
                    ['file-text', 'Document- & dataverwerking', 'Lezen, herkennen, verwerken', 'Inkomende documenten, formulieren en bestanden automatisch uitgelezen en op de juiste plek gezet. Ook met AI waar dat helpt.'],
                    ['layout-dashboard', 'Rapportering & dashboards', 'Cijfers zonder handwerk', 'Je belangrijkste cijfers automatisch samengebracht en altijd actueel, in plaats van maandelijks handmatig samengesteld.'],
                    ['calendar-clock', 'Planning & interne workflows', 'Het juiste op het juiste moment', 'Taken, herinneringen en planningen die automatisch aangemaakt en verdeeld worden, zodat je team weet wat er vandaag moet gebeuren.'],
                    ['cpu', 'AI-workflows & maatwerkapplicaties', 'Als standaardtools niet volstaan', 'Een applicatie op maat van jouw proces, of AI die repetitieve beslissingen en teksten overneemt. Alleen als het echt het verschil maakt.'],
                ],
            ),

            $this->faq('Twijfels over business automation?', [
                ['Is mijn bedrijf niet te klein hiervoor?', '<p>Nee. Juist in een klein team weegt elk uur administratie zwaar, omdat het rechtstreeks van klantentijd of je eigen avonden gaat. Een eerste automatisering is vaak klein en verdient zichzelf snel terug.</p>'],
                ['Kunnen jullie met mijn bestaande systemen werken?', '<p>In de meeste gevallen wel. Vrijwel elke moderne tool, van boekhoudsoftware tot CRM of planningstool, laat zich koppelen. We vertrekken van wat je hebt en vervangen alleen wat echt in de weg zit.</p>'],
                ['Moet alles in één keer?', '<p>Nee, liever niet. We starten met het proces dat het meeste oplevert en bouwen van daaruit verder. Zo zie je snel resultaat en blijft de verandering behapbaar voor je team.</p>'],
                ['Wat kost het en wanneer verdien ik het terug?', '<p>Dat hangt af van het aantal processen en koppelingen. Je krijgt vooraf een vast voorstel, met een inschatting van de uren die je bespaart. De meeste klanten verdienen een eerste automatisering binnen enkele maanden terug.</p>'],
                ['Wat als er iets misloopt met een automatisering?', '<p>We bouwen foutafhandeling in: mislukt een stap, dan krijgen wij een melding en lossen we het op, vaak voor jij het merkt. Je blijft niet afhankelijk van iets dat niemand begrijpt.</p>'],
                ['Is dit ook iets voor mijn verkoop en leadopvolging?', '<p>Dat kan, maar daar hebben we een aparte aanpak voor. Gaat het jou vooral om sneller en consequenter opvolgen van leads, kijk dan bij <a href="/sales-automation">Sales Automation</a>.</p>'],
            ]),

            $this->finalCta(
                'Ontdek waar automatisering jou tijd kan besparen',
                '<p>In een Bottleneck Scan van 30 minuten lopen we door je dagelijkse processen. Je hoort waar de meeste tijd verloren gaat, wat automatisering daar kan betekenen en waar je best begint.</p>',
                $scanId,
            ),
        ];
    }

    // ---------------------------------------------------------------------
    // Cross-cutting updates
    // ---------------------------------------------------------------------

    /**
     * The cases_grid section filters on a tag, so each service page gets the
     * real cases that back it up. Tags are merged, never replaced.
     */
    private function tagCases(): void
    {
        $map = [
            'bookingplatform-kanoverhuur'              => ['Websites & Conversie', 'Sales Automation', 'Business Automation'],
            'website-bookingplatform-luxe-villabeheer' => ['Websites & Conversie', 'Business Automation'],
            'website-applicatie-dansschool'            => ['Sales Automation', 'Business Automation'],
            'webshop-dartspecialist'                   => ['Websites & Conversie', 'Leadgeneratie'],
            'marktplaats-duikbedrijven'                => ['Websites & Conversie', 'Leadgeneratie'],
        ];

        foreach ($map as $slug => $tags) {
            $case = CaseStudy::where('slug', $slug)->first();
            if (! $case) {
                continue;
            }
            $existing = is_array($case->tags) ? $case->tags : (json_decode($case->tags ?? '[]', true) ?: []);
            $case->tags = array_values(array_unique([...$existing, ...$tags]));
            $case->save();
        }
    }

    private function updateMenu(Page $websites, Page $leadgen, Page $sales, Page $business): void
    {
        $menu = Menu::where('location', 'main')->first();
        if (! $menu) {
            return;
        }

        $parent = MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->where('label', 'Diensten')->first();
        if (! $parent) {
            return;
        }

        $children = [
            [$websites, 'Websites & Conversie'],
            [$leadgen, 'Leadgeneratie'],
            [$sales, 'Sales Automation'],
            [$business, 'Business Automation'],
        ];

        $keep = [];
        foreach ($children as $position => [$page, $label]) {
            $item = MenuItem::where('menu_id', $menu->id)->where('parent_id', $parent->id)->where('page_id', $page->id)->first()
                ?? new MenuItem(['menu_id' => $menu->id, 'parent_id' => $parent->id]);
            $item->fill(['label' => $label, 'page_id' => $page->id, 'url' => null, 'position' => $position, 'target_blank' => false]);
            $item->save();
            $keep[] = $item->id;
        }

        // Any other child under "Diensten" would be a sub-service page, which the new structure forbids.
        MenuItem::where('menu_id', $menu->id)->where('parent_id', $parent->id)->whereNotIn('id', $keep)->delete();
    }

    private function updateRedirects(): void
    {
        foreach ([
            'websites-en-leadgeneratie' => '/websites-conversie',
            'ai-telefoonassistenten'    => '/sales-automation',
        ] as $from => $to) {
            Redirect::updateOrCreate(['from' => $from], ['to' => $to, 'status_code' => 301]);
        }

        // Redirects are exact-match (no chaining), so re-point the old chains straight to the final destination.
        foreach ([
            '/ai-telefoonassistenten'    => '/sales-automation',
            '/websites-en-leadgeneratie' => '/websites-conversie',
            '/adviesgesprek'             => '/bottleneck-scan',
            '/gratis-adviesgesprek'      => '/bottleneck-scan',
        ] as $oldTarget => $newTarget) {
            Redirect::where('to', $oldTarget)->update(['to' => $newTarget]);
        }
    }

    /**
     * The homepage "Wat we doen" cards are the internal links to the services;
     * they must name all four and use the new labels.
     */
    private function updateHomepageServiceCards(Page $websites, Page $leadgen, Page $sales, Page $business): void
    {
        $home = Page::where('is_homepage', true)->first();
        if (! $home) {
            return;
        }

        $section = PageSection::where('sectionable_type', $home->getMorphClass())
            ->where('sectionable_id', $home->id)
            ->where('section_type', 'cards')
            ->where('content->section_id', 'diensten')
            ->first();
        if (! $section) {
            return;
        }

        $card = fn (string $icon, string $title, string $subtitle, string $description, Page $page, array $features) => [
            'media_type' => 'icon', 'icon' => $icon, 'title' => $title, 'subtitle' => $subtitle,
            'description' => $description, 'features' => $features,
            'cta_label' => 'Meer weten', 'link_type' => 'page', 'page_id' => $page->id,
        ];

        // Same customer journey as the problem-recognition section right above it,
        // so the four cards read as the answer to that journey.
        $journey = PageSection::where('sectionable_type', $home->getMorphClass())
            ->where('sectionable_id', $home->id)
            ->where('section_type', 'problem_recognition')
            ->orderBy('position')
            ->first()?->content['journey'] ?? [];

        $content = $section->content;
        $content['journey'] = $journey;
        $content['columns'] = 4;
        $content['intro'] = '<p>Vier manieren waarop we de groei van zelfstandigen en KMO\'s aanpakken. Stap voor stap, en altijd te beginnen bij de grootste bottleneck.</p>';
        $content['cards'] = [
            $card('mouse-pointer-click', 'Meer aanvragen uit je website', 'Websites & Conversie', 'Van bezoeker naar aanvraag.', $websites, ['Websites op maat', 'Landingpagina\'s & funnels', 'Conversie-optimalisatie', 'Analytics & tracking']),
            $card('target', 'Meer van de juiste klanten bereiken', 'Leadgeneratie', 'Van onbekende naar relevante lead.', $leadgen, ['Meta & Google Ads', 'Campagnefunnels', 'SEO & content', 'Tracking & attributie']),
            $card('phone-call', 'Meer klanten uit je leads', 'Sales Automation', 'Van lead naar afspraak en klant.', $sales, ['Speed-to-lead', 'AI-telefoonassistent', 'Automatische opvolging', 'CRM & pipelines']),
            $card('settings-2', 'Minder manueel werk', 'Business Automation', 'Van manueel werk naar efficiënte processen.', $business, ['Systeemintegraties', 'Administratieve workflows', 'Rapportering & dashboards', 'Maatwerkapplicaties']),
        ];
        $section->content = $content;
        $section->save();
    }

    private function updateFooterTagline(): void
    {
        $setting = Setting::where('key', 'footer')->first();
        if (! $setting) {
            return;
        }
        $value = is_array($setting->value) ? $setting->value : (json_decode($setting->value ?? '{}', true) ?: []);
        $value['brand']['tagline'] = 'Meer leads, meer klanten, minder manueel werk. Websites & conversie, leadgeneratie, sales automation en business automation voor zelfstandigen en KMO\'s.';
        $setting->value = $value;
        $setting->save();
    }
}
