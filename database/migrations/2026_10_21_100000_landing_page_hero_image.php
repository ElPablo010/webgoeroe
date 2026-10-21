<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: beeld naast de tekst in de hero.
 * Een ondernemer achter zijn laptop met een nieuwe offerteaanvraag in zijn
 * inbox (AI-gegenereerd, Higgsfield): laat meteen het resultaat zien waar de
 * kop over gaat. Beeld staat in de repo en komt zo live mee in de library.
 *
 * Enkel als de hero nog geen beeld heeft; wat in de admin is aangepast blijft staan.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    private const IMAGE_SOURCE = 'resources/images/landing/hero-ondernemer-offerteaanvraag.jpg';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        $hero = $page->sections->firstWhere('section_type', 'hero');

        if (! $hero || ! empty(($hero->content['image']['src'] ?? null))) {
            return;
        }

        $path = base_path(self::IMAGE_SOURCE);

        if (! is_file($path)) {
            return;
        }

        try {
            $media = app(\Webgoeroe\Core\Services\WebsiteMediaService::class)
                ->store(new \Illuminate\Http\UploadedFile($path, basename($path), 'image/jpeg', null, true));
        } catch (\Throwable) {
            return;
        }

        $content = $hero->content;
        $content['image'] = [
            'src' => $media->url,
            'alt' => 'Ondernemer achter zijn laptop, glimlachend bij een nieuwe offerteaanvraag in zijn inbox',
        ];
        $content['image_layout'] = 'side';

        $hero->update(['content' => $content]);
    }

    public function down(): void
    {
        // Bewust leeg: het beeld blijft in de library.
    }
};
