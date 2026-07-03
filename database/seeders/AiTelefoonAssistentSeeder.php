<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

class AiTelefoonAssistentSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::where('slug', 'ai-telefoonassistenten')->firstOrFail();

        // Clear existing sections
        $page->sections()->delete();

        $sections = [
            // 1. Hero
            [
                'section_type' => 'hero',
                'position' => 0,
                'content' => [
                    'section_id' => null,
                    'eyebrow' => 'AI Telefoonassistent',
                    'heading' => 'Nooit meer een gemiste klant omdat jij niet opneemt.',
                    'subtitle' => '<p>Jouw AI-telefoonassistent staat 24/7 klaar. Hij neemt op, beantwoordt vragen, plant afspraken in en bezorgt je daarna een overzicht — terwijl jij gewoon doorwerkt.</p>',
                    'image' => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'page', 'page_id' => 8, 'variant' => 'primary'],
                        ['label' => 'Hoe werkt het?', 'link_type' => 'url', 'href' => '#hoe-het-werkt', 'variant' => 'ghost'],
                    ],
                ],
            ],

            // 2. Probleem (text_media)
            [
                'section_type' => 'text_media',
                'position' => 1,
                'content' => [
                    'section_id' => 'probleem',
                    'background' => 'light',
                    'eyebrow' => 'Het probleem',
                    'heading' => 'Elke gemiste oproep kost je een klant',
                    'intro' => '<p>Als zelfstandige of KMO heb je het druk. Je staat op de werf, zit bij een klant of bent gewoon niet bereikbaar. Ondertussen gaat je telefoon — maar je kan niet opnemen.</p><p>Wat doet die beller? Hij belt de concurrent. En die concurrent neemt <em>wel</em> op.</p><p>Gemiddeld belt een nieuwe klant maar één of twee keer voor hij afhaakt. Elke gemiste oproep is geen gemist gesprek — het is een gemiste opdracht, een gemiste factuur.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media' => ['src' => null, 'alt' => 'Gemiste oproepen kosten omzet'],
                    'ctas' => [],
                ],
            ],

            // 3. Oplossing (text_media)
            [
                'section_type' => 'text_media',
                'position' => 2,
                'content' => [
                    'section_id' => null,
                    'background' => 'dark',
                    'eyebrow' => 'De oplossing',
                    'heading' => 'Jouw AI-assistent neemt op. Altijd. 24/7.',
                    'intro' => '<p>De AI-telefoonassistent van De Webgoeroe staat dag en nacht paraat. Hij begroet bellers professioneel in jouw naam, begrijpt wat ze nodig hebben en helpt hen direct verder — zonder dat jij erbij moet zijn.</p><ul><li>Afspraken worden automatisch ingepland in jouw agenda</li><li>Vragen over jouw diensten worden direct beantwoord</li><li>Offerteaanvragen worden meteen doorgestuurd naar jou</li><li>Na elk gesprek ontvang je een overzicht per e-mail of sms</li></ul>',
                    'media_type' => 'image',
                    'media_side' => 'left',
                    'media' => ['src' => null, 'alt' => 'AI-telefoonassistent in actie'],
                    'ctas' => [
                        ['label' => 'Bekijk onze aanpak', 'link_type' => 'url', 'href' => '#hoe-het-werkt', 'variant' => 'primary'],
                    ],
                ],
            ],

            // 4. Hoe het werkt (cards - 3 stappen)
            [
                'section_type' => 'cards',
                'position' => 3,
                'content' => [
                    'section_id' => 'hoe-het-werkt',
                    'background' => 'light',
                    'eyebrow' => 'Hoe het werkt',
                    'heading' => 'Live in minder dan een week',
                    'intro' => '<p>Geen technische kennis nodig. Wij regelen de volledige setup — jij kan al snel genieten van een assistent die nooit pauze neemt.</p>',
                    'columns' => '3',
                    'max_visible' => null,
                    'cards' => [
                        [
                            'media_type' => 'icon',
                            'icon' => 'settings-2',
                            'title' => 'Setup in één sessie',
                            'subtitle' => 'Stap 1',
                            'description' => 'We leren de assistent alles over jouw bedrijf, diensten en beschikbaarheid. Eén gesprek van een uur is genoeg.',
                            'features' => ['Persoonlijk afgestemd op jouw bedrijf', 'Jouw toon en stijl', 'Live binnen de week'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'phone-incoming',
                            'title' => 'Beller wordt direct geholpen',
                            'subtitle' => 'Stap 2',
                            'description' => 'Jouw AI-assistent neemt op in jouw naam, helpt de beller verder en plant indien nodig een afspraak in — vriendelijk en professioneel.',
                            'features' => ['24/7 bereikbaar', 'Geen wachttijd voor de beller', 'Meerdere gesprekken tegelijk'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'bell-ring',
                            'title' => 'Jij ontvangt het overzicht',
                            'subtitle' => 'Stap 3',
                            'description' => 'Na elk gesprek ontvang je een samenvatting. Ingeplande afspraken verschijnen automatisch in je agenda. Nooit iets over het hoofd.',
                            'features' => ['Samenvatting per gesprek', 'Automatische agenda-koppeling', 'Direct actie ondernemen'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                    ],
                ],
            ],

            // 5. Voordelen (cards - 6 benefits)
            [
                'section_type' => 'cards',
                'position' => 4,
                'content' => [
                    'section_id' => 'voordelen',
                    'background' => 'dark',
                    'eyebrow' => 'Voordelen',
                    'heading' => 'Wat je er dagelijks aan hebt',
                    'intro' => '<p>Meer dan alleen oproepen beantwoorden — jouw AI-assistent werkt als een voltijdse receptionist, tegen een fractie van de kost.</p>',
                    'columns' => '3',
                    'max_visible' => null,
                    'cards' => [
                        [
                            'media_type' => 'icon',
                            'icon' => 'clock',
                            'title' => 'Altijd bereikbaar',
                            'subtitle' => '24/7 · 365 dagen per jaar',
                            'description' => '\'s Avonds, in het weekend, op feestdagen. Jouw assistent slaapt nooit — ook niet wanneer jij dat wel doet.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'trending-up',
                            'title' => 'Meer omzet, minder verlies',
                            'subtitle' => 'ROI van dag één',
                            'description' => 'Eén extra opdracht per maand dekt de kost al ruimschoots. Elke oproep die je vroeger miste, is nu potentiële omzet.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'calendar-check',
                            'title' => 'Automatische planning',
                            'subtitle' => 'Zero manueel werk',
                            'description' => 'Afspraken worden ingepland in jouw agenda zonder dat jij ook maar één bericht hoeft te sturen of terug te bellen.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'smile',
                            'title' => 'Professionele indruk',
                            'subtitle' => 'Altijd vlekkeloos',
                            'description' => 'Bellers worden altijd vriendelijk en professioneel begroet — ook op jouw drukste dag of wanneer je moe bent.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'zap',
                            'title' => 'Geen extra personeel',
                            'subtitle' => 'Slim besparen',
                            'description' => 'De AI-assistent doet het werk van een voltijdse receptioniste — voor een fractie van de kost, zonder sociale lasten of vakantie.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'shield-check',
                            'title' => 'Volledig op maat',
                            'subtitle' => 'Jij bepaalt de regels',
                            'description' => 'Jij beslist wat de assistent zegt, welke info hij deelt en wanneer hij jou doorverbindt. Aanpassen is altijd mogelijk.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                    ],
                ],
            ],

            // 6. Voor wie (text_media)
            [
                'section_type' => 'text_media',
                'position' => 5,
                'content' => [
                    'section_id' => 'voor-wie',
                    'background' => 'light',
                    'eyebrow' => 'Voor wie',
                    'heading' => 'Ideaal voor elke zelfstandige die zijn telefoon niet altijd kan opnemen',
                    'intro' => '<p>De AI-telefoonassistent werkt voor <em>elke</em> ondernemer die niet fulltime achter zijn bureau zit:</p><ul><li><strong>Bouwvakkers en vaklui</strong> die met hun handen werken en niet midden in een klus kunnen opnemen</li><li><strong>Coaches en therapeuten</strong> die tijdens sessies ongestoord willen werken</li><li><strong>KMO\'s</strong> waar de zaakvoerder ook de receptionist, de accountmanager en de uitvoerder is</li><li><strong>Vrije beroepen</strong> zoals advocaten, notarissen, architecten die focus nodig hebben</li><li><strong>Zorgverleners</strong> die tijdens behandelingen niet bereikbaar mogen zijn</li></ul><p>Kort samengevat: als jij je telefoon niet altijd kan of wil opnemen, is de AI-assistent jouw oplossing.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media' => ['src' => null, 'alt' => 'Zelfstandige op de werf — bereikbaar via AI-assistent'],
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'page', 'page_id' => 8, 'variant' => 'primary'],
                    ],
                ],
            ],

            // 7. Testimonials
            [
                'section_type' => 'testimonials',
                'position' => 6,
                'content' => [
                    'section_id' => null,
                    'background' => 'dark',
                    'eyebrow' => 'Wat zeggen onze klanten?',
                    'heading' => 'Ondernemers die nooit meer een oproep missen',
                    'items' => [
                        [
                            'quote' => 'Vroeger miste ik elke dag minstens drie oproepen terwijl ik op de werf werkte. Nu plant de AI-assistent van De Webgoeroe mijn afspraken automatisch in. Ik heb er vorige maand twee nieuwe klanten mee binnengehaald.',
                            'author' => 'Kevin D.',
                            'company' => 'Elektricien, Antwerpen',
                            'rating' => '5',
                            'avatar' => null,
                        ],
                        [
                            'quote' => 'Tijdens mijn sessies kon ik nooit opnemen. Klanten haakten af of vonden het onprofessioneel. Sindsdien de AI-assistent live staat, verlies ik geen enkele lead meer. Hij plant consultaties direct in mijn agenda.',
                            'author' => 'An V.',
                            'company' => 'Coach & therapeute, Gent',
                            'rating' => '5',
                            'avatar' => null,
                        ],
                        [
                            'quote' => 'Als zaakvoerder ben ik te veel bezig om altijd op te nemen. De assistent filtert de oproepen, beantwoordt standaardvragen en geeft mij alleen door wat echt mijn aandacht vraagt. Ideaal.',
                            'author' => 'Joris M.',
                            'company' => 'Zaakvoerder, schrijnwerkerij Gent',
                            'rating' => '5',
                            'avatar' => null,
                        ],
                    ],
                ],
            ],

            // 8. FAQ
            [
                'section_type' => 'faq',
                'position' => 7,
                'content' => [
                    'section_id' => 'faq',
                    'background' => 'light',
                    'eyebrow' => 'Veelgestelde vragen',
                    'heading' => 'Alles wat je wil weten over de AI-assistent',
                    'intro' => '<p></p>',
                    'items' => [
                        [
                            'question' => 'Klinkt het als een robot?',
                            'answer' => '<p>Nee. De stem klinkt natuurlijk en professioneel. Je kan kiezen uit verschillende stemmen, en we stemmen de toon, het script en de stijl volledig af op jouw bedrijf. De meeste bellers merken het verschil niet met een echte receptioniste.</p>',
                        ],
                        [
                            'question' => 'Wat als de beller iets vraagt wat de assistent niet weet?',
                            'answer' => '<p>De assistent geeft vriendelijk aan dat hij jou op de hoogte stelt en dat je zo snel mogelijk terugbelt. Jij ontvangt onmiddellijk een notificatie met de vraag. Geen enkele beller staat met lege handen.</p>',
                        ],
                        [
                            'question' => 'Hoe snel is de AI-assistent live?',
                            'answer' => '<p>Gemiddeld binnen de week. We regelen alles: de technische setup, de persoonlijke training van de assistent op jouw bedrijf en een testfase zodat alles vlekkeloos loopt voor we live gaan.</p>',
                        ],
                        [
                            'question' => 'Kan ik de assistent zelf aanpassen?',
                            'answer' => '<p>Ja. Via een eenvoudig dashboard beheer je scripts, beschikbaarheid en doorschakelregels. En als je iets wil wijzigen maar niet zeker bent hoe? Wij helpen je er altijd mee. Geen technische kennis nodig.</p>',
                        ],
                        [
                            'question' => 'Werkt dit ook voor WhatsApp of chat?',
                            'answer' => '<p>De AI-telefoonassistent focust op telefonische oproepen. Wil je ook chat, e-mail of WhatsApp automatiseren? Dat regelen we via onze <a href="/business-automation">Business Automation</a>-diensten — vraag het zeker even tijdens het gesprek.</p>',
                        ],
                        [
                            'question' => 'Hoeveel kost de AI-telefoonassistent?',
                            'answer' => '<p>De prijs hangt af van het volume oproepen en de gewenste functies. We werken liever met een voorstel op maat dan met vage forfaits. Plan een gratis gesprek in — we leggen alles helder uit, zonder verborgen kosten of verplichtingen.</p>',
                        ],
                    ],
                ],
            ],

            // 9. CTA
            [
                'section_type' => 'cta',
                'position' => 8,
                'content' => [
                    'section_id' => null,
                    'background' => null,
                    'eyebrow' => 'Gratis · Geen verplichtingen',
                    'heading' => 'Klaar om nooit meer een oproep te missen?',
                    'intro' => '<p>Plan een gratis gesprek van 30 minuten. We bespreken jouw situatie, tonen je live hoe de AI-assistent werkt en maken een voorstel op maat — zonder druk en zonder verkooppraatje.</p>',
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'page', 'page_id' => 8, 'variant' => 'primary'],
                    ],
                    'note' => 'Geen creditcard · Live demo tijdens het gesprek · Antwoord binnen 24u',
                ],
            ],
        ];

        foreach ($sections as $data) {
            $page->sections()->create([
                'section_type' => $data['section_type'],
                'position' => $data['position'],
                'locale' => 'nl',
                'content' => $data['content'],
            ]);
        }

        $this->command->info('AI Telefoonassistent pagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
