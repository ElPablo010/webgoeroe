<?php

use App\Models\Page;
use App\Models\Setting;
use App\Support\LandingPages;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina van de campagne "duur visitekaartje" (converterende websites):
 * /website-die-verkoopt. Copy en keuzes: WebGoeroe/wiki/campagne-converterende-websites.md.
 *
 * Content-migratie zodat de pagina met een gewone deploy live komt, zonder
 * db-push. Bestaat de slug al (bv. al bewerkt in de admin), dan doet ze niets.
 * Gepubliceerd als noindex: het is een advertentiepagina, geen SEO-pagina.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    /** Bestaande beelden uit de media-library (screenshot uit de case, foto van "Over ons"). */
    private const RAAMINZICHT_IMAGE = '/storage/website-media/01M2SK5C3SCZFW4G6Z639ZNNS7.webp';

    /** Liggende uitsnede van de "Over ons"-foto; staat in de repo zodat ze live mee in de library komt. */
    private const PIETER_IMAGE_SOURCE = 'resources/images/landing/pieter-bureau-liggend.jpg';

    public function up(): void
    {
        // Enkel op een site met inhoud (lokaal/live): een verse database (tests,
        // nieuwe installatie) heeft geen bottleneck-scan en krijgt deze pagina niet.
        if (Page::query()->where('slug', self::SLUG)->exists()
            || ! Page::query()->where('slug', 'bottleneck-scan')->exists()) {
            return;
        }

        $page = Page::query()->create([
            'title'            => 'Website die verkoopt',
            'slug'             => self::SLUG,
            'locale'           => 'nl',
            'published'        => true,
            'meta_title'       => 'Brengt jouw website klanten binnen? | De WebGoeroe',
            'meta_description' => 'Is jouw website meer dan een duur visitekaartje? Bereken hoeveel klanten je misloopt en ontdek hoe je met dezelfde bezoekers meer aanvragen haalt.',
            'meta_robots'      => 'noindex,follow',
        ]);

        foreach ($this->sections() as $position => [$type, $content]) {
            $page->sections()->create([
                'section_type' => $type,
                'position'     => $position,
                'locale'       => 'nl',
                'content'      => $content,
            ]);
        }

        // Advertentiepagina: zonder menu en footer (Instellingen → Algemeen).
        LandingPages::add($page->id);
    }

    public function down(): void
    {
        Page::query()->where('slug', self::SLUG)->get()->each(function (Page $page) {
            Setting::set(LandingPages::KEY, array_values(array_diff(LandingPages::ids(), [$page->id])));
            $page->sections()->delete();
            $page->delete();
        });
    }

    /** Beeld enkel meegeven als het in de library staat, anders toont het blok enkel tekst. */
    private function media(string $src, string $alt): array
    {
        $exists = \Webgoeroe\Core\Models\WebsiteMedia::query()
            ->where('path', ltrim(str_replace('/storage/', '', $src), '/'))
            ->exists();

        return $exists ? ['src' => $src, 'alt' => $alt] : [];
    }

    /** Zet een beeld uit de repo in de media-library; lukt dat niet, dan toont het blok enkel tekst. */
    private function importMedia(string $relativePath, string $alt): array
    {
        $path = base_path($relativePath);

        if (! is_file($path)) {
            return [];
        }

        try {
            $media = app(\Webgoeroe\Core\Services\WebsiteMediaService::class)
                ->store(new \Illuminate\Http\UploadedFile($path, basename($path), 'image/jpeg', null, true));
        } catch (\Throwable) {
            return [];
        }

        return ['src' => $media->url, 'alt' => $alt];
    }

    private function sections(): array
    {
        return [
            ['hero', [
                'height'   => 'tall',
                'eyebrow'  => "Voor zelfstandigen en kmo's",
                'heading'  => 'Brengt jouw website klanten binnen, of is het een duur visitekaartje?',
                'subtitle' => 'De meeste websites tonen netjes wie je bent en wat je doet. Logisch, maar zo overtuig je niemand om voor jou te kiezen. Gevolg: je loopt elke maand klanten mis, zonder dat je het merkt.',
                'ctas'     => [
                    ['label' => 'Bereken wat je misloopt', 'variant' => 'primary', 'link_type' => 'url', 'href' => '#calculator'],
                    ['label' => 'Hoe het werkt', 'variant' => 'ghost', 'link_type' => 'url', 'href' => '#waarom'],
                ],
            ]],

            ['text', [
                'background' => 'dark',
                'eyebrow'    => 'Herkenbaar?',
                'heading'    => 'Herken je dit?',
                'body'       => '<ul>'
                    .'<li>Je website ziet er goed uit, maar de telefoon rinkelt er niet van.</li>'
                    .'<li>Je meeste klanten komen via mond-tot-mond. Je website speelt nauwelijks mee.</li>'
                    .'<li>Je krijgt af en toe een aanvraag, maar je weet niet waarom of waarvandaan.</li>'
                    .'<li>Je hebt ooit flink geïnvesteerd in je site. Wat hij oplevert, kan je niet zeggen.</li>'
                    .'<li>Je ziet concurrenten groeien die niet beter zijn dan jij.</li>'
                    .'</ul>'
                    .'<p><strong>Je website is waarschijnlijk prima gebouwd. Alleen is hij niet geoptimaliseerd om te verkopen.</strong></p>',
            ]],

            ['problem_recognition', [
                'section_id' => 'waarom',
                'background' => 'light',
                'eyebrow'    => 'Waarom het misloopt',
                'heading'    => 'Waarom je website geen klanten oplevert',
                'intro'      => '<p>De meeste websites zijn technisch in orde. Ze zijn snel, mooi en mobiel. Toch verkopen ze niet, omdat ze gebouwd zijn om te informeren in plaats van om te overtuigen.</p>',
                'problems'   => [
                    ['icon' => 'users', 'title' => 'Je richt je tot iedereen, dus spreek je niemand aan', 'description' => 'Wie alles voor iedereen doet, valt bij niemand op. Je bezoeker herkent zichzelf niet en klikt weg.'],
                    ['icon' => 'help-circle', 'title' => 'Je aanbod is niet meteen duidelijk', 'description' => 'Na 5 seconden moet je bezoeker weten wat je doet, voor wie en wat het hem oplevert. Lukt dat niet, dan is hij weg.'],
                    ['icon' => 'heart-off', 'title' => 'Je informeert, maar je raakt niets', 'description' => 'Je site somt netjes op wie je bent en wat je doet. Logisch en correct. Maar mensen kopen op emotie: hun frustratie, hun verlangen. Raak je dat niet, dan kiezen ze niet.'],
                    ['icon' => 'shield-question', 'title' => 'Je bewijst niets', 'description' => 'Geen resultaten, geen cases, geen echte reviews. Dan moet je bezoeker je op je woord geloven. Dat doet hij niet.'],
                    ['icon' => 'signpost', 'title' => 'Je vervolgstap is vaag', 'description' => '"Neem contact op" zegt niets. Wat gebeurt er dan? Hoe lang duurt het? Wat kost het? Een concrete stap met een duidelijke verwachting durft hij wel zetten.'],
                    ['icon' => 'copy', 'title' => 'Je bent inwisselbaar', 'description' => 'Kwaliteit, service, ervaring. Dat zegt je concurrent ook. Ziet je bezoeker niet waarom jij, dan vergelijkt hij op prijs.'],
                ],
                'closing'    => '',
                'cta_label'  => null,
            ]],

            ['conversion_calculator', [
                'section_id'       => 'calculator',
                'background'       => 'dark',
                'eyebrow'          => 'Reken het zelf uit',
                'heading'          => 'Wat kost je website je eigenlijk?',
                'intro'            => '<p>Vul je cijfers in. Weet je ze niet precies? Een schatting volstaat.</p>',
                'default_visitors' => 500,
                'default_leads'    => 5,
                'target_rate'      => 3,
                'sectors'          => [
                    ['key' => 'renovatie', 'label' => 'Bouw & renovatie', 'close_rate' => 25, 'client_value' => 8000],
                    ['key' => 'coaching', 'label' => 'Coaching & opleiding', 'close_rate' => 40, 'client_value' => 1500],
                    ['key' => 'advies', 'label' => 'Advies & dienstverlening', 'close_rate' => 30, 'client_value' => 3000],
                    ['key' => 'andere', 'label' => 'Andere', 'close_rate' => 30, 'client_value' => 2000],
                ],
                'disclaimer'       => 'Schatting op basis van jouw cijfers en een haalbare conversie van 3%. Geen garantie, wel een realistisch beeld.',
                'cta_label'        => 'Ontdek waar jouw website lekt',
                'link_type'        => 'url',
                'href'             => '#scan',
            ]],

            ['process_steps', [
                'section_id' => 'aanpak',
                'background' => 'light',
                'eyebrow'    => 'Zo pakken we het aan',
                'heading'    => 'Van visitekaartje naar website die verkoopt',
                'steps'      => [
                    ['title' => 'Analyseren', 'description' => 'We lichten je huidige site door: wie is je ideale klant, wat overtuigt hem, en waar haakt hij af? Je krijgt een conversieplan met concrete verbeterpunten.'],
                    ['title' => 'Herschrijven en herbouwen', 'description' => 'We zetten je aanbod scherp, schrijven teksten die raken en bouwen de pagina\'s rond één doel: een aanvraag. Soms volstaat optimaliseren, soms is een nieuwe site slimmer. Dat zeggen we eerlijk.'],
                    ['title' => 'Meten en verbeteren', 'description' => 'Je ziet precies hoeveel aanvragen je site oplevert en waar ze vandaan komen. Zo weet je wat je website je opbrengt.'],
                ],
            ]],

            ['text_media', [
                'background' => 'dark',
                'eyebrow'    => 'Wat dat oplevert',
                'heading'    => 'Raaminzicht: van 10 naar 20 aanvragen per week',
                'intro'      => '<p>Ik bouwde een volledig nieuwe website voor een bedrijf in ramen en zonwering, geoptimaliseerd voor conversie.</p>'
                    .'<p><strong>Resultaat: het aantal aanvragen verdubbelde, binnen 3 weken na livegang.</strong></p>',
                'media_type' => 'image',
                'media_side' => 'right',
                'media'      => $this->media(self::RAAMINZICHT_IMAGE, 'Screenshot website Raaminzicht'),
                'ctas'       => [],
            ]],

            // Echte quotes uit de cases (bevestigd door Pieter, 09/10/2026). Geen sterren:
            // er is geen echte score.
            ['reviews', [
                'background' => 'light',
                'eyebrow'    => 'Wat klanten zeggen',
                'heading'    => 'Ondernemers die het verschil merken',
                'items'      => [
                    ['quote' => 'We wilden een redesign. Drie weken later zaten we aan twintig aanvragen per week in plaats van tien.', 'name' => 'Raaminzicht', 'role' => 'Zonwering & schrijnwerk', 'rating' => 0],
                    ['quote' => 'Wat vroeger veel manueel werk en chaos was, loopt nu overzichtelijk en automatisch. Boekingen, planning en materiaalbeheer zitten eindelijk in één systeem. Dat maakt een enorm verschil in onze werking.', 'name' => 'Nick Meylemans', 'role' => 'Zaakvoerder, Ark van Noë', 'rating' => 0],
                    ['quote' => "De nieuwe website sluit perfect aan op hoe wij onze villa's beheren. Alles wordt automatisch gesynchroniseerd en onze klanten zien altijd correcte beschikbaarheden. Dat geeft vertrouwen en bespaart ons enorm veel tijd.", 'name' => 'Nick Meylemans', 'role' => 'Zaakvoerder, Golden Palm Collection', 'rating' => 0],
                ],
            ]],

            ['text_media', [
                'background' => 'dark',
                'eyebrow'    => 'Wie ik ben',
                'heading'    => 'Geen bureau met tien lagen. Gewoon een directe lijn.',
                'intro'      => '<p>Ik ben Pieter, oprichter van De WebGoeroe. Ik bouw websites die geen visitekaartje zijn, maar een verkoper die 24/7 voor je werkt. Je spreekt altijd rechtstreeks met mij: geen accountmanager, geen ticketsysteem.</p>'
                    .'<p>En ik ben eerlijk: als je website niet het probleem is, zeg ik dat ook.</p>',
                'media_type' => 'image',
                'media_side' => 'left',
                'media'      => $this->importMedia(self::PIETER_IMAGE_SOURCE, 'Pieter Van Looy, oprichter De WebGoeroe'),
                'ctas'       => [],
            ]],

            // Geen Calendly-embed: die laadt pas na scrollen én na cookie-toestemming.
            // Een knop opent de agenda meteen.
            ['cta', [
                'section_id' => 'scan',
                'background' => 'light',
                'eyebrow'    => 'Gratis Bottleneck Scan',
                'heading'    => 'Ontdek waar jouw website klanten verliest',
                'intro'      => '<p>✓ Een gesprek van 30 minuten, online<br>'
                    .'✓ Ik heb je website vooraf bekeken<br>'
                    .'✓ Je hoort de 3 grootste redenen waarom je site niet converteert<br>'
                    .'✓ Je weet wat het je kan opleveren en wat de volgende stap is</p>'
                    .'<p>Geen verkooppraatje, geen presentatie. Wil je daarna niet verder? Dan heb je toch 3 concrete verbeterpunten.</p>',
                'ctas'       => [
                    ['label' => 'Kies een moment', 'variant' => 'primary', 'link_type' => 'url', 'href' => 'https://calendly.com/dewebgoeroe/bottleneck-scan'],
                ],
            ]],

            ['faq', [
                'section_id' => 'faq',
                'background' => 'dark',
                'heading'    => 'Veelgestelde vragen',
                'items'      => [
                    ['question' => 'Mijn website is pas nieuw. Is dit dan voor mij?', 'answer' => 'Waarschijnlijk wel. Een nieuwe website is technisch meestal in orde, maar dat betekent nog niet dat hij verkoopt. Daar zit net het verschil.'],
                    ['question' => 'Moet ik dan een volledig nieuwe website?', 'answer' => 'Niet altijd. Soms levert het herschrijven van je teksten en een duidelijke vervolgstap al veel op. In de scan hoor je wat in jouw geval zinvol is.'],
                    ['question' => 'Wat kost dat?', 'answer' => 'Dat hangt af van wat je site nodig heeft. Je krijgt altijd een voorstel met een vaste prijs, zodat je vooraf weet waar je aan toe bent.'],
                    ['question' => 'Hoe lang duurt het?', 'answer' => 'Een nieuwe website staat er in 3 tot 6 weken. Optimalisaties gaan sneller.'],
                    ['question' => 'Ik heb maar weinig bezoekers. Helpt dit dan?', 'answer' => 'Eerlijk: als er bijna niemand op je site komt, is conversie niet je eerste probleem. Dan moet er eerst verkeer komen. Ook dat bekijken we in de scan.'],
                    ['question' => 'Werk je enkel in mijn sector?', 'answer' => "Nee. Ik werk voor zelfstandigen en kmo's in verschillende sectoren. De principes van een website die verkoopt zijn overal dezelfde."],
                ],
            ]],

            ['cta', [
                'heading' => 'Je bezoekers zijn er al. Laat ze niet langer naar je concurrent gaan.',
                'ctas'    => [
                    ['label' => 'Boek een gratis Bottleneck Scan', 'variant' => 'primary', 'link_type' => 'url', 'href' => '#scan'],
                    ['label' => 'Bereken eerst wat je misloopt', 'variant' => 'secondary', 'link_type' => 'url', 'href' => '#calculator'],
                ],
            ]],
        ];
    }
};
