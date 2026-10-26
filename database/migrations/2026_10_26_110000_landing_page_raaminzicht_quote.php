<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: het Raaminzicht-citaat noemde "twintig
 * aanvragen per week in plaats van tien", wat niet klopt (echt: 5 aanvragen per
 * maand vroeger, 13 in oktober 2026). Op uitdrukkelijk verzoek van Pieter
 * (26/10/2026) aangepast aan de werkelijkheid; Tims eerste zin blijft staan.
 * Nog aan Tim voor te leggen.
 *
 * Enkel als het citaat nog de oude tekst heeft; wat in de admin is aangepast blijft staan.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    private const OLD_QUOTE = 'We wilden een redesign. Drie weken later zaten we aan twintig aanvragen per week in plaats van tien.';

    private const NEW_QUOTE = 'We wilden een redesign. Sinds de nieuwe website krijgen we meer dan dubbel zoveel aanvragen.';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();
        $section = $page?->sections->firstWhere('section_type', 'reviews');

        if (! $section) {
            return;
        }

        $content = $section->content;
        $changed = false;

        foreach ($content['items'] ?? [] as $i => $item) {
            if (($item['quote'] ?? null) === self::OLD_QUOTE) {
                $content['items'][$i]['quote'] = self::NEW_QUOTE;
                $changed = true;
            }
        }

        if ($changed) {
            $section->update(['content' => $content]);
        }
    }

    public function down(): void
    {
        // Bewust leeg: het oude citaat klopt niet.
    }
};
