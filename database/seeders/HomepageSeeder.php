<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Homepage van De Webgoeroe — conversie-gericht ritme:
 * Hero → 3 Pijlers (cards) → Testimonials → FAQ → CTA
 */
class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'home'],
            [
                'title'            => 'Home',
                'is_homepage'      => true,
                'published'        => true,
                'meta_title'       => 'De Webgoeroe — Meer klanten. Minder gemiste oproepen. Minder rompslomp.',
                'meta_description' => 'De Webgoeroe helpt KMO\'s en zelfstandigen met websites die converteren, een AI-assistent die telefoons opneemt, en automatisering die je administratie verlicht.',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero -------------------------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'eyebrow'  => 'Digitale groei voor KMO\'s en vaklui',
                    'heading'  => "Meer klanten.\nMinder gemiste oproepen.\nMinder rompslomp.",
                    'subtitle' => '<p>De Webgoeroe helpt zelfstandigen en KMO\'s groeien met slimme digitale systemen — van een website die verkoopt tot een AI-assistent die jouw telefoon opneemt terwijl jij op de werf staat.</p>',
                    'ctas'     => [
                        [
                            'label'     => 'Gratis gesprek inplannen',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                        [
                            'label'     => 'Ontdek onze diensten',
                            'variant'   => 'ghost',
                            'link_type' => 'url',
                            'href'      => '/diensten',
                        ],
                    ],
                ],
            ],

            // 2. Cards — 3 pijlers ------------------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'background' => 'light',
                    'eyebrow'    => 'Wat we voor jou doen',
                    'heading'    => 'Drie systemen. Één doel: jouw bedrijf laten groeien.',
                    'intro'      => '<p>We pakken de drie grootste pijnpunten aan voor zelfstandigen en KMO\'s — stap voor stap, op jouw tempo.</p>',
                    'columns'    => '3',
                    'cards'      => [
                        [
                            'title'       => 'Nooit meer een gemiste klant',
                            'icon'        => 'phone-call',
                            'media_type'  => 'icon',
                            'subtitle'    => 'AI-telefoonassistent',
                            'description' => 'Elke oproep beantwoord, elke vraag opgelost, elke afspraak ingepland — ook als jij op de werf staat of je handen vol hebt.',
                            'features'    => [
                                'Neemt oproepen op 24/7',
                                'Plant afspraken automatisch in',
                                'Stuurt offerteaanvragen door',
                                'Herinnert leads aan gemaakte afspraken',
                            ],
                            'cta_label' => 'Meer weten',
                            'href'      => '/diensten/nooit-meer-een-gemiste-klant',
                        ],
                        [
                            'title'       => 'Meer leads & klanten',
                            'icon'        => 'trending-up',
                            'media_type'  => 'icon',
                            'subtitle'    => 'Websites & leadgeneratie',
                            'description' => 'Een website die bezoekers omzet in betalende klanten. Aangevuld met funnels en advertenties die jouw ideale klant bereiken.',
                            'features'    => [
                                'Converterende website op maat',
                                'Meta-advertenties',
                                'Landingpagina\'s & funnels',
                                'Conversie-optimalisatie',
                            ],
                            'cta_label' => 'Meer weten',
                            'href'      => '/diensten/meer-leads-en-klanten',
                        ],
                        [
                            'title'       => 'Minder administratie',
                            'icon'        => 'settings-2',
                            'media_type'  => 'icon',
                            'subtitle'    => 'Business automation',
                            'description' => 'Verbind je tools, automatiseer herhalende taken en krijg overzicht over je bedrijf — zonder dat je er zelf tijd in moet steken.',
                            'features'    => [
                                'Koppelingen tussen tools',
                                'Offerte- en facturatieflow',
                                'Interne processen automatiseren',
                                'Dashboards op maat',
                            ],
                            'cta_label' => 'Meer weten',
                            'href'      => '/diensten/minder-administratie',
                        ],
                    ],
                ],
            ],

            // 3. Testimonials -----------------------------------------------
            [
                'section_type' => 'testimonials',
                'content' => [
                    'eyebrow'    => 'Wat klanten zeggen',
                    'heading'    => 'Resultaten die spreken voor zich',
                    'items'      => [
                        [
                            'quote'   => 'Vroeger miste ik elke dag minstens drie oproepen terwijl ik op de werf werkte. Nu plant de AI-assistent van De Webgoeroe mijn afspraken automatisch in. Ik heb er vorige maand twee nieuwe klanten mee binnengehaald.',
                            'author'  => 'Kevin D.',
                            'company' => 'Elektricien, Antwerpen',
                            'rating'  => '5',
                        ],
                        [
                            'quote'   => 'Onze nieuwe website converteert drie keer beter dan de oude. Pieter heeft echt meegedacht over wat onze klanten nodig hebben, niet gewoon een mooie pagina gemaakt.',
                            'author'  => 'Sofie V.',
                            'company' => 'Zaakvoerder, bouwbedrijf Mechelen',
                            'rating'  => '5',
                        ],
                        [
                            'quote'   => 'Dankzij de automatisering steek ik geen tijd meer in het versturen van offerteherinneringen. Dat doet het systeem gewoon. Mijn opvolgingspercentage is verdubbeld.',
                            'author'  => 'Joris M.',
                            'company' => 'Schrijnwerker, Gent',
                            'rating'  => '5',
                        ],
                    ],
                ],
            ],

            // 4. FAQ --------------------------------------------------------
            [
                'section_type' => 'faq',
                'content' => [
                    'background' => 'light',
                    'eyebrow'    => 'Veelgestelde vragen',
                    'heading'    => 'Alles wat je wil weten',
                    'items'      => [
                        [
                            'question' => 'Voor welke bedrijven is De Webgoeroe geschikt?',
                            'answer'   => '<p>We werken hoofdzakelijk met zelfstandigen en KMO\'s in België — van schrijnwerkers en elektriciens tot coaches en vrije beroepen. Als je een bedrijf hebt dat lokale klanten bedient en wil groeien, kunnen we je helpen.</p>',
                        ],
                        [
                            'question' => 'Hoe snel zie ik resultaten?',
                            'answer'   => '<p>De AI-telefoonassistent is live binnen de week. Een nieuwe website is doorgaans klaar in twee tot vier weken. Resultaten in leads en conversies merk je al in de eerste maanden — we meten alles zodat je precies ziet wat het oplevert.</p>',
                        ],
                        [
                            'question' => 'Wat kost dit?',
                            'answer'   => '<p>De prijs hangt af van welk systeem je nodig hebt en hoe uitgebreid. We werken liever met een concreet voorstel op maat dan met vage forfaits. Plan een gratis gesprek in en we leggen alles helder uit — zonder verborgen kosten.</p>',
                        ],
                        [
                            'question' => 'Moet ik technisch zijn om dit te beheren?',
                            'answer'   => '<p>Absoluut niet. Alles wat we bouwen is bedoeld voor niet-techneuten. Je beheert je website via een eenvoudige admin, en de AI-assistent werkt volledig op de achtergrond zonder dat jij er iets voor moet doen.</p>',
                        ],
                        [
                            'question' => 'Kan ik starten met één systeem en later uitbreiden?',
                            'answer'   => '<p>Ja, dat is zelfs onze aanpak. De meeste klanten starten met de AI-telefoonassistent — dat is een klein, duidelijk probleem met een meetbaar resultaat. Van daaruit bouwen we verder op wat jou het meeste oplevert.</p>',
                        ],
                    ],
                ],
            ],

            // 5. CTA --------------------------------------------------------
            [
                'section_type' => 'cta',
                'content' => [
                    'eyebrow' => 'Gratis · Geen verplichtingen',
                    'heading' => 'Klaar om meer klanten te winnen?',
                    'intro'   => '<p>30 minuten. We bekijken jouw situatie live en tonen precies welk systeem het meeste verschil maakt voor jouw bedrijf — en wat het oplevert.</p>',
                    'ctas'    => [
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
    }
}
