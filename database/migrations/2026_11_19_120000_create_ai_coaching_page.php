<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\AiCoaching;
use Illuminate\Database\Migrations\Migration;
use Webgoeroe\Core\Agenda\Agenda;
use Webgoeroe\Core\Models\WebsiteMedia;
use Webgoeroe\Core\Support\Modules;

/**
 * Pagina /ai-coaching: 1-op-1 AI-coaching in de ik-vorm (Pieter, beslist 19/11/2026).
 * Tekst letterlijk uit het conversieplan (WebGoeroe/assets/conversieplannen/ai-coaching),
 * met enkel bestaande blokken.
 *
 * - De pagina staat op CONCEPT. Ze mag pas publiek als betalen bij het boeken werkt
 *   (anders boekt iemand een startsessie zonder te betalen). Dat is een aparte taak.
 * - Het menu-item "AI-coaching" onder Diensten staat er al, maar de header toont een
 *   item naar een concept-pagina niet: het verschijnt vanzelf bij het publiceren.
 * - De agenda is de eigen agenda van de core, met twee soorten afspraak: Startsessie
 *   (120 min) en Vervolguur (60 min). Elk soort is een eigen boeking-blok; de twee
 *   wisselen af onder twee knoppen ("Wisselen met een ander blok"), de startsessie staat
 *   voorgeselecteerd. De beschikbare uren (Instellingen → Agenda) zijn Pieters keuze en
 *   zet deze migratie niet.
 * - Op /ai-automatisering-zelfstandigen, /ai-automatisering-kmo en /business-automation
 *   komt onder de slotknop "Liever zelf leren? Bekijk onze AI-coaching." De regel
 *   verschijnt pas als /ai-coaching gepubliceerd is.
 *
 * Bestaat de pagina al, of heeft de site nog geen inhoud (verse database), dan komt de
 * pagina er niet bij.
 */
return new class extends Migration
{
    private const PHOTO_FILE = 'pieter-bureau-liggend.jpg';

    /** Liggende foto van Pieter (staat al op de site, o.a. in de landingspagina's). */
    private const PHOTO_SOURCE = 'resources/images/landing/pieter-bureau-liggend.jpg';

    public function up(): void
    {
        $this->setUpAgenda();

        if (Page::query()->where('slug', AiCoaching::SLUG)->exists()
            || ! Page::query()->where('slug', 'bottleneck-scan')->exists()) {
            return;
        }

        $photo = $this->photo('Pieter Van Looy aan zijn bureau, waar hij met Claude werkt');

        $page = Page::query()->create([
            'title' => 'AI-coaching',
            'slug' => AiCoaching::SLUG,
            'locale' => 'nl',
            'published' => false,
            'meta_title' => "AI-coach voor zelfstandigen en kmo's | De WebGoeroe",
            'meta_description' => '1-op-1 AI-coaching voor ondernemers in Vlaanderen. We zetten Claude samen op en automatiseren je eerste taak. Startsessie € 250, daarna € 75 per uur.',
            'meta_robots' => 'index, follow',
        ]);

        foreach ($this->sections($photo) as $position => [$type, $content]) {
            $page->sections()->create([
                'section_type' => $type,
                'position' => $position,
                'locale' => 'nl',
                'content' => $content,
            ]);
        }

        $this->addMenuItem($page);
        $this->addInternalLinks($page);
    }

    public function down(): void
    {
        Page::query()->where('slug', AiCoaching::SLUG)->get()->each(function (Page $page): void {
            MenuItem::query()->where('page_id', $page->id)->delete();
            $page->sections()->delete();
            $page->delete();
        });
    }

    /** Eigen agenda aanzetten en de twee soorten afspraak aanmaken (bestaande instellingen blijven staan). */
    private function setUpAgenda(): void
    {
        Modules::save([Modules::AGENDA => true]);

        $types = (array) Agenda::setting('types');
        $keys = array_column($types, 'key');

        foreach ([
            ['key' => 'startsessie', 'name' => 'Startsessie', 'duration' => 120],
            ['key' => 'vervolguur', 'name' => 'Vervolguur', 'duration' => 60],
        ] as $type) {
            if (in_array($type['key'], $keys, true)) {
                continue;
            }

            // Coaching is online met schermdeling: enkel videocall. De rest (aanlooptijd,
            // horizon, herinnering, ontvanger) volgt de gewone agenda-instellingen.
            $types[] = [
                ...$type,
                'buffer' => null,
                'lead_hours' => null,
                'horizon_days' => null,
                'methods' => ['video'],
                'video_url' => null,
                'reminder_hours' => null,
                'change_hours' => null,
                'notify_to' => [],
                'mail_text' => null,
                'own_windows' => false,
                'windows' => [],
                'translations' => [],
            ];
        }

        Agenda::save(['types' => array_values($types)]);
    }

    /** De foto van Pieter uit de mediabibliotheek (anders uit de repo erbij gezet). */
    private function photo(string $alt): array
    {
        $existing = WebsiteMedia::query()->where('original_filename', self::PHOTO_FILE)->first();

        if ($existing) {
            return ['src' => $existing->url, 'alt' => $alt];
        }

        $path = base_path(self::PHOTO_SOURCE);

        if (! is_file($path)) {
            return [];
        }

        try {
            $media = app(\Webgoeroe\Core\Services\WebsiteMediaService::class)
                ->store(new \Illuminate\Http\UploadedFile($path, self::PHOTO_FILE, 'image/jpeg', null, true));
        } catch (\Throwable) {
            return [];
        }

        return ['src' => $media->url, 'alt' => $alt];
    }

    /** "AI-coaching" als vijfde item onder Diensten in het hoofdmenu. */
    private function addMenuItem(Page $page): void
    {
        $menu = Menu::query()->where('location', 'main')->first();
        $parent = $menu?->items()->whereNull('parent_id')->where('label', 'Diensten')->first();

        if (! $menu || ! $parent || MenuItem::query()->where('menu_id', $menu->id)->where('page_id', $page->id)->exists()) {
            return;
        }

        MenuItem::query()->create([
            'menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'label' => 'AI-coaching',
            'page_id' => $page->id,
            'position' => (int) MenuItem::query()->where('parent_id', $parent->id)->max('position') + 1,
        ]);
    }

    /** "Liever zelf leren? Bekijk onze AI-coaching." onder de slotknop van drie bestaande pagina's. */
    private function addInternalLinks(Page $coaching): void
    {
        foreach (['ai-automatisering-zelfstandigen', 'ai-automatisering-kmo', 'business-automation'] as $slug) {
            $cta = Page::query()->where('slug', $slug)->first()?->sections()->where('section_type', 'cta')->orderByDesc('position')->first();

            if (! $cta || collect($cta->content['extra_lines'] ?? [])->contains('page_id', $coaching->id)) {
                continue;
            }

            $cta->update(['content' => [
                ...$cta->content,
                'extra_lines' => [[
                    'text' => 'Liever zelf leren?',
                    'label' => 'Bekijk onze AI-coaching.',
                    'link_type' => 'page',
                    'page_id' => $coaching->id,
                ]],
            ]]);
        }
    }

    private function card(string $icon, string $title, string $description, ?string $subtitle = null): array
    {
        return [
            'icon' => $icon,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'media_type' => 'icon',
            'features' => [],
            'cta_label' => null,
            'link_type' => null,
            'page_id' => null,
        ];
    }

    private function toAgenda(string $label = 'Boek je startsessie'): array
    {
        return ['label' => $label, 'variant' => 'primary', 'link_type' => 'url', 'href' => '#agenda'];
    }

    private function bookingFields(): array
    {
        $field = fn (string $role, string $label, string $type, bool $required = false, ?string $placeholder = null): array => [
            'key' => $role === 'extra' ? 'x_'.str($label)->slug('_') : $role,
            'role' => $role,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'options' => [],
            'placeholder' => $placeholder,
        ];

        return [
            $field('first_name', 'Voornaam', 'text', true),
            $field('email', 'E-mailadres', 'email', true),
            $field('phone', 'Telefoon', 'tel'),
            $field('extra', 'Website', 'text', false, 'www.jouwbedrijf.be'),
            $field('extra', 'Wat doe je?', 'textarea'),
            $field('extra', 'Welke taak kost je elke week het meeste tijd?', 'textarea'),
        ];
    }

    /** @return list<array{0: string, 1: array<string, mixed>}> */
    private function sections(array $photo): array
    {
        $contact = "Liever eerst een vraag stellen? Mail naar hello@dewebgoeroe.be of bel +32 (0)456 95 25 54.";
        $intro = '<p>Kies je startsessie of een vervolguur, kies een moment dat past en reken meteen af. Je krijgt een bevestiging met de link voor het gesprek.</p>';

        return [
            // 1. Hero
            ['hero', [
                'height' => 'medium',
                'image_layout' => 'side',
                'eyebrow' => "1-op-1 AI-coaching voor zelfstandigen en kmo's",
                'heading' => 'Een AI-coach die het samen met je opzet.',
                'subtitle' => '<p>Je weet dat AI werk van je kan overnemen. Je weet alleen niet waar je moet beginnen. In een 1-op-1 sessie zet ik Claude samen met jou op voor jouw bedrijf en automatiseren we je eerste taak. Daarna kan je het zelf.</p>',
                'ctas' => [
                    $this->toAgenda(),
                    ['label' => 'Bekijk eerst wat je na één sessie hebt', 'variant' => 'ghost', 'link_type' => 'url', 'href' => '#na-je-eerste-sessie'],
                ],
                'cta_note' => '2 uur online · € 250 excl. btw · niet tevreden, geld terug',
                'image' => $photo,
            ]],

            // 2. Herkenning
            ['problem_recognition', [
                'section_id' => 'herkenning',
                'background' => 'dark',
                'eyebrow' => 'Herken je dit?',
                'heading' => 'Je wil met AI aan de slag, maar je raakt niet vertrokken',
                'problems' => [
                    ['icon' => 'message-square', 'title' => 'Je gebruikt ChatGPT af en toe voor een mail.', 'description' => 'Daar blijft het bij.'],
                    ['icon' => 'eye-off', 'title' => 'Je ziet overal wat AI kan.', 'description' => 'Wat het voor jouw zaak betekent, zie je niet.'],
                    ['icon' => 'play-circle', 'title' => 'Je keek een avond video\'s.', 'description' => 'Daarna wist je nog minder waar te beginnen.'],
                    ['icon' => 'terminal', 'title' => 'Je hoort over Claude Code en agents.', 'description' => 'Het klinkt als iets voor programmeurs.'],
                    ['icon' => 'shield', 'title' => 'Je wil je klantgegevens niet zomaar ergens in plakken.', 'description' => 'Dus doe je voorlopig niets.'],
                    ['icon' => 'clock', 'title' => 'Je hebt geen tijd om het zelf uit te zoeken.', 'description' => 'Elke week dat je wacht, doe je hetzelfde werk weer met de hand.'],
                ],
                'closing' => '<p><strong>Je weet niet te weinig over AI.</strong> Je mist iemand die naast je zit en zegt: hiermee beginnen we.</p>',
            ]],

            // 3. Wat doet een AI-coach? (tekst met tabel)
            ['text', [
                'section_id' => 'wat-doet-een-ai-coach',
                'background' => 'light',
                'heading' => 'Wat doet een AI-coach?',
                'body' => '<p>Een AI-coach leert je AI gebruiken in je eigen bedrijf, met je eigen werk. Geen algemene cursus en geen bureau dat alles voor je bouwt. We kijken samen op jouw scherm, kiezen de taak die je het meeste tijd kost en zetten die om. Jij doet het zelf, ik zit ernaast.</p>'
                    .'<p>Ik werk met Claude en Claude Code, de AI-assistent van Anthropic. Claude beantwoordt niet alleen vragen. Het kan ook taken uitvoeren in je mailbox, je documenten en je andere tools.</p>'
                    .'<table><tbody>'
                    .'<tr><th></th><th>Online cursus</th><th>Laten bouwen</th><th>AI-coaching</th></tr>'
                    .'<tr><td>Waarover het gaat</td><td>Algemene voorbeelden</td><td>Jouw bedrijf</td><td>Jouw bedrijf</td></tr>'
                    .'<tr><td>Wie het doet</td><td>Jij, alleen</td><td>Ik</td><td>Jij, met mij ernaast</td></tr>'
                    .'<tr><td>Wat je overhoudt</td><td>Kennis</td><td>Een systeem</td><td>Een systeem en de kennis om verder te bouwen</td></tr>'
                    .'<tr><td>Als je vastzit</td><td>Zelf zoeken</td><td>Mij bellen</td><td>Een uur boeken</td></tr>'
                    .'</tbody></table>',
            ]],

            // 4. Na je eerste sessie (4 kaarten) + knop
            ['cards', [
                'section_id' => 'na-je-eerste-sessie',
                'background' => 'dark',
                'columns' => 2,
                'eyebrow' => 'De startsessie',
                'heading' => 'Na twee uur werkt er al iets',
                'intro' => '<p>Je gaat niet naar huis met een stapel notities. Dit heb je aan het einde van de startsessie.</p>',
                'cards' => [
                    $this->card('brain', 'Claude kent jouw bedrijf', 'Wat je aanbiedt, voor wie en hoe je klinkt. Je moet het niet telkens opnieuw uitleggen.'),
                    $this->card('check-circle', 'Je eerste taak loopt met AI', 'We kiezen iets wat je elke week met de hand doet en zetten het samen om.'),
                    $this->card('list-ordered', 'Je weet wat hierna komt', 'Een korte lijst van wat je het meeste tijd oplevert, in de juiste volgorde.'),
                    $this->card('lock', 'Je weet wat je deelt en wat niet', 'We zetten de privacy-instellingen samen goed en spreken af welke gegevens je er beter niet in zet.'),
                ],
                'ctas' => [$this->toAgenda()],
            ]],

            // 5. Wat je kan laten doen (6 kaarten)
            ['cards', [
                'section_id' => 'wat-je-kan-laten-doen',
                'background' => 'light',
                'columns' => 3,
                'eyebrow' => 'Voorbeelden',
                'heading' => 'Wat Claude bij mij zelf elke dag doet',
                'intro' => '<p>Dit zijn geen voorbeelden uit een brochure. Het draait vandaag in mijn eigen bedrijven. Welke hiervan bij jou past, bekijken we samen.</p>',
                'cards' => [
                    $this->card('mail', 'Je mailbox voorbereiden', 'Elke ochtend staan de antwoorden klaar in jouw stijl. Jij leest na en verstuurt.'),
                    $this->card('calendar', 'Een overzicht van je dag', 'Afspraken, taken en wat er open staat, op één pagina.'),
                    $this->card('file-text', 'Offertes opmaken', 'Van je notities na een gesprek naar een nette offerte in je eigen huisstijl.'),
                    $this->card('pencil', 'Blog en social media', 'Artikels en berichten in je eigen toon, ingepland voor de hele week.'),
                    $this->card('bar-chart-2', 'Cijfers en rapporten', 'Je advertenties, je omzet of je facturen in een overzicht dat je in één oogopslag leest.'),
                    $this->card('clipboard-list', 'Formulieren en administratie', 'Documenten invullen, facturen ordenen, gegevens overzetten.'),
                ],
            ]],

            // 6. Je coach (foto links)
            ['text_media', [
                'section_id' => 'je-coach',
                'background' => 'dark',
                'eyebrow' => 'Je coach',
                'heading' => 'Ik leer je alleen wat ik zelf gebruik',
                'intro' => '<p>Ik ben Pieter Van Looy, oprichter van De WebGoeroe. Ik run drie bedrijven, waaronder een webbureau en een dansschool, en laat het terugkerende werk daarvan grotendeels door Claude doen.</p>'
                    .'<p>Alles wat je in de coaching leert, heb ik eerst zelf uitgeprobeerd. Ook wat niet werkte. Zo hoef jij die omwegen niet te maken.</p>'
                    .'<p>Geen account manager, geen ticketsysteem. Je boekt een uur en we zitten samen aan je scherm.</p>',
                'media_type' => 'image',
                'media_side' => 'left',
                'media' => $photo,
                'ctas' => [],
            ]],

            // 7. Zo werkt het
            ['process_steps', [
                'section_id' => 'zo-werkt-het',
                'background' => 'light',
                'heading' => 'In drie stappen aan de slag',
                'steps' => [
                    ['title' => 'Je boekt je startsessie', 'description' => 'Je kiest een moment in de agenda, betaalt online en vertelt in drie zinnen wat je doet en waar je tijd verliest.'],
                    ['title' => 'We zetten het samen op', 'description' => 'Twee uur online, met schermdeling. Jij zit aan de knoppen, ik zeg wat de volgende stap is.'],
                    ['title' => 'Je gaat verder op je eigen tempo', 'description' => 'Alleen, of met een extra uur wanneer je iets nieuws wil aanpakken of vastzit.'],
                ],
            ]],

            // 8. Prijzen (2 kaarten) + garantie + knop
            ['cards', [
                'section_id' => 'prijzen',
                'background' => 'dark',
                'columns' => 2,
                'eyebrow' => 'Wat kost een AI-coach?',
                'heading' => 'Twee prijzen, geen abonnement',
                'cards' => [
                    $this->card('rocket', 'Startsessie', 'Twee uur online, door mij voorbereid. Claude opgezet voor jouw bedrijf, je eerste taak geautomatiseerd en een lijst voor daarna. Je betaalt bij het boeken.', '€ '.AiCoaching::START_PRICE.' excl. btw'),
                    $this->card('life-buoy', 'Vervolgcoaching', 'Je boekt een uur wanneer je het nodig hebt en betaalt bij het boeken. Geen minimum, geen contract.', '€ '.AiCoaching::FOLLOW_UP_PRICE.' per uur excl. btw'),
                ],
                'footer' => '<p><strong>Niet tevreden, geld terug.</strong> Werkt je eerste taak aan het einde van de startsessie niet met AI, dan betaal ik je de volledige € '.AiCoaching::START_PRICE.' terug. Zonder discussie.</p>'
                    .'<p>Je hebt een laptop, een internetverbinding en een betalend Claude-abonnement nodig. Dat abonnement neem je rechtstreeks bij Anthropic. Ik help je bij het kiezen tijdens of voor de sessie.</p>'
                    .'<p>De coaching verloopt online, dus het maakt niet uit waar in Vlaanderen of België je zit.</p>',
                'ctas' => [$this->toAgenda()],
            ]],

            // 9. Voor wie wel en niet
            ['text', [
                'section_id' => 'voor-wie',
                'background' => 'light',
                'heading' => 'Is dit iets voor jou?',
                'body' => '<h3>Wel</h3><ul>'
                    .'<li>Je bent zelfstandige of zaakvoerder van een kmo.</li>'
                    .'<li>Je wil zelf begrijpen hoe het werkt en er zelf mee verder kunnen.</li>'
                    .'<li>Je kan met een laptop overweg. Programmeren hoeft niet.</li>'
                    .'<li>Je hebt minstens één taak die je elke week met tegenzin doet.</li>'
                    .'</ul><h3>Niet</h3><ul>'
                    .'<li>Je wil er zelf geen tijd in steken. Dan bouw ik het beter voor je.</li>'
                    .'<li>Je zoekt een opleiding voor een heel team in een leslokaal.</li>'
                    .'<li>Je verwacht dat AI na één sessie je hele bedrijf runt. Het is een begin, geen wondermiddel.</li>'
                    .'</ul>',
            ]],

            // 10. Veelgestelde vragen (de core maakt er de FAQPage-schema van)
            ['faq', [
                'section_id' => 'faq',
                'background' => 'dark',
                'heading' => 'Veelgestelde vragen over AI-coaching',
                'items' => [
                    ['question' => 'Moet ik technisch zijn?', 'answer' => '<p>Nee. Als je kan mailen en een document kan openen, kan je dit. We werken op jouw tempo en jij zit zelf aan de knoppen, zodat je het daarna ook alleen kan.</p>'],
                    ['question' => 'Wat is het verschil tussen ChatGPT, Claude en Claude Code?', 'answer' => '<p>ChatGPT en Claude zijn allebei AI-assistenten waar je vragen aan stelt. Claude Code gaat een stap verder: het kan taken uitvoeren in je bestanden en je tools, in plaats van alleen te antwoorden. Daar zit de echte tijdwinst. Je hoeft er niet voor te kunnen programmeren.</p>'],
                    ['question' => 'Is mijn data veilig?', 'answer' => '<p>In de startsessie zetten we samen de privacy-instellingen van je account goed. We spreken ook af welke gegevens je wel en niet deelt. Jij beslist altijd wat erin gaat.</p>'],
                    ['question' => 'Hoeveel sessies heb ik nodig?', 'answer' => '<p>Dat kies je zelf. Na de startsessie kan je alleen verder. De meeste taken daarna pak je aan in een uur per keer. Er is geen minimum en geen traject waar je aan vastzit.</p>'],
                    ['question' => 'Wat heb ik nodig om te starten?', 'answer' => '<p>Een laptop, een internetverbinding en een betalend Claude-abonnement. Heb je dat nog niet, dan regelen we het aan het begin van de sessie.</p>'],
                    ['question' => 'Ik gebruik al ChatGPT of Copilot. Heeft dit dan zin?', 'answer' => '<p>Ja. Ik coach met Claude omdat ik er zelf elke dag mee werk. Wat je leert over hoe je AI aan het werk zet, kan je grotendeels ook in andere tools gebruiken.</p>'],
                    ['question' => 'Wat als de AI-tools volgend jaar weer anders zijn?', 'answer' => '<p>Je leert een manier van werken, geen knoppen van buiten. Die blijft bruikbaar als de tools veranderen. Verandert er iets groots, dan boek je een uur en zet ik je opnieuw op weg.</p>'],
                    ['question' => 'Kan je het ook gewoon voor mij bouwen?', 'answer' => '<p>Ja. Wil je er zelf niet mee bezig zijn, dan automatiseer ik het voor je. Dat begint met een gratis <a href="/bottleneck-scan">Bottleneck Scan</a>.</p>'],
                ],
            ]],

            // 11a. Agenda: startsessie (voorgeselecteerd)
            ['booking', [
                'section_id' => 'agenda',
                'background' => 'light',
                'layout' => 'section',
                'provider' => 'agenda',
                'heading' => 'Boek je startsessie',
                'intro' => $intro,
                'agenda_type' => 'startsessie',
                'agenda_note' => '2 uur online · € '.AiCoaching::START_PRICE.' excl. btw',
                'agenda_submit_label' => 'Boek mijn startsessie',
                'agenda_fields' => $this->bookingFields(),
                'after_text' => "Prijzen excl. btw. Veilig online betalen. Startsessie: niet tevreden, geld terug.\n\n".$contact,
                'swap_with' => 'vervolguur',
                'swap_label' => 'Startsessie · 2 uur · € '.AiCoaching::START_PRICE,
                'swap_hidden' => false,
            ]],

            // 11b. Agenda: vervolguur (achter de tweede knop)
            ['booking', [
                'section_id' => 'vervolguur',
                'background' => 'light',
                'layout' => 'section',
                'provider' => 'agenda',
                'heading' => 'Boek een vervolguur',
                'intro' => $intro,
                'agenda_type' => 'vervolguur',
                'agenda_note' => '1 uur online · € '.AiCoaching::FOLLOW_UP_PRICE.' excl. btw',
                'agenda_submit_label' => 'Boek mijn vervolguur',
                'agenda_fields' => $this->bookingFields(),
                'after_text' => "Prijzen excl. btw. Veilig online betalen.\n\n".$contact,
                'swap_with' => 'agenda',
                'swap_label' => 'Vervolguur · 1 uur · € '.AiCoaching::FOLLOW_UP_PRICE,
                'swap_hidden' => true,
            ]],
        ];
    }
};
