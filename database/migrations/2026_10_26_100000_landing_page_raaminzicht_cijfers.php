<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: de Raaminzicht-kop klopte niet ("van 10 naar
 * 20 aanvragen per week"). Cijfers van de klant (Pieter, 26/10/2026): vroeger 5
 * aanvragen per maand via de website, in oktober 2026 al 13 (site live eind
 * augustus/begin september). We zeggen "sinds de livegang", niet dat de site de
 * oorzaak is: dat weten we niet zeker (seizoen, meer bezoekers).
 *
 * Enkel als de kop nog de oude tekst heeft; wat in de admin is aangepast blijft staan.
 * Het citaat in het reviews-blok blijft bewust ongewijzigd (besluit Pieter).
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    private const OLD_HEADING = 'Raaminzicht: van 10 naar 20 aanvragen per week';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        $section = $page?->sections->first(
            fn ($s) => $s->section_type === 'text_media' && ($s->content['heading'] ?? null) === self::OLD_HEADING,
        );

        if (! $section) {
            return;
        }

        $content = $section->content;
        $content['heading'] = 'Raaminzicht: van 5 naar 13 aanvragen per maand';
        $content['intro'] = '<p>Ik bouwde een volledig nieuwe website voor een bedrijf in ramen en zonwering, geoptimaliseerd voor conversie.</p>'
            .'<p><strong>Resultaat: het aantal aanvragen per maand is meer dan verdubbeld sinds de livegang.</strong></p>'
            .'<p>Vroeger kreeg Raaminzicht ongeveer 5 aanvragen per maand via de website. Sinds de nieuwe site (live sinds september 2026) zijn het er in oktober al 13, en de maand is nog niet voorbij.</p>';

        $section->update(['content' => $content]);
    }

    public function down(): void
    {
        // Bewust leeg: de oude cijfers kloppen niet meer.
    }
};
