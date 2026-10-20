<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: de boeking staat op de pagina zelf, in
 * de GHL-agenda van deze campagne ("Bottleneck Scan Websites"), in plaats van
 * een knop naar een aparte pagina. Eén pagina per campagne = de agenda weet
 * van welke campagne een boeking komt (zie wiki "campagnepakket").
 *
 * - De omzetcheck-knop springt weer naar #scan (de boeking onderaan); naam en
 *   e-mail uit de gate vullen de agenda voor.
 * - De sectie "#scan" (CTA met knop) wordt een boeking-sectie.
 *
 * Enkel waarden die nog het origineel zijn; wat in de admin is aangepast blijft staan.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    /** GHL-agenda "Bottleneck Scan Websites" (iframe-URL). */
    private const CALENDAR_URL = 'https://api.leadconnectorhq.com/widget/booking/knxGUwQTCwM1iGpZ5Sgz';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections as $section) {
            $content = $section->content;

            if ($section->section_type === 'conversion_calculator'
                && ($content['link_type'] ?? null) === 'url' && ($content['href'] ?? null) === '/bottleneck-scan') {
                $content['href'] = '#scan';
                $section->update(['content' => $content]);

                continue;
            }

            if ($section->section_type === 'cta' && ($content['section_id'] ?? null) === 'scan') {
                $section->update([
                    'section_type' => 'booking',
                    'content' => [
                        'section_id' => 'scan',
                        'background' => $content['background'] ?? 'light',
                        'layout' => 'section',
                        'provider' => 'ghl',
                        'url' => self::CALENDAR_URL,
                        'height' => 800,
                        'eyebrow' => $content['eyebrow'] ?? 'Gratis Bottleneck Scan',
                        'heading' => $content['heading'] ?? 'Ontdek waar jouw website klanten verliest',
                        'intro' => $content['intro'] ?? '',
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        // Bewust leeg: terug naar een losse pagina zou het campagnepakket breken.
    }
};
