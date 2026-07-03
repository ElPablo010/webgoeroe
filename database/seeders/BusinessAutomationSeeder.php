<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class BusinessAutomationSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['locale' => 'nl', 'slug' => 'business-automation'],
            [
                'title'            => 'Business Automation',
                'is_homepage'      => false,
                'published'        => true,
                'meta_title'       => 'Business Automation — Stop met tijdverlies, automatiseer je processen | De Webgoeroe',
                'meta_description' => 'Verbind je tools, automatiseer herhalende taken en win elke week uren terug. Van automatische offerteflows tot facturatieautomatisering — implementatie in 2–4 weken.',
            ],
        );

        // Clear existing sections
        $page->sections()->delete();

        $sections = [
            // 1. Hero
            [
                'section_type' => 'hero',
                'position' => 0,
                'content' => [
                    'section_id' => null,
                    'eyebrow' => 'Business Automation voor KMO\'s en zelfstandigen',
                    'heading' => "Stop met herhalend werk.\nStart met ondernemen.",
                    'subtitle' => '<p>Elke minuut die jij of je team kwijt bent aan formulieren invullen, mails kopiëren of lijstjes bijhouden, is een minuut die je niet investeert in je klanten of je groei. Wij automatiseren die rompslomp — zodat jij je bedrijf kan leiden in plaats van beheren.</p>',
                    'image' => ['src' => null, 'alt' => null, 'position' => 'center 50%'],
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'url', 'href' => '/contact', 'variant' => 'primary'],
                        ['label' => 'Wat automatiseren wij?', 'link_type' => 'url', 'href' => '#wat-we-automatiseren', 'variant' => 'ghost'],
                    ],
                ],
            ],

            // 2. Probleem (text_media)
            [
                'section_type' => 'text_media',
                'position' => 1,
                'content' => [
                    'section_id' => 'het-probleem',
                    'background' => 'light',
                    'eyebrow' => 'Het probleem',
                    'heading' => 'Jij betaalt mensen om hetzelfde twee keer over te doen',
                    'intro' => '<p>Een nieuwe lead komt binnen via je website. Wat gebeurt er daarna? Iemand kopieert de gegevens manueel naar de CRM. Dan stuurt iemand anders een bevestigingsmail. Dan wordt er een taak aangemaakt. Dan een herinnering in de agenda gezet.</p><p>Vier manuele stappen voor wat eigenlijk één seconde werk zou moeten zijn. Vermenigvuldig dat met tien leads per dag, vijf offertes, twintig facturen, dertig notificaties — en je zit al snel aan uren per week puur administratief verlies.</p><p>Niet geteld: de fouten die opduiken wanneer mensen dezelfde data op meerdere plaatsen beheren. De vertraging wanneer de verkeerde persoon iets te laat doorstuurt. De klant die al drie dagen wacht op een bevestiging die niemand zag te sturen.</p><p><strong>Manueel werk schaalt niet. Automatisering wel.</strong></p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media' => ['src' => null, 'alt' => 'Manueel administratief werk stapelt zich op'],
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
                    'heading' => 'Jouw software praat met elkaar. Automatisch. Altijd.',
                    'intro' => '<p>Business automation koppelt jouw bestaande tools aan elkaar — je CRM, je mailprogramma, je boekhoudsoftware, je projecttool, je website — zodat informatie automatisch op de juiste plek terechtkomt.</p><ul><li>Een nieuwe aanvraag start automatisch een onboardingflow</li><li>Een goedgekeurde offerte genereert meteen een factuur</li><li>Een nieuwe klant krijgt direct de juiste welkomstmails</li><li>Je team wordt automatisch op de hoogte gebracht van nieuwe taken</li><li>Rapporten worden wekelijks automatisch samengesteld en verstuurd</li></ul><p>Wij bouwen die flows op maat — afgestemd op jouw processen, jouw tools, jouw sector. Geen generieke template, maar een systeem dat écht bij jou past.</p>',
                    'media_type' => 'image',
                    'media_side' => 'left',
                    'media' => ['src' => null, 'alt' => 'Automatische workflows verbinden je tools'],
                    'ctas' => [
                        ['label' => 'Bekijk onze aanpak', 'link_type' => 'url', 'href' => '#onze-aanpak', 'variant' => 'primary'],
                    ],
                ],
            ],

            // 4. Hoe het werkt (cards - 3 stappen)
            [
                'section_type' => 'cards',
                'position' => 3,
                'content' => [
                    'section_id' => 'onze-aanpak',
                    'background' => 'light',
                    'eyebrow' => 'Onze aanpak',
                    'heading' => 'Van manueel naar automatisch in drie stappen',
                    'intro' => '<p>We beginnen altijd met luisteren. Jij kent jouw processen het best — wij zorgen dat ze vlekkeloos lopen, zonder dat jij iets technisch hoeft te begrijpen.</p>',
                    'columns' => '3',
                    'max_visible' => null,
                    'cards' => [
                        [
                            'media_type' => 'icon',
                            'icon' => 'search',
                            'title' => 'We brengen jouw processen in kaart',
                            'subtitle' => 'Stap 1 — Analyse',
                            'description' => 'In één gesprek bespreken we welke taken je team dagelijks herhaalt. We detecteren waar tijd verloren gaat en waar automatisering het meeste oplevert.',
                            'features' => ['Intake van 60 minuten', 'Procesanalyse op maat', 'Prioriteitenplan met ROI-inschatting'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'git-branch',
                            'title' => 'We bouwen de automatisering',
                            'subtitle' => 'Stap 2 — Implementatie',
                            'description' => 'We koppelen je tools, bouwen de workflows en testen alles grondig. Jij hoeft niks te doen — tot we je de werkende automatisering tonen.',
                            'features' => ['Volledig door ons gebouwd', 'Stap-voor-stap getest', 'Live demo voor akkoord'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'trending-up',
                            'title' => 'Het systeem werkt. Jij groeit.',
                            'subtitle' => 'Stap 3 — Opvolging',
                            'description' => 'Na de lancering monitoren we de flows, lossen we eventuele drempels op en breiden we uit wanneer jij groeit. Geen technische kennis nodig aan jouw kant.',
                            'features' => ['Continue monitoring', 'Proactieve optimalisaties', 'Schaalbaar wanneer jij groeit'],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                    ],
                ],
            ],

            // 5. Wat we automatiseren (cards - 6 use cases als voordelen)
            [
                'section_type' => 'cards',
                'position' => 4,
                'content' => [
                    'section_id' => 'wat-we-automatiseren',
                    'background' => 'dark',
                    'eyebrow' => 'Wat we automatiseren',
                    'heading' => 'Meer tijd voor wat echt telt',
                    'intro' => '<p>We pakken de taken aan die jij of je team elke dag herhalen — van leadopvolging tot rapportage. Elk geautomatiseerd proces is uren die jij terugwint.</p>',
                    'columns' => '3',
                    'max_visible' => null,
                    'cards' => [
                        [
                            'media_type' => 'icon',
                            'icon' => 'user-check',
                            'title' => 'Nooit meer een lead die door de mazen glipt',
                            'subtitle' => 'Lead- & CRM-automatisering',
                            'description' => 'Elke aanvraag — van je website, sociale media of e-mail — wordt automatisch in je CRM geplaatst, gelabeld en opgevolgd. Jij focust op de gesprekken, niet op de administratie.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'file-text',
                            'title' => 'Offertes en facturen op het juiste moment',
                            'subtitle' => 'Offerte- & factuurflows',
                            'description' => 'Een goedgekeurde offerte genereert automatisch een factuur. Een vervaldatum triggert een betalingsherinnering. Jij int sneller, zonder achter mensen aan te bellen.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'bell',
                            'title' => 'Je team altijd op de hoogte, automatisch',
                            'subtitle' => 'Interne notificaties & taakverdeling',
                            'description' => 'Nieuwe klant? Taak aangemaakt. Deadline morgen? Herinnering verstuurd. Je team werkt op basis van de juiste info, op het juiste moment — zonder dat iemand dat manueel moet doorsturen.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'heart-handshake',
                            'title' => 'Klanten die zich herinnerd voelen',
                            'subtitle' => 'Klantopvolging & nurturing',
                            'description' => 'Na een aankoop, na een gesprek, na een bepaalde periode — jouw klanten ontvangen automatisch de juiste boodschap. Meer loyaliteit, meer herhaalaankopen, minder inspanning.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'bar-chart-2',
                            'title' => 'Rapporten zonder manueel werk',
                            'subtitle' => 'Automatische rapportage',
                            'description' => 'Wekelijks of maandelijks jouw cijfers — omzet, leads, prestaties — automatisch samengesteld en in je inbox. Je beslist op basis van data, niet op buikgevoel.',
                            'features' => [],
                            'cta_label' => null,
                            'link_type' => null,
                            'page_id' => null,
                        ],
                        [
                            'media_type' => 'icon',
                            'icon' => 'plug',
                            'title' => 'Al je tools werken samen als één geheel',
                            'subtitle' => 'Systeemintegraties & koppelingen',
                            'description' => 'We verbinden je website, CRM, boekhoudsoftware, planningssysteem en communicatietools — zodat data slechts één keer ingevoerd wordt en overal terecht komt.',
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
                    'heading' => "Voor wie zijn 's avonds nog met administratie bezig",
                    'intro' => '<p>Business automation is geen luxe voor grote bedrijven. Het is een noodzaak voor elke ondernemer die wil groeien zonder proportioneel meer mensen aan te nemen:</p><ul><li><strong>KMO-zaakvoerders</strong> die hun groei willen schalen zonder extra administratief personeel</li><li><strong>Teams van 2 tot 20 mensen</strong> die hetzelfde werk op meerdere plaatsen uitvoeren</li><li><strong>Zelfstandigen met een drukke agenda</strong> die leads en opvolging niet kunnen laten liggen</li><li><strong>Dienstenbedrijven</strong> (consultants, agencies, coaches, vrije beroepen) met repetitieve klantflows</li><li><strong>Webshops en e-commerce</strong> die bestellingen, voorraadbeheer en klantenservice willen stroomlijnen</li></ul><p>Als jij aan het einde van de dag nog uren kwijt bent aan dingen die een computer ook kan doen — dan is dit voor jou.</p>',
                    'media_type' => 'image',
                    'media_side' => 'right',
                    'media' => ['src' => null, 'alt' => 'Ondernemer die dankzij automatisering meer tijd heeft'],
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'url', 'href' => '/contact', 'variant' => 'primary'],
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
                    'heading' => 'Ondernemers die uren per week terugwonnen',
                    'items' => [
                        [
                            'quote' => 'We verloren elke week minstens acht uur aan het manueel verwerken van aanvragen. De Webgoeroe koppelde onze website, CRM en boekhoudprogramma aan elkaar. Nu loopt alles automatisch en heeft ons team eindelijk tijd voor de dingen die er echt toe doen.',
                            'author' => 'Stephanie V.',
                            'company' => 'Zaakvoerder, HR-consultancy',
                            'rating' => '5',
                            'avatar' => null,
                        ],
                        [
                            'quote' => 'Ik stuurde vroeger elke factuur manueel na, herinnerde klanten manueel aan hun afspraak en maakte elke maand handmatig een omzetrapport. Dat is nu allemaal geautomatiseerd. Ik bespaar minstens een halve dag per week — elke week.',
                            'author' => 'Thomas B.',
                            'company' => 'Zelfstandige coach, Brussel',
                            'rating' => '5',
                            'avatar' => null,
                        ],
                        [
                            'quote' => 'Onze webshop groeide te snel voor onze manuele processen. De Webgoeroe automatiseerde onze bestelverwerking, voorraadupdates en klantmails. Sindsdien verwerken we het dubbele aantal bestellingen met hetzelfde team.',
                            'author' => 'Karen D.',
                            'company' => 'Zaakvoerster, online winkel mode-accessoires',
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
                    'heading' => 'Alles wat je wil weten over business automation',
                    'intro' => '<p></p>',
                    'items' => [
                        [
                            'question' => 'Wat is business automation precies?',
                            'answer' => '<p>Business automation betekent dat software taken overneemt die jij of je team nu manueel uitvoert. Denk aan: een lead die binnenkomt via je website en automatisch in je CRM belandt, een goedgekeurde offerte die meteen een factuur genereert, of een klant die automatisch een herinneringsmail krijgt drie dagen voor zijn afspraak. Wij bouwen die verbindingen op maat — geen generieke apps, maar flows die aansluiten op hoe jij werkt.</p>',
                        ],
                        [
                            'question' => 'Ik ben niet technisch. Kan dat dan ook voor mij?',
                            'answer' => '<p>Absoluut. Wij doen het technische werk volledig voor jou. Jij hoeft enkel te vertellen hoe jouw processen er nu uitzien — de rest is aan ons. Na de oplevering beheer je alles via een eenvoudig dashboard, of je laat ons dat gewoon opvolgen. Technische kennis is niet vereist.</p>',
                        ],
                        [
                            'question' => 'Welke tools en software kunnen jullie koppelen?',
                            'answer' => '<p>We werken met meer dan 6.000 tools — van populaire software zoals HubSpot, Teamleader, Exact Online, Mollie, Mailchimp en Google Workspace tot nichesoftware in jouw sector. Via platformen zoals Make (voorheen Integromat) en directe API-koppelingen verbinden we vrijwel elk systeem dat een digitale verbinding ondersteunt. Twijfel je over jouw specifieke tools? Vraag het ons gerust — we kijken graag even mee.</p>',
                        ],
                        [
                            'question' => 'Wat kost business automation?',
                            'answer' => '<p>Dat hangt af van de complexiteit van je processen en het aantal koppelingen. We werken altijd met een transparant voorstel op maat — geen verborgen kosten, geen vage abonnementen. De meeste klanten verdienen de investering in minder dan drie maanden terug via de uren die ze besparen. Plan een gratis gesprek in en we geven je een eerlijke inschatting, zonder verplichtingen.</p>',
                        ],
                        [
                            'question' => 'Hoe snel is de automatisering live?',
                            'answer' => '<p>Voor een eenvoudige flow met twee à drie koppelingen zijn we doorgaans binnen twee weken live. Complexere projecten met meerdere systemen kunnen vier tot zes weken duren. We werken in fasen zodat jij snel resultaat ziet — de meest impactvolle automatisering lanceren we altijd eerst.</p>',
                        ],
                        [
                            'question' => 'Wat als er iets misgaat met een automatisering?',
                            'answer' => '<p>We bouwen alle flows met foutafhandeling in: als een stap mislukt, worden wij automatisch op de hoogte gebracht en lossen we het op voor jij er last van hebt. Je ontvangt ook een maandelijks rapport met de prestaties van jouw automatiseringen. Je bent nooit afhankelijk van iets wat je zelf niet begrijpt — wij houden de controle voor je.</p>',
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
                    'eyebrow' => 'Gratis · Zonder verplichtingen',
                    'heading' => 'Ontdek hoeveel tijd jij elke week kan besparen',
                    'intro' => '<p>In een gesprek van 30 minuten bespreken we jouw huidige processen en tonen we je concreet welk werk we kunnen automatiseren. Geen technisch jargon, geen verkooppraatje — gewoon een eerlijk advies over wat voor jou het meeste verschil maakt.</p>',
                    'ctas' => [
                        ['label' => 'Plan een gratis gesprek', 'link_type' => 'url', 'href' => '/contact', 'variant' => 'primary'],
                    ],
                    'note' => 'Geen creditcard · Eerlijk advies · Antwoord binnen 24u',
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

        $this->command->info('Business Automation pagina: ' . count($sections) . ' secties aangemaakt.');
    }
}
