<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: de GHL-agenda opent in een pop-up achter
 * een knop in plaats van als iframe in de pagina (geen scroll-in-scroll, de
 * paginalayout blijft heel). Enkel de boeking-sectie van deze pagina.
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
                $section->update(['content' => [
                    ...$content,
                    'ghl_display' => 'popup',
                    'popup_button_label' => $content['popup_button_label'] ?? 'Kies een moment',
                ]]);
            }
        }
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
