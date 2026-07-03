<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Leadgeneratie-landingspagina: Gratis Digitale Scan
 *
 * Doel: bezoekers van advertenties omzetten in gekwalificeerde leads.
 * Conversie-ritme (geen navigatie-afleiding):
 * Hero → Wat je krijgt (cards) → Cijfers (cards) → Hoe het werkt (cards)
 * → Voor wie (text_media) → Testimonials → FAQ → Formulier/CTA
 */
class GratisDigitaleScanSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'gratis-digitale-scan'],
            [
                'title'            => 'Gratis Digitale Scan',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Gratis Digitale Scan — Ontdek hoeveel klanten jij misloopt | De Webgoeroe',
                'meta_description' => 'Ontvang gratis een persoonlijke analyse van jouw digitale aanwezigheid. We tonen je precies waar je klanten misloopt en geven je een concreet actieplan. 100% gratis, geen verplichtingen.',
                'meta_robots'      => 'noindex,follow',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero — grote belofte ---------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'section_id' => null,
                    'eyebrow'    => '100% gratis · Geen verplichtingen',
                    'heading'    => "Ontdek gratis hoeveel\nklanten jij nu misloopt —\nen krijg een concreet actieplan.",
                    'subtitle'   => '<p>In 30 minuten analyseren wij jouw volledige digitale aanwezigheid: jouw website, jouw bereikbaarheid en jouw concurrenten. Je ontvangt een persoonlijk rapport met de drie grootste verbeterpunten — gratis, zonder verplichtingen.</p>',
                    'image'      => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas'       => [
                        [
                            'label'     => 'Ja, ik wil mijn gratis scan',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '#scan-aanvragen',
                        ],
                    ],
                ],
            ],

            // 2. Wat je krijgt (cards) -------------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'wat-je-krijgt',
                    'background' => 'light',
                    'eyebrow'    => 'Wat je gratis krijgt',
                    'heading'    => 'Drie concrete deliverables — één gesprek van 30 minuten',
                    'intro'      => '<p>Geen algemeen rapport vol digitaal jargon. Drie specifieke, actiegerichte inzichten voor jouw bedrijf.</p>',
                    'columns'    => '3',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'search',
                            'title'       => 'Website-analyse op maat',
                            'subtitle'    => 'Deliverable 1',
                            'description' => 'We analyseren jouw website — of die van een concurrent als jij er nog geen hebt — op conversiepunten, laadsnelheid, SEO-fundament en mobiele ervaring.',
                            'features'    => [
                                'Conversiepunten geïdentificeerd',
                                'Technische SEO-beoordeling',
                                'Vergelijking met top-3 concurrenten',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'phone-missed',
                            'title'       => 'Bereikbaarheidsaudit',
                            'subtitle'    => 'Deliverable 2',
                            'description' => 'We testen hoe bereikbaar jouw bedrijf is voor potentiële klanten: reactietijd, telefonische beschikbaarheid, online aanwezigheid en verwerkingstijd van vragen.',
                            'features'    => [
                                'Test op 5 contactmomenten',
                                'Vergelijking met sector-benchmark',
                                'Concreet verliesgetal in omzet',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'lightbulb',
                            'title'       => 'Concreet actieplan',
                            'subtitle'    => 'Deliverable 3',
                            'description' => 'Je ontvangt de drie acties met het hoogste rendement voor jouw specifieke situatie — gerangschikt op impact en haalbaarheid, met een realistische ROI-schatting.',
                            'features'    => [
                                'Top-3 prioriteiten met ROI-schatting',
                                'Tijdlijn voor elk verbeterpunt',
                                'Eerlijke kosteninschatting',
                            ],
                            'cta_label' => null,
                            'link_type' => null,
                            'href'      => null,
                        ],
                    ],
                ],
            ],

            // 3. Sociale bewijs — cijfers (cards) ---------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'resultaten',
                    'background' => 'dark',
                    'eyebrow'    => 'Wat ondernemers ontdekten',
                    'heading'    => 'Resultaten na de gratis digitale scan',
                    'intro'      => '<p>Elke scan is anders — maar de patronen die we terugzien zijn verrassend consistent.</p>',
                    'columns'    => '3',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'phone-missed',
                            'title'       => 'Gemist per dag',
                            'subtitle'    => 'Gemiddeld 3–5 oproepen',
                            'description' => 'De meeste zelfstandigen die we spreken missen dagelijks 3 tot 5 oproepen van potentiële klanten — zonder dat ze het weten. Elke gemiste oproep is gemiddeld €300–€1.500 aan potentiële omzet.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'trending-down',
                            'title'       => 'Websiteconversie',
                            'subtitle'    => 'Gemiddeld 0,5–1,5%',
                            'description' => 'De gemiddelde Belgische KMO-website converteert minder dan 2% van zijn bezoekers. Na onze optimalisaties halen klanten gemiddeld 4–6% — drie tot vier keer meer leads uit hetzelfde verkeer.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'clock',
                            'title'       => 'Tijdverlies per week',
                            'subtitle'    => 'Gemiddeld 5–8 uur',
                            'description' => 'Zaakvoerders en zelfstandigen besteden gemiddeld 5 tot 8 uur per week aan herhalende administratieve taken die volledig geautomatiseerd kunnen worden.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                    ],
                ],
            ],

            // 4. Hoe het werkt (cards — 3 stappen) --------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'hoe-het-werkt',
                    'background' => 'light',
                    'eyebrow'    => 'Hoe het werkt',
                    'heading'    => 'In drie stappen naar jouw gratis digitale scan',
                    'intro'      => '<p>Minder dan 2 minuten om te starten. Jouw scan ontvang je binnen 48 uur na het gesprek.</p>',
                    'columns'    => '3',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'send',
                            'title'       => 'Vul het formulier in',
                            'subtitle'    => 'Stap 1 · 2 minuten',
                            'description' => 'Laat je naam, telefoonnummer en de URL van je website (als je die hebt) achter. Dat is alles. Geen lange vragenlijst.',
                            'features'    => ['Naam & telefoonnummer', 'Website-URL (optioneel)', 'Eventueel je sector'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'calendar-check',
                            'title'       => 'We plannen een gesprek in',
                            'subtitle'    => 'Stap 2 · Binnen 24u',
                            'description' => 'We nemen contact op om een moment van 30 minuten in te plannen dat jou past. Online, via Google Meet of Zoom — geen reistijd.',
                            'features'    => ['Antwoord binnen 24 uur', '30 minuten online', 'Op een moment dat jou past'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'file-check',
                            'title'       => 'Jij ontvangt je scan',
                            'subtitle'    => 'Stap 3 · Binnen 48u na gesprek',
                            'description' => 'Na het gesprek ontvang je jouw persoonlijke digitale scan per mail: drie prioriteiten, concreet actieplan en ROI-inschatting.',
                            'features'    => ['Persoonlijk rapport per mail', 'Top-3 prioriteiten', 'Concreet actieplan + ROI'],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                    ],
                ],
            ],

            // 5. Voor wie (text_media) --------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => 'voor-wie',
                    'background' => 'dark',
                    'eyebrow'    => 'Voor wie',
                    'heading'    => 'De gratis digitale scan is voor jou als...',
                    'intro'      => '<ul><li>Je een zelfstandige of KMO bent in België en lokale klanten bedient</li><li>Je het gevoel hebt dat je digitaal niet alles uit je bedrijf haalt maar niet weet waar te starten</li><li>Je dagelijks oproepen mist of klanten verliest aan concurrenten die sneller reageren</li><li>Je website nauwelijks leads oplevert ondanks dat er bezoekers op komen</li><li>Je te veel tijd verliest aan manuele, herhalende taken</li><li>Je wil groeien maar niet zomaar een grote investering wil doen zonder duidelijk beeld van wat het oplevert</li></ul><p>Als jij één of meer van die punten herkent, is deze scan voor jou gemaakt.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media'      => ['src' => null, 'alt' => 'Zelfstandige die zijn digitale situatie verbetert'],
                    'ctas'       => [
                        [
                            'label'     => 'Ja, ik wil mijn gratis scan',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '#scan-aanvragen',
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
                    'eyebrow'    => 'Ervaringen met de scan',
                    'heading'    => 'Wat ondernemers zeggen na hun digitale scan',
                    'items'      => [
                        [
                            'quote'   => 'Ik dacht dat mijn website OK was. Na de scan bleek dat ik elke week gemiddeld 15 oproepen miste en mijn website slechts 0,8% converteerde. Die twee dingen wisten we in drie maanden recht te zetten. Game changer.',
                            'author'  => 'Kevin D.',
                            'company' => 'Elektricien, Antwerpen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'De scan was niet wat ik verwachtte. Geen verkooppraatje maar echt een analyse. Pieter toonde me concreet welke concurrenten beter scoorden dan wij en waarom. Dat was confronterend maar enorm waardevol.',
                            'author'  => 'Sofie V.',
                            'company' => 'Zaakvoerder, bouwbedrijf Mechelen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'Het gratis gesprek duurde 35 minuten. De informatie die ik meekreeg was de afgelopen vijf jaar de meest waardevolle 35 minuten voor mijn bedrijf. Ik begrijp nu eindelijk hoe mijn digitale systeem werkt — of beter gezegd: hoe het niet werkte.',
                            'author'  => 'Marc D.',
                            'company' => 'Aannemer, Hasselt',
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
                    'heading'    => 'Over de gratis digitale scan',
                    'intro'      => null,
                    'items'      => [
                        [
                            'question' => 'Is de scan echt helemaal gratis?',
                            'answer'   => '<p>Ja. Er zijn geen verborgen kosten, geen kleine lettertjes en geen automatische betaling achteraf. De scan is gratis, het gesprek is gratis en het rapport is gratis. We doen dit omdat het de beste manier is om jou te laten zien hoe we werken — en of we een goede match zijn.</p>',
                        ],
                        [
                            'question' => 'Wat als ik nog geen website heb?',
                            'answer'   => '<p>Dan is de scan misschien nog waardevoller. We analyseren jouw concurrenten, bepalen wat jou onderscheidt en geven je een concreet beeld van wat een nieuwe website voor jou kan opbrengen — inclusief een eerlijke kosteninschatting om te starten.</p>',
                        ],
                        [
                            'question' => 'Ben ik verplicht om daarna iets te kopen?',
                            'answer'   => '<p>Absoluut niet. Na de scan ontvang je jouw rapport — en dan beslis jij volledig vrij wat je er mee doet. Je kan alles zelf uitvoeren, iemand anders inschakelen of met ons samenwerken. Wij sturen je nooit een agressief verkooppraatje. Als er geen match is, zeggen we dat gewoon.</p>',
                        ],
                        [
                            'question' => 'Hoe lang duurt het voor ik mijn rapport ontvang?',
                            'answer'   => '<p>We plannen het gesprek in binnen 24 uur na jouw aanvraag. Na het gesprek ontvang je jouw persoonlijk rapport binnen 48 uur per mail.</p>',
                        ],
                        [
                            'question' => 'Hoeveel van mijn tijd vraagt dit?',
                            'answer'   => '<p>Het formulier invullen duurt 2 minuten. Het gesprek duurt 30 minuten. Het rapport lees je wanneer het jou uitkomt. Totaal: minder dan 35 minuten voor een volledig inzicht in jouw digitale situatie.</p>',
                        ],
                    ],
                ],
            ],

            // 8. Formulier / CTA --------------------------------------------
            [
                'section_type' => 'form',
                'content' => [
                    'section_id'  => 'scan-aanvragen',
                    'background'  => 'light',
                    'eyebrow'     => '100% gratis · Geen verplichtingen',
                    'heading'     => 'Vraag nu je gratis digitale scan aan',
                    'intro'       => '<p>Laat je gegevens achter en we nemen binnen 24 uur contact op om een gesprek in te plannen op een moment dat jou past.</p><p><strong>Wat we nodig hebben:</strong></p><ul><li>Jouw naam en telefoonnummer</li><li>De URL van je website (als je die hebt)</li><li>Optioneel: de grootste digitale uitdaging van jouw bedrijf</li></ul>',
                    'form_type'   => 'contact',
                    'form_layout' => 'right',
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

        $this->command->info('Gratis Digitale Scan landingspagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
