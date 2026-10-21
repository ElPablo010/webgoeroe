<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

/**
 * De GHL-agenda stond op 1100 px: onder de kalender bleef veel wit staan. Lager,
 * 900 px; het boekformulier scrolt zo nodig binnenin. Enkel boeking-secties met
 * provider ghl die nog op de vorige waarde staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()->where('section_type', 'booking')->get()->each(function (PageSection $section) {
            $content = $section->content;

            if (($content['provider'] ?? null) === 'ghl' && (int) ($content['height'] ?? 0) === 1100) {
                $section->update(['content' => [...$content, 'height' => 900]]);
            }
        });
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
