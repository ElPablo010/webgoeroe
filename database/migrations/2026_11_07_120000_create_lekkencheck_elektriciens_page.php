<?php

use App\Models\Page;
use App\Support\LandingPages;
use Illuminate\Database\Migrations\Migration;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Integrations\GoHighLevel\SubmissionSync;

/**
 * Landingspagina /lekkencheck-elektriciens (core 0.15.10, blok "Lekkencheck"): 7
 * vragen → resultaat (bandbreedte omzet per jaar, lekken met tip), daaronder het
 * verkenningsgesprek van 15 minuten (kopie van het blok op
 * /speed-to-lead-elektriciens). Enkel via Meta-advertenties: landingsmodus,
 * noindex. Zet ook het type "Lekkencheck" aan op Integraties (GHL), met de
 * herkomst van de advertentie. Bestaat de pagina al, dan blijft ze zoals ze is.
 * Zonder /speed-to-lead-elektriciens (lege testdatabase) gebeurt er niets.
 */
return new class extends Migration
{
    private const SLUG = 'lekkencheck-elektriciens';

    private const BOOKING_CALENDAR = '7NSGK8pLb0FcXVa9TLLH';

    public function up(): void
    {
        // Type "Lekkencheck" naar GHL, met de herkomst van de advertentie.
        $attribution = config('services.gohighlevel.attribution_field');
        $stored = (array) Core::model('setting')::get(SubmissionSync::SETTING, []);
        $stored['lead_check'] ??= [
            'enabled' => true,
            'fields' => filled($attribution) ? [['form' => 'herkomst', 'ghl' => $attribution]] : [],
        ];
        Core::model('setting')::set(SubmissionSync::SETTING, $stored);

        // Enkel op de echte site (lokaal/live), niet in een lege testdatabase.
        if (Page::query()->where('slug', self::SLUG)->exists()
            || ! Page::query()->where('slug', 'speed-to-lead-elektriciens')->exists()) {
            return;
        }

        $q = fn (string $key, string $question, string $role, array $options, array $extra = []) => [
            'key' => $key, 'question' => $question, 'role' => $role, 'input' => 'choice',
            'options' => array_map(fn ($o) => ['label' => $o[0], 'value' => $o[1]], $options),
            ...$extra,
        ];

        $content = [
            'section_id' => 'lekkencheck',
            'eyebrow' => 'Lekkencheck voor elektriciens',
            'heading' => 'Hoeveel klanten bellen je concurrent terwijl jij op de werf staat?',
            'intro' => '<p>Beantwoord 7 korte vragen en zie meteen hoeveel omzet je laat liggen door gemiste oproepen en trage opvolging. Het duurt 2 minuten.</p>',
            'questions' => [
                $q('opdrachtwaarde', 'Wat is een gemiddelde opdracht bij jou waard?', 'value', [['Minder dan €500', 350], ['€500 tot €1.500', 1000], ['€1.500 tot €5.000', 3000], ['Meer dan €5.000', 7000]]),
                $q('oproepen', 'Hoeveel oproepen en aanvragen krijg je per week?', 'volume', [['0 tot 5', 3], ['5 tot 10', 7], ['10 tot 20', 15], ['Meer dan 20', 25]], ['period' => 'week']),
                $q('slotratio', 'Van 10 klanten die bellen, hoeveel worden een opdracht?', 'close_rate', [['1 of 2', 15], ['3 of 4', 35], ['5 of meer', 55], ['Geen idee', 30]]),
                $q('gemiste_oproepen', 'Hoe vaak neem je niet meteen op, bv. omdat je op de werf staat?', 'leak_leads', [['Bijna nooit', 0], ['1 op de 5 oproepen', 5], ['1 op de 3 oproepen', 10], ['De helft of meer', 20]], [
                    'leak_label' => 'Gemiste oproepen', 'leak_tag' => 'lek-gemiste-oproepen',
                    'info' => 'Een klant met een probleem wacht zelden. Krijgt hij geen gehoor, dan belt hij de volgende elektricien op zijn lijstje.',
                    'advice' => 'Een klant die geen gehoor krijgt, belt meteen de volgende. Zorg dat er altijd iemand opneemt als jij het niet kan.',
                ]),
                $q('terugbellen', 'Hoe snel bel je een gemiste oproep terug?', 'leak_clients', [['Binnen het uur', 0], ['Dezelfde dag', 10], ['De volgende dag of later', 20], ['Niet, ze spreken wel in', 30]], [
                    'leak_label' => 'Reactiesnelheid', 'leak_tag' => 'lek-reactie',
                    'info' => 'Bedrijven die binnen het uur reageren, hebben bijna 7 keer meer kans op een echt gesprek dan wie een uur langer wacht (Harvard Business Review, 2011).',
                    'advice' => 'Hoe later je terugbelt, hoe groter de kans dat de klant al iemand anders heeft. Elke beller moet binnen de minuut weten dat je hem gehoord hebt.',
                ]),
                $q('buiten_de_uren', 'Wat gebeurt er met een oproep \'s avonds of in het weekend?', 'leak_leads', [['Iemand neemt op', 0], ['Voicemail', 5], ['Niets, de telefoon staat uit', 10]], [
                    'leak_label' => 'Bereikbaarheid buiten de uren', 'leak_tag' => 'lek-bereikbaarheid',
                    'advice' => 'Net \'s avonds en in het weekend bellen mensen met een dringend probleem. Wie dan opneemt, krijgt de opdracht.',
                ]),
                $q('opvolging', 'Wat doe je als een klant niet reageert op je offerte?', 'leak_clients', [['Ik volg minstens 2 keer op', 0], ['1 keer, of soms', 10], ['Niets', 20]], [
                    'leak_label' => 'Opvolging van offertes', 'leak_tag' => 'lek-opvolging',
                    'advice' => 'Geen antwoord is zelden een nee: meestal is het druk of vergeten. Elke offerte verdient minstens twee herinneringen.',
                ]),
            ],
            'mode' => 'amount',
            'default_value' => 1000,
            'default_volume' => 30,
            'default_close_rate' => 30,
            'max_close_rate' => 80,
            'max_loss' => 30,
            'low_factor' => 50,
            'result_period' => 'year',
            'price' => 297,
            'price_text' => 'Eén extra klant per maand betaalt Speed to Lead (€297 per maand) al terug.',
            'gate_heading' => 'Waar mogen we je resultaat naartoe sturen?',
            'gate_text' => 'Je ziet je resultaat meteen. We sturen het ook naar je mailbox, met per lek een tip die je zelf kan toepassen.',
            'phone_required' => true,
            'gate_button' => 'Toon mijn resultaat',
            'privacy_note' => 'We gebruiken je gegevens enkel om je resultaat te sturen en je verder te helpen. Geen spam.',
            'result_heading' => '{voornaam}, je laat naar schatting {laag_jaar} tot {hoog_jaar} per jaar liggen.',
            'result_heading_score' => '{voornaam}, {score} van de {totaal} punten zijn in orde.',
            'result_text' => 'Dat zijn ongeveer {klanten} klanten per maand die bij een andere elektricien terechtkomen. Je grootste lek: {grootste_lek}.',
            'disclaimer' => 'Een voorzichtige schatting op basis van je antwoorden. Geen belofte, wel een goed beeld van waar je omzet weglekt.',
            'cta_label' => 'Ja, toon me hoe ik dit oplos',
            'link_type' => 'url',
            'href' => '#afspraak',
            'ghl' => ['tags' => ['lekkencheck-ingevuld'], 'source' => 'Meta – Lekkencheck elektriciens'],
            // Eigen velden in GHL (aangemaakt op 07/11/2026).
            'ghl_result' => [
                'result_year' => 'zy6ZouJ87Xh0JJsOT2PN',
                'lost_clients' => 'UbTsyUpDpiyzYXbvUCut',
                'top_leak' => '64qSy7SnVtjKKoIvcIOy',
                'top_leak_advice' => 'XvbueypCJ1VX8zur1PBo',
                'leaks' => 'ndaGvccdMEoRefPl5lwo',
            ],
        ];

        $page = Page::query()->create([
            'title' => 'Lekkencheck elektriciens',
            'slug' => self::SLUG,
            'locale' => 'nl',
            'published' => true,
            'meta_title' => 'Lekkencheck voor elektriciens | De WebGoeroe',
            'meta_description' => 'Zie in 2 minuten hoeveel klanten en omzet je misloopt door gemiste oproepen en trage opvolging.',
            'meta_robots' => 'noindex, nofollow',
        ]);

        $page->sections()->create(['section_type' => 'lead_check', 'position' => 0, 'content' => $content]);

        $booking = Core::model('page_section')::query()
            ->where('section_type', 'booking')
            ->get()
            ->first(fn ($section): bool => str_contains((string) ($section->content['url'] ?? ''), self::BOOKING_CALENDAR));

        if ($booking) {
            $page->sections()->create(['section_type' => 'booking', 'position' => 1, 'content' => [
                ...$booking->content,
                'section_id' => 'afspraak',
                // Workflow 2.3 stopt op deze tag.
                'ghl' => ['source' => 'Boeking lekkencheck', 'tags' => ['lekkencheck-geboekt']],
            ]]);
        }

        LandingPages::add($page->id);
    }
};
