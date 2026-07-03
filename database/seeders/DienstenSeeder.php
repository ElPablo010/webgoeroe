<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Diensten-overzichtspagina — conversie-ritme:
 * Hero → 3 Diensten (cards) → Aanpak (text_media) → Testimonials → FAQ → CTA
 */
class DienstenSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'diensten'],
            [
                'title'            => 'Diensten',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Diensten — AI-telefoonassistent, websites & automatisering | De Webgoeroe',
                'meta_description' => 'Drie bewezen digitale systemen voor KMO\'s en zelfstandigen: AI-telefoonassistent, converterende websites + Meta-advertenties, en business automation. Bekijk wat we voor jou kunnen doen.',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero -------------------------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'section_id' => null,
                    'eyebrow'    => 'Onze diensten',
                    'heading'    => "Drie bewezen systemen.\nEén missie: jouw bedrijf laten groeien.",
                    'subtitle'   => '<p>Van een AI-assistent die 24/7 jouw telefoon opneemt tot een website die bezoekers omzet in betalende klanten — we lossen de drie grootste pijnpunten op voor Belgische zelfstandigen en KMO\'s.</p>',
                    'ctas'       => [
                        [
                            'label'     => 'Gratis gesprek inplannen',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                        [
                            'label'     => 'Bekijk onze aanpak',
                            'variant'   => 'ghost',
                            'link_type' => 'url',
                            'href'      => '#aanpak',
                        ],
                    ],
                ],
            ],

            // 2. Cards — 3 diensten -----------------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'overzicht',
                    'background' => 'light',
                    'eyebrow'    => 'Drie systemen',
                    'heading'    => 'Elk systeem lost een concreet probleem op',
                    'intro'      => '<p>Geen vaag digitaal advies — drie concrete systemen met meetbare resultaten die stuk voor stuk jouw bedrijf vooruithelpen.</p>',
                    'columns'    => '3',
                    'cards'      => [
                        [
                            'title'       => 'AI-telefoonassistent',
                            'icon'        => 'phone-call',
                            'media_type'  => 'icon',
                            'subtitle'    => 'Nooit meer een gemiste klant',
                            'description' => 'Jouw AI-assistent neemt elke oproep op in jouw naam: vragen beantwoorden, afspraken inplannen, offerteaanvragen doorsturen — 24/7, ook als jij niet beschikbaar bent.',
                            'features'    => [
                                'Oproepen beantwoord in < 3 seconden',
                                'Afspraken automatisch in jouw agenda',
                                'Offerteaanvragen direct doorgestuurd',
                                'Gesprekssamenvattingen per sms/mail',
                                'Live binnen de week',
                            ],
                            'cta_label' => 'Meer over AI-assistent',
                            'link_type' => 'url',
                            'href'      => '/ai-telefoonassistent',
                        ],
                        [
                            'title'       => 'Websites & leadgeneratie',
                            'icon'        => 'trending-up',
                            'media_type'  => 'icon',
                            'subtitle'    => 'Meer leads & betalende klanten',
                            'description' => 'Een converterende website op maat, aangevuld met Meta-advertenties die jouw ideale klant bereiken op het juiste moment — samen vormen ze een constante stroom nieuwe leads.',
                            'features'    => [
                                'Website live in 3–4 weken',
                                'Gemiddeld 3× hogere conversie',
                                'Meta-advertenties met meetbare ROI',
                                'Landingspagina\'s & funnels',
                                'Analytics & maandrapportage',
                            ],
                            'cta_label' => 'Meer over websites',
                            'link_type' => 'url',
                            'href'      => '/websites-en-leadgeneratie',
                        ],
                        [
                            'title'       => 'Business automation',
                            'icon'        => 'settings-2',
                            'media_type'  => 'icon',
                            'subtitle'    => 'Minder administratie',
                            'description' => 'Verbind je tools, automatiseer herhalende taken en win elke week uren terug — van automatische offerteherinneringen tot volledige facturatieflows die zichzelf beheren.',
                            'features'    => [
                                'Automatische offerte- & factuurflow',
                                'Koppelingen tussen al je tools',
                                'Herinneringen & follow-ups op autopiloot',
                                'Dashboards met real-time overzicht',
                                'Implementatie in 2–4 weken',
                            ],
                            'cta_label' => 'Meer over automatisering',
                            'link_type' => 'url',
                            'href'      => '/business-automation',
                        ],
                    ],
                ],
            ],

            // 3. Aanpak (text_media) ----------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => 'aanpak',
                    'background' => 'dark',
                    'eyebrow'    => 'Onze aanpak',
                    'heading'    => 'Altijd op maat — nooit een kant-en-klaar pakket',
                    'intro'      => '<p>We pushen je niet in een formule die niet bij jouw bedrijf past. Elk systeem wordt afgestemd op jouw situatie, jouw klanten en jouw doelen.</p><p>Onze aanpak in vier stappen:</p><ul><li><strong>Gratis gesprek van 30 minuten</strong> — we analyseren jouw situatie en identificeren de grootste groeikansen.</li><li><strong>Voorstel op maat</strong> — transparante prijs, duidelijke deliverables, geen verborgen kosten.</li><li><strong>We bouwen en testen</strong> — jij hoeft er niets voor te doen. We houden je op de hoogte en gaan live na jouw goedkeuring.</li><li><strong>Meten en optimaliseren</strong> — resultaten volgen we op. Werkt iets beter dan verwacht? We gaan er dieper op in.</li></ul>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media'      => ['src' => null, 'alt' => 'De Webgoeroe — aanpak op maat voor KMO\'s'],
                    'ctas'       => [
                        [
                            'label'     => 'Gratis gesprek inplannen',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                    ],
                ],
            ],

            // 4. Testimonials -----------------------------------------------
            [
                'section_type' => 'testimonials',
                'content' => [
                    'section_id' => null,
                    'background' => 'light',
                    'eyebrow'    => 'Wat klanten zeggen',
                    'heading'    => 'Ondernemers die al gegroeid zijn met onze systemen',
                    'items'      => [
                        [
                            'quote'   => 'De AI-assistent stond live binnen vijf dagen. Eerste week al drie afspraken ingepland terwijl ik werkte. Dat is pure omzet die ik vroeger miste.',
                            'author'  => 'Thomas B.',
                            'company' => 'Loodgieter, Brussel',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'Onze website haalt nu elke maand 40–50 nieuwe leads binnen via Google en Meta. Vroeger moesten we alles via mond-aan-mondreclame doen. Dat verschil is enorm.',
                            'author'  => 'Sofie V.',
                            'company' => 'Zaakvoerder, bouwbedrijf Mechelen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'De automatisering heeft mij elke week minstens vier uur teruggegeven. Die tijd steek ik nu in het werk zelf, niet in e-mails sturen en formulieren invullen.',
                            'author'  => 'Marc D.',
                            'company' => 'Aannemer, Hasselt',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                    ],
                ],
            ],

            // 5. FAQ --------------------------------------------------------
            [
                'section_type' => 'faq',
                'content' => [
                    'section_id' => 'faq',
                    'background' => 'dark',
                    'eyebrow'    => 'Veelgestelde vragen',
                    'heading'    => 'Alles over onze diensten',
                    'intro'      => null,
                    'items'      => [
                        [
                            'question' => 'Moet ik alle drie de systemen afnemen?',
                            'answer'   => '<p>Nee. De meeste klanten starten met één systeem en breiden later uit. We raden aan te starten met het systeem dat het snelste resultaat geeft voor jouw specifieke situatie — dat bepalen we samen in het gratis gesprek.</p>',
                        ],
                        [
                            'question' => 'Wat is de minimale looptijd van een samenwerking?',
                            'answer'   => '<p>Dat hangt af van het systeem. Een website is een eenmalig project. De AI-telefoonassistent en automatiseringen lopen op maandelijkse basis — je kan steeds maandelijks opzeggen na de eerste drie maanden. Geen langlopende contracten.</p>',
                        ],
                        [
                            'question' => 'Werken jullie enkel in België?',
                            'answer'   => '<p>We werken primair met Belgische KMO\'s en zelfstandigen — zowel Vlaanderen als Brussel. Voor uitzonderingen in het buitenland bekijken we het geval per geval.</p>',
                        ],
                        [
                            'question' => 'Hoe snel is een systeem live?',
                            'answer'   => '<p>De AI-telefoonassistent: gemiddeld binnen de week. Een website: 3 tot 4 weken. Business automation: 2 tot 4 weken afhankelijk van de complexiteit. Na het gratis gesprek geven we altijd een concrete timing.</p>',
                        ],
                        [
                            'question' => 'Hoe verloopt de samenwerking na livegang?',
                            'answer'   => '<p>Na livegang volgen we resultaten op, optimaliseren waar nodig en zijn we bereikbaar voor vragen. Je krijgt altijd een directe lijn met Pieter — geen ticketsysteem, geen callcenter.</p>',
                        ],
                    ],
                ],
            ],

            // 6. CTA --------------------------------------------------------
            [
                'section_type' => 'cta',
                'content' => [
                    'section_id' => null,
                    'background' => null,
                    'eyebrow'    => 'Gratis · Geen verplichtingen',
                    'heading'    => 'Welk systeem past bij jouw bedrijf?',
                    'intro'      => '<p>30 minuten. We analyseren jouw situatie en tonen precies welk systeem het meeste verschil maakt — inclusief een schatting van wat het jou oplevert.</p>',
                    'ctas'       => [
                        [
                            'label'     => 'Plan een gratis gesprek',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                    ],
                    'note' => 'Geen creditcard · Geen verkooppraatje · Antwoord binnen 24u',
                ],
            ],
        ];

        foreach ($sections as $position => $section) {
            $page->sections()->create([
                'section_type' => $section['section_type'],
                'position'     => $position,
                'content'      => $section['content'],
            ]);
        }

        $this->command->info('Diensten-overzichtspagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
