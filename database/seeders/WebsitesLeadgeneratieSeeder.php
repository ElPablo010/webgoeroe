<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Websites & Leadgeneratie-pagina — conversie-ritme:
 * Hero → Probleem → Oplossing → Wat inbegrepen (cards) → Onze aanpak (cards)
 * → Testimonials → FAQ → CTA
 */
class WebsitesLeadgeneratieSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'websites-en-leadgeneratie'],
            [
                'title'            => 'Websites & Leadgeneratie',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Websites & Leadgeneratie — Converterende websites + Meta-advertenties | De Webgoeroe',
                'meta_description' => 'We bouwen geen mooie websites — we bouwen klantenmachines. Converterende website op maat + Meta-advertenties die jouw ideale klant bereiken. Live in 3–4 weken.',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero -------------------------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'section_id' => null,
                    'eyebrow'    => 'Websites & Leadgeneratie',
                    'heading'    => "Van website naar klantenmachine:\nbezoekers omzetten in\nbetalende klanten.",
                    'subtitle'   => '<p>Wij bouwen geen "mooie websites" — wij bouwen systemen die 24/7 voor jou werken. Elke knop, elke tekst en elk beeld is ontworpen met één doel: meer betalende klanten voor jouw bedrijf.</p>',
                    'image'      => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas'       => [
                        [
                            'label'     => 'Gratis gesprek inplannen',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                        [
                            'label'     => 'Bekijk wat inbegrepen is',
                            'variant'   => 'ghost',
                            'link_type' => 'url',
                            'href'      => '#wat-inbegrepen',
                        ],
                    ],
                ],
            ],

            // 2. Probleem (text_media) --------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => 'probleem',
                    'background' => 'light',
                    'eyebrow'    => 'Het probleem',
                    'heading'    => 'De meeste websites kosten meer dan ze opbrengen',
                    'intro'      => '<p>Een website zonder conversiestrategie is duur behang. Je betaalt voor hosting, onderhoud en misschien zelfs advertenties — maar de telefoon gaat niet.</p><p>Bezoekers klikken weg na 8 seconden omdat ze niet direct vinden wat ze zoeken. Er staat geen duidelijke call-to-action. De laadtijd is te traag op mobiel. De teksten spreken niet aan.</p><p>Ondertussen investeer je in Google Ads of Meta-advertenties die geld verbranden aan bezoekers die toch niet converteren — want de website vangt ze niet op.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media'      => ['src' => null, 'alt' => 'Website zonder conversie — bezoekers haken af'],
                    'ctas'       => [],
                ],
            ],

            // 3. Oplossing (text_media) -------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => null,
                    'background' => 'dark',
                    'eyebrow'    => 'De oplossing',
                    'heading'    => 'Een website die 24/7 voor jou werkt — ook als jij slaapt',
                    'intro'      => '<p>We bouwen een website die niet alleen goed uitkijkt, maar ook converteert. Elke pagina is doordacht: de juiste boodschap voor de juiste bezoeker op het juiste moment.</p><p>Aangevuld met gerichte Meta-advertenties bereiken we jouw ideale klant — niet toevallige websitebezoekers, maar mensen die al actief op zoek zijn naar jouw dienst in jouw regio.</p><ul><li><strong>Conversiegerichte copywriting</strong> — teksten die overtuigen, geen vage marketingpraat</li><li><strong>Technisch correct</strong> — snel, mobiel-vriendelijk, SEO-geoptimaliseerd</li><li><strong>Gerichte advertenties</strong> — we bereiken jouw ideale klant op Meta en/of Google</li><li><strong>Alles meetbaar</strong> — je ziet exact hoeveel leads je website genereert</li></ul>',
                    'media_type' => 'image',
                    'media_side' => 'left',
                    'media'      => ['src' => null, 'alt' => 'Converterende website — meer leads voor KMO\'s'],
                    'ctas'       => [
                        [
                            'label'     => 'Plan een gratis gesprek',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                    ],
                ],
            ],

            // 4. Wat inbegrepen (cards) -------------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'wat-inbegrepen',
                    'background' => 'light',
                    'eyebrow'    => 'Wat inbegrepen is',
                    'heading'    => 'Alles wat je nodig hebt — van strategie tot resultaat',
                    'intro'      => '<p>Geen à-la-carte-menu waarbij je zelf moet uitzoeken wat je nodig hebt. Wij leveren een volledig systeem dat werkt.</p>',
                    'columns'    => '3',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'layout',
                            'title'       => 'Converterende website op maat',
                            'subtitle'    => 'Design dat verkoopt',
                            'description' => 'Elke pagina gebouwd vanuit conversiedoelen — niet vanuit esthetiek alleen. Duidelijke structuur, overtuigende copy en strategisch geplaatste call-to-actions.',
                            'features'    => [
                                'Op maat ontworpen, niet van een template',
                                'Mobielvriendelijk & razendsnel',
                                'Eenvoudig zelf te beheren',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'search',
                            'title'       => 'SEO-optimalisatie',
                            'subtitle'    => 'Gevonden worden via Google',
                            'description' => 'Technische SEO, zoekwoordenonderzoek en geoptimaliseerde teksten zodat jouw website gevonden wordt door mensen die actief zoeken naar jouw diensten.',
                            'features'    => [
                                'Zoekwoordenonderzoek inbegrepen',
                                'Technisch SEO-fundament',
                                'Lokale SEO voor jouw regio',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'megaphone',
                            'title'       => 'Meta-advertenties',
                            'subtitle'    => 'Bereik jouw ideale klant',
                            'description' => 'Gerichte campagnes op Facebook en Instagram die jouw ideale klantprofiel bereiken in jouw regio — met meetbare kosten per lead.',
                            'features'    => [
                                'Doelgroepbepaling op maat',
                                'A/B-testen van advertenties',
                                'Maandelijkse rapportage',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'mouse-pointer-click',
                            'title'       => 'Landingspagina\'s & funnels',
                            'subtitle'    => 'Elke campagne zijn eigen pagina',
                            'description' => 'Aparte, geoptimaliseerde landingspagina\'s voor elke advertentiecampagne — zodat bezoekers direct zien wat relevant is voor hen en sneller converteren.',
                            'features'    => [
                                'Één duidelijke boodschap per pagina',
                                'Hoge relevantiescore = lagere kosten',
                                'A/B-varianten mogelijk',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'bar-chart-2',
                            'title'       => 'Analytics & rapportage',
                            'subtitle'    => 'Alles meetbaar',
                            'description' => 'Je ziet exact hoeveel bezoekers je krijgt, hoeveel er converteren, hoeveel leads je website genereert en wat die kosten via advertenties.',
                            'features'    => [
                                'Google Analytics 4 ingesteld',
                                'Conversie-tracking ingesteld',
                                'Maandelijkse rapportage',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'shield',
                            'title'       => 'Onderhoud & support',
                            'subtitle'    => 'Altijd up-to-date',
                            'description' => 'Updates, beveiligingspatches, kleine aanpassingen en support via een directe lijn — zonder wachtrijen of dure uurlonen per aanpassing.',
                            'features'    => [
                                'Maandelijkse onderhoudsupdates',
                                'Directe lijn met Pieter',
                                'Kleine aanpassingen inbegrepen',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                    ],
                ],
            ],

            // 5. Onze aanpak — stappen (cards) ------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'aanpak',
                    'background' => 'dark',
                    'eyebrow'    => 'Onze aanpak',
                    'heading'    => 'Van brief tot live in 3–4 weken',
                    'intro'      => '<p>Geen maandenlange trajecten. Geen eindeloze revisieronden. We werken snel, transparant en resultaatgericht.</p>',
                    'columns'    => '4',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'message-circle',
                            'title'       => 'Gratis kennismakingsgesprek',
                            'subtitle'    => 'Week 0',
                            'description' => 'We leren jouw bedrijf en doelen kennen. We analyseren jouw huidige situatie en stellen een concreet plan voor.',
                            'features'    => ['30 minuten', 'Volledig gratis', 'Geen verplichtingen'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'pencil-ruler',
                            'title'       => 'Strategie & design',
                            'subtitle'    => 'Week 1',
                            'description' => 'We werken de conversiestrategie uit, schrijven de teksten en ontwerpen de website. Jij geeft feedback en keurt goed.',
                            'features'    => ['Copywriting inbegrepen', 'Design op jouw brand', 'Snelle feedbackronden'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'code-2',
                            'title'       => 'Bouwen & testen',
                            'subtitle'    => 'Week 2–3',
                            'description' => 'We bouwen de website, koppelen analytics, testen op alle apparaten en optimaliseren laadsnelheid. Jij hoeft er niks voor te doen.',
                            'features'    => ['Volledig door ons gebouwd', 'Test op mobiel & desktop', 'Snelheidsoptimalisatie'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'rocket',
                            'title'       => 'Live & eerste leads',
                            'subtitle'    => 'Week 3–4',
                            'description' => 'De website gaat live. Advertentiecampagnes worden geactiveerd. We meten resultaten en optimaliseren continu.',
                            'features'    => ['Livegang na jouw goedkeuring', 'Advertenties direct actief', 'Maandelijkse optimalisatie'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                    ],
                ],
            ],

            // 6. Testimonials -----------------------------------------------
            [
                'section_type' => 'testimonials',
                'content' => [
                    'section_id' => null,
                    'background' => 'light',
                    'eyebrow'    => 'Resultaten van klanten',
                    'heading'    => 'Websites die écht meer klanten binnenbrengen',
                    'items'      => [
                        [
                            'quote'   => 'Onze nieuwe website converteert drie keer beter dan de oude. Pieter heeft echt meegedacht over wat onze klanten nodig hebben — niet zomaar een mooie pagina gemaakt.',
                            'author'  => 'Sofie V.',
                            'company' => 'Zaakvoerder, bouwbedrijf Mechelen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'Via de Meta-advertenties die De Webgoeroe beheert, halen we elke maand 40 à 50 nieuwe leads binnen. Vroeger moesten we alles via mond-aan-mondreclame doen. Dat verschil is enorm.',
                            'author'  => 'Thomas B.',
                            'company' => 'Loodgieter, Brussel',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'Binnen drie weken stond mijn nieuwe website live. Eerste maand al vier offerte-aanvragen via de website — dat had ik de vorige vijf jaar nooit gehad.',
                            'author'  => 'Sara W.',
                            'company' => 'Interior designer, Antwerpen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                    ],
                ],
            ],

            // 7. FAQ --------------------------------------------------------
            [
                'section_type' => 'faq',
                'content' => [
                    'section_id' => 'faq',
                    'background' => 'dark',
                    'eyebrow'    => 'Veelgestelde vragen',
                    'heading'    => 'Alles over onze websites & leadgeneratie',
                    'intro'      => null,
                    'items'      => [
                        [
                            'question' => 'Hoe lang duurt het om een website te bouwen?',
                            'answer'   => '<p>Gemiddeld 3 tot 4 weken van eerste gesprek tot livegang. Dat hangt af van hoe snel jij feedback geeft en hoeveel pagina\'s de website heeft. We geven altijd een concrete tijdlijn na het kennismakingsgesprek.</p>',
                        ],
                        [
                            'question' => 'Kan ik de website zelf aanpassen na livegang?',
                            'answer'   => '<p>Ja. We bouwen elke website met een eenvoudige admin (CMS) waarbij je teksten, afbeeldingen en zelfs volledige pagina\'s kan aanpassen zonder technische kennis. We geven je ook een korte uitleg na livegang.</p>',
                        ],
                        [
                            'question' => 'Zijn advertenties verplicht bij een website?',
                            'answer'   => '<p>Nee. Je kan starten met enkel de website en later advertenties toevoegen. Sommige klanten groeien ook zonder advertenties via organische SEO. We bespreken altijd wat het meeste zin heeft voor jouw specifieke situatie.</p>',
                        ],
                        [
                            'question' => 'Wat is het minimum budget voor Meta-advertenties?',
                            'answer'   => '<p>We raden een minimum advertentiebudget aan van €500/maand om voldoende data te verzamelen en te optimaliseren. Ons beheersvergoeding komt daar bovenop. Minder is mogelijk maar geeft minder snel resultaten.</p>',
                        ],
                        [
                            'question' => 'Schrijven jullie ook de teksten?',
                            'answer'   => '<p>Ja, copywriting is standaard inbegrepen. We schrijven conversiegerichte teksten op basis van een intake-gesprek over jouw bedrijf, doelgroep en unique selling points. Je kan altijd aanpassingen vragen.</p>',
                        ],
                        [
                            'question' => 'Wat als ik al een website heb?',
                            'answer'   => '<p>Dan bekijken we samen wat er nog van bruikbaar is en wat er aangepast moet worden om te converteren. Soms is een volledige rebuild de snelste weg, soms een gerichte optimalisatie. We zijn eerlijk over wat je het meeste oplevert.</p>',
                        ],
                    ],
                ],
            ],

            // 8. CTA --------------------------------------------------------
            [
                'section_type' => 'cta',
                'content' => [
                    'section_id' => null,
                    'background' => null,
                    'eyebrow'    => 'Gratis · Geen verplichtingen',
                    'heading'    => 'Klaar voor een website die écht meer klanten oplevert?',
                    'intro'      => '<p>Plan een gratis gesprek van 30 minuten. We analyseren jouw huidige situatie, tonen je wat mogelijk is en maken een concreet voorstel op maat.</p>',
                    'ctas'       => [
                        [
                            'label'     => 'Plan een gratis gesprek',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                    ],
                    'note' => 'Geen creditcard · Transparante prijs · Antwoord binnen 24u',
                ],
            ],
        ];

        foreach ($sections as $position => $section) {
            $page->sections()->create([
                'section_type' => $section['section_type'],
                'position'     => $position,
                'locale'       => 'nl',
                'content'      => $section['content'],
            ]);
        }

        $this->command->info('Websites & Leadgeneratie pagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
