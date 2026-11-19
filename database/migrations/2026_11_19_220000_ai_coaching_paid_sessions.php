<?php

use App\Models\Page;
use App\Support\AiCoaching;
use Illuminate\Database\Migrations\Migration;
use Webgoeroe\Core\Agenda\Agenda;
use Webgoeroe\Core\Support\Modules;

/**
 * AI-coaching: elke sessie wordt bij het boeken betaald (Mollie, via de betaallaag van de core).
 *
 * - Module Betalingen aan (de Mollie-sleutel zet Pieter zelf in de admin: Instellingen → Betalingen).
 * - Prijs per soort afspraak: Startsessie € 250 en Vervolguur € 75, beide excl. btw, 21% btw.
 *   Andere soorten (en het standaardtype, de gratis Bottleneck Scan) blijven ongewijzigd.
 * - Eén boeking-blok waarin de bezoeker kiest tussen de twee soorten (Startsessie voorgeselecteerd),
 *   in plaats van twee blokken achter "Wisselen met een ander blok".
 * - De pagina /ai-coaching wordt gepubliceerd; het menu-item verschijnt daardoor vanzelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Modules::save([Modules::PAYMENTS => true]);

        $types = array_map(function (array $type): array {
            return match ($type['key'] ?? null) {
                'startsessie' => [...$type, 'price' => (string) AiCoaching::START_PRICE, 'vat_rate' => 21],
                'vervolguur' => [...$type, 'price' => (string) AiCoaching::FOLLOW_UP_PRICE, 'vat_rate' => 21],
                default => $type,
            };
        }, (array) Agenda::setting('types'));

        Agenda::save(['types' => array_values($types)]);

        $page = Page::query()->where('slug', AiCoaching::SLUG)->first();

        if (! $page) {
            return;
        }

        $page->sections()->where('section_type', 'booking')->where('content->agenda_type', 'vervolguur')->delete();

        $booking = $page->sections()->where('section_type', 'booking')->where('content->agenda_type', 'startsessie')->first();

        if ($booking) {
            $content = $booking->content;
            unset($content['swap_with'], $content['swap_label'], $content['swap_hidden']);

            $booking->update(['content' => [
                ...$content,
                'heading' => 'Boek je sessie',
                'agenda_type' => 'startsessie',
                'agenda_types' => ['startsessie', 'vervolguur'],
                'agenda_note' => null,
                'agenda_submit_label' => null,
                'after_text' => '€ '.AiCoaching::START_PRICE." excl. btw, veilig online betalen. Niet tevreden, geld terug.\n\nLiever eerst een vraag stellen? Mail naar hello@dewebgoeroe.be of bel +32 (0)456 95 25 54.",
            ]]);
        }

        $page->update(['published' => true]);
    }

    public function down(): void
    {
        Page::query()->where('slug', AiCoaching::SLUG)->update(['published' => false]);
    }
};
