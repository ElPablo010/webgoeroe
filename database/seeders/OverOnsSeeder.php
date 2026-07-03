<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Over De Webgoeroe-pagina — conversie-ritme:
 * Hero → Verhaal (text_media) → Aanpak (text_media) → Waarom anders (cards)
 * → Testimonials → FAQ → CTA
 */
class OverOnsSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'over-ons'],
            [
                'title'            => 'Over De Webgoeroe',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Over De Webgoeroe — Pieter Van Looy, digitale partner voor KMO\'s',
                'meta_description' => 'De Webgoeroe is Pieter Van Looy — een technische partner die meedenkt als ondernemer. AI-telefoonassistenten, converterende websites en business automation voor Belgische KMO\'s.',
            ],
        );

        $page->sections()->delete();

        $sections = [

            // 1. Hero -------------------------------------------------------
            [
                'section_type' => 'hero',
                'content' => [
                    'section_id' => null,
                    'eyebrow'    => 'Over De Webgoeroe',
                    'heading'    => "Pieter Van Looy —\nde technische partner die\nmeedenkt als ondernemer.",
                    'subtitle'   => '<p>Geen groot agency met een account manager die jouw dossier doorbladert voor elk gesprek. Wij zijn De Webgoeroe — een kleine, gespecialiseerde partner die diep investeert in jouw succes en persoonlijk bereikbaar is wanneer het telt.</p>',
                    'image'      => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas'       => [
                        [
                            'label'     => 'Gratis kennismakingsgesprek',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                        [
                            'label'     => 'Bekijk onze diensten',
                            'variant'   => 'ghost',
                            'link_type' => 'url',
                            'href'      => '/diensten',
                        ],
                    ],
                ],
            ],

            // 2. Verhaal (text_media) ---------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => 'verhaal',
                    'background' => 'light',
                    'eyebrow'    => 'Ons verhaal',
                    'heading'    => 'Gebouwd vanuit frustratie. Geworden tot een missie.',
                    'intro'      => '<p>Ik ben Pieter Van Looy, oprichter van De Webgoeroe. Na jaren in de digitale wereld zag ik keer op keer hetzelfde patroon: zelfstandigen en KMO\'s in België die fantastisch werk leveren, maar digitaal achterop hinken — niet door gebrek aan ambitie, maar door gebrek aan de juiste partner.</p><p>Ze hadden een website laten bouwen die er mooi uitzag maar niets opbracht. Ze betaalden voor advertenties zonder te weten of het werkte. Ze misten dagelijks klanten omdat ze hun telefoon niet konden opnemen. En hun administratie vraat hen op.</p><p>De Webgoeroe is mijn antwoord op die realiteit: een partner die niet enkel bouwt wat gevraagd wordt, maar meedenkt over wat jou écht verder helpt. Geen trends-van-de-week, geen vage digitaliseringsrapporten — gewoon concrete systemen met meetbare resultaten.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media'      => ['src' => null, 'alt' => 'Pieter Van Looy — oprichter De Webgoeroe'],
                    'ctas'       => [],
                ],
            ],

            // 3. Aanpak (text_media) ----------------------------------------
            [
                'section_type' => 'text_media',
                'content' => [
                    'section_id' => 'aanpak',
                    'background' => 'dark',
                    'eyebrow'    => 'Onze aanpak',
                    'heading'    => 'Systemen, geen opdrachten',
                    'intro'      => '<p>Veel digitale bureaus werken per opdracht: ze bouwen wat je vraagt en verdwijnen daarna. Wij werken anders.</p><p>Wij denken in systemen: elk project dat we opstarten is een onderdeel van een groter geheel dat jouw bedrijf structureel verder helpt. We starten met het meest impactvolle onderdeel — het probleem dat jou nu de meeste omzet of tijd kost — en bouwen van daaruit verder.</p><ul><li><strong>We meten alles.</strong> Elk systeem dat we bouwen heeft een duidelijk meetpunt. Je weet altijd wat het oplevert.</li><li><strong>We zijn eerlijk.</strong> Als iets niet past voor jouw bedrijf, zeggen we dat. We verkopen je geen overbodige diensten.</li><li><strong>We zijn bereikbaar.</strong> Geen ticketsysteem, geen wachtrij. Een directe lijn met Pieter via mail, telefoon of WhatsApp.</li></ul>',
                    'media_type' => 'image',
                    'media_side' => 'left',
                    'media'      => ['src' => null, 'alt' => 'De Webgoeroe — aanpak gericht op systemen en resultaten'],
                    'ctas'       => [
                        [
                            'label'     => 'Bekijk onze diensten',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/diensten',
                        ],
                    ],
                ],
            ],

            // 4. Waarom anders (cards) --------------------------------------
            [
                'section_type' => 'cards',
                'content' => [
                    'section_id' => 'waarom-anders',
                    'background' => 'light',
                    'eyebrow'    => 'Waarom anders',
                    'heading'    => 'Wat De Webgoeroe anders maakt',
                    'intro'      => '<p>Er zijn honderden webbureau\'s. Dit is waarom klanten voor ons kiezen en blijven.</p>',
                    'columns'    => '3',
                    'max_visible' => null,
                    'cards'      => [
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'user',
                            'title'       => 'Eén aanspreekpunt',
                            'subtitle'    => 'Altijd Pieter',
                            'description' => 'Geen account manager die jouw dossier overdraagt. Jij werkt altijd rechtstreeks met Pieter — de persoon die jouw project bouwt en jouw situatie kent.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'bar-chart-2',
                            'title'       => 'Altijd meetbaar',
                            'subtitle'    => 'Resultaat voor budget',
                            'description' => 'Elk systeem heeft een duidelijk meetpunt. Je weet exact hoeveel leads je website genereert, hoeveel oproepen de AI-assistent beantwoordt en hoeveel tijd automatisering bespaart.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'target',
                            'title'       => 'Eerlijk & direct',
                            'subtitle'    => 'Geen bullshit',
                            'description' => 'Als een dienst niet de moeite is voor jouw situatie, zeggen we dat. We verkopen je nooit iets wat je niet nodig hebt. Onze reputatie bouwt op eerlijkheid, niet op commissies.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'zap',
                            'title'       => 'Snel van start',
                            'subtitle'    => 'Resultaten in weken, niet maanden',
                            'description' => 'AI-assistent live in één week. Website live in 3–4 weken. Business automation live in 2–4 weken. Geen maandenlange trajecten — je ziet snel wat het oplevert.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'map-pin',
                            'title'       => 'Belgisch & lokaal',
                            'subtitle'    => 'Kent jouw markt',
                            'description' => 'We werken uitsluitend met Belgische KMO\'s en zelfstandigen. We kennen de lokale markt, de Belgische klant en de manier waarop Vlaamse ondernemers werken.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                        [
                            'media_type'  => 'icon',
                            'icon'        => 'refresh-cw',
                            'title'       => 'Langetermijnpartner',
                            'subtitle'    => 'Niet eenmalig, maar structureel',
                            'description' => 'We bouwen geen websites en verdwijnen daarna. We zijn er nog wanneer je wil uitbreiden, aanpassen of een nieuw systeem toevoegen. Een partner, geen leverancier.',
                            'features'    => [],
                            'cta_label'   => null,
                            'link_type'   => null,
                            'href'        => null,
                        ],
                    ],
                ],
            ],

            // 5. Testimonials -----------------------------------------------
            [
                'section_type' => 'testimonials',
                'content' => [
                    'section_id' => null,
                    'background' => 'dark',
                    'eyebrow'    => 'Wat klanten zeggen',
                    'heading'    => 'Partnerschappen die werken',
                    'items'      => [
                        [
                            'quote'   => 'Het grootste verschil met andere bureaus: Pieter denkt écht mee. Hij stelde zelf voor om te starten met de AI-assistent in plaats van een nieuwe website — dat was de juiste keuze en dat weet hij.',
                            'author'  => 'Kevin D.',
                            'company' => 'Elektricien, Antwerpen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'We hadden al twee andere bureaus geprobeerd. Mooie websites, maar het bracht niets op. Met De Webgoeroe is dat anders: alles is gericht op resultaat, en we zien dat ook in de cijfers.',
                            'author'  => 'Sofie V.',
                            'company' => 'Zaakvoerder, bouwbedrijf Mechelen',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                        [
                            'quote'   => 'Ik stuurde een berichtje op een zaterdagavond en had een antwoord voor het weekend voorbij was. Dat vind je nergens anders bij een bureau. En het werk klopt ook gewoon.',
                            'author'  => 'An V.',
                            'company' => 'Coach & therapeute, Gent',
                            'rating'  => '5',
                            'avatar'  => null,
                        ],
                    ],
                ],
            ],

            // 6. FAQ --------------------------------------------------------
            [
                'section_type' => 'faq',
                'content' => [
                    'section_id' => 'faq',
                    'background' => 'light',
                    'eyebrow'    => 'Veelgestelde vragen',
                    'heading'    => 'Vragen over De Webgoeroe',
                    'intro'      => null,
                    'items'      => [
                        [
                            'question' => 'Is De Webgoeroe een freelancer of een bedrijf?',
                            'answer'   => '<p>De Webgoeroe is een eenmanszaak geleid door Pieter Van Looy. Voor gespecialiseerde taken (bv. copywriting, fotografie) werken we samen met een netwerk van vertrouwde freelancers — altijd onder begeleiding van Pieter als jouw centrale aanspreekpunt.</p>',
                        ],
                        [
                            'question' => 'Werken jullie met bedrijven uit alle sectoren?',
                            'answer'   => '<p>We werken het liefst met KMO\'s en zelfstandigen die lokale klanten bedienen: bouw, technische installaties, zorg, vrije beroepen, coaching, retail en dienstverleners. We zeggen eerlijk als een sector niet bij ons past.</p>',
                        ],
                        [
                            'question' => 'Wat als ik al samenwerk met een ander bureau?',
                            'answer'   => '<p>Geen probleem. We werken graag samen met bestaande partners. We zijn ook eerlijk als iets wat een ander al voor jou doet goed genoeg is — we proberen dat niet te vervangen als het werkt.</p>',
                        ],
                        [
                            'question' => 'In welke regio\'s werken jullie?',
                            'answer'   => '<p>We werken voor bedrijven in heel België. Onze klanten zitten van Antwerpen tot Leuven, van Gent tot Brussel. De meeste gesprekken doen we online — efficiënter voor jou én voor ons.</p>',
                        ],
                    ],
                ],
            ],

            // 7. CTA --------------------------------------------------------
            [
                'section_type' => 'cta',
                'content' => [
                    'section_id' => null,
                    'background' => null,
                    'eyebrow'    => 'Gratis · Geen verplichtingen',
                    'heading'    => 'Laten we kennismaken',
                    'intro'      => '<p>30 minuten. We bespreken jouw situatie, je digitale uitdagingen en welk systeem het meeste verschil zou maken voor jouw bedrijf. Geen verkooppraatje — gewoon een eerlijk gesprek.</p>',
                    'ctas'       => [
                        [
                            'label'     => 'Plan een gratis gesprek',
                            'variant'   => 'primary',
                            'link_type' => 'url',
                            'href'      => '/contact',
                        ],
                    ],
                    'note' => 'Geen creditcard · Geen verplichtingen · Antwoord binnen 24u',
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

        $this->command->info('Over De Webgoeroe pagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
