<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: de GHL-agenda toont de eigen kalender
 * (zonder iframe) in plaats van de pop-up achter een knop. Enkel de
 * boeking-sectie van deze pagina.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections as $section) {
            $content = $section->content;

            if ($section->section_type === 'booking' && ($content['provider'] ?? null) === 'ghl') {
                $section->update(['content' => [...$content, 'ghl_display' => 'own']]);
            }
        }
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
