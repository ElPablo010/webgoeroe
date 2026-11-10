<?php

use App\Models\Page;
use App\Models\Setting;
use App\Support\LandingPages;
use Illuminate\Database\Migrations\Migration;

/**
 * Advertentiepagina /website-die-verkoopt, 10/11/2026:
 *
 * 1. Hero compacter: op een gsm moeten kop, tekst en knop samen in het eerste
 *    scherm passen (de knop stond net eronder). Hoogte "compact" en een kortere
 *    ondertitel. Enkel als de hero nog de oorspronkelijke kop heeft; wat in de
 *    admin is aangepast blijft staan.
 * 2. Tweede versie /website-die-verkoopt-calculator voor de nieuwe advertenties:
 *    kop en tekst volgen de advertentie ("Weinig aanvragen via je website?") en de
 *    calculator staat meteen onder de hero. Kopie van de eerste pagina, noindex,
 *    zonder menu en footer. Bestaat de slug al, dan doet stap 2 niets.
 *
 * Context: WebGoeroe/wiki/campagne-converterende-websites.md.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    private const SLUG_B = 'website-die-verkoopt-calculator';

    private const OLD_HEADING = 'Brengt jouw website klanten binnen, of is het een duur visitekaartje?';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        $this->compactHero($page);
        $this->createCalculatorVariant($page->fresh());
    }

    private function compactHero(Page $page): void
    {
        $hero = $page->sections->firstWhere('section_type', 'hero');

        if (! $hero || ($hero->content['heading'] ?? null) !== self::OLD_HEADING) {
            return;
        }

        $content = $hero->content;
        $content['height'] = 'compact';
        $content['subtitle'] = 'Je loopt elke maand klanten mis, zonder dat je het merkt. Bereken in 1 minuut wat dat jou kost.';

        $hero->update(['content' => $content]);
    }

    private function createCalculatorVariant(Page $source): void
    {
        if (Page::query()->where('slug', self::SLUG_B)->exists()) {
            return;
        }

        $copy = Page::query()->create([
            'title'            => 'Website die verkoopt – calculator',
            'slug'             => self::SLUG_B,
            'locale'           => 'nl',
            'published'        => true,
            'meta_title'       => 'Weinig aanvragen via je website? | De WebGoeroe',
            'meta_description' => 'Bereken in 1 minuut hoeveel omzet je website laat liggen en ontdek hoe je met dezelfde bezoekers meer aanvragen haalt.',
            'meta_robots'      => 'noindex,follow',
        ]);

        $sections = $source->sections->values();
        $calculator = $sections->firstWhere('section_type', 'conversion_calculator');
        $rest = $sections->reject(fn ($s) => $s->section_type === 'conversion_calculator');

        // Volgorde: hero, calculator, daarna de uitleg zoals op de eerste pagina.
        $ordered = collect([$rest->first(), $calculator])->merge($rest->slice(1))->filter()->values();

        foreach ($ordered as $position => $section) {
            $content = $section->content;

            if ($section->section_type === 'hero') {
                $content['heading'] = 'Weinig aanvragen via je website?';
                $content['subtitle'] = 'Bereken in 1 minuut hoeveel omzet je website daardoor laat liggen. Gratis en meteen resultaat.';
                $content['height'] = 'compact';
                // Zonder beeld: de calculator staat dan meteen onder de knop.
                unset($content['image'], $content['image_layout']);
                $content['ctas'] = [[
                    'href'      => '#calculator',
                    'label'     => 'Doe de gratis omzetcheck',
                    'variant'   => 'primary',
                    'link_type' => 'url',
                ]];
            }

            $copy->sections()->create([
                'section_type' => $section->section_type,
                'position'     => $position,
                'locale'       => $section->locale ?? 'nl',
                'content'      => $content,
            ]);
        }

        // Advertentiepagina: zonder menu en footer (Instellingen → Algemeen).
        LandingPages::add($copy->id);
    }

    public function down(): void
    {
        Page::query()->where('slug', self::SLUG_B)->get()->each(function (Page $page) {
            Setting::set(LandingPages::KEY, array_values(array_diff(LandingPages::ids(), [$page->id])));
            $page->sections()->delete();
            $page->delete();
        });
    }
};
