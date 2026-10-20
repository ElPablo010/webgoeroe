<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: beide boekpaden lopen via
 * /bottleneck-scan (GoHighLevel-agenda) in plaats van via #scan → Calendly.
 *
 * - De knop onder het omzetcheck-resultaat gaat naar /bottleneck-scan; daar
 *   vult de agenda naam en e-mail voor uit de gate (?first_name=…&email=…).
 * - De knop "Kies een moment" onderaan wees nog naar Calendly; een Calendly-
 *   boeking komt niet in GHL, waardoor de opvolgmails niet stoppen.
 *
 * Enkel waarden die nog het origineel zijn; wat in de admin is aangepast blijft staan.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    private const CALENDLY = 'https://calendly.com/dewebgoeroe/bottleneck-scan';

    private const TARGET = '/bottleneck-scan';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections as $section) {
            $content = $section->content;
            $changed = false;

            if ($section->section_type === 'conversion_calculator'
                && ($content['link_type'] ?? null) === 'url' && ($content['href'] ?? null) === '#scan') {
                $content['href'] = self::TARGET;
                $changed = true;
            }

            if ($section->section_type === 'cta' && is_array($content['ctas'] ?? null)) {
                foreach ($content['ctas'] as $i => $cta) {
                    if (($cta['href'] ?? null) === self::CALENDLY) {
                        $content['ctas'][$i]['href'] = self::TARGET;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $section->update(['content' => $content]);
            }
        }
    }

    public function down(): void
    {
        // Bewust leeg: terug naar Calendly zou boekingen weer buiten GHL laten vallen.
    }
};
