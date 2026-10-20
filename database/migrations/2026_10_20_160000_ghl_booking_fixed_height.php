<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

/**
 * De GHL-agenda draait zonder form_embed.js (dat verborg de iframe en liet hem
 * lui laden, waardoor hij onderaan een pagina nooit laadde). Zonder dat script
 * past de hoogte zich niet meer aan, dus een ruime vaste hoogte: het boekformulier
 * is lang. Alleen boeking-secties met provider ghl die lager staan.
 */
return new class extends Migration
{
    private const HEIGHT = 1100;

    public function up(): void
    {
        PageSection::query()->where('section_type', 'booking')->get()->each(function (PageSection $section) {
            $content = $section->content;

            if (($content['provider'] ?? null) === 'ghl' && (int) ($content['height'] ?? 0) < self::HEIGHT) {
                $section->update(['content' => [...$content, 'height' => self::HEIGHT]]);
            }
        });
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
