<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Setting;

/**
 * De site-brede call-to-action: de afsluitende banner onderaan de blog- en
 * case-detailpagina's (titel, tekst en knop).
 *
 * Eén plek in de admin (Instellingen → Algemeen) bepaalt waar élke CTA naartoe
 * wijst; een case mag afzonderlijk afwijken via z'n eigen content.cta-velden.
 * Laat die leeg en de case erft de instelling hieronder.
 *
 * De bestemming wordt bij voorkeur als pagina-koppeling bewaard (link_type
 * 'page' + page_id), zodat de knop blijft kloppen wanneer de slug van die
 * pagina later verandert.
 */
class SiteCta
{
    public const KEY = 'cta';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'title' => 'Benieuwd wat dit voor jouw bedrijf kan betekenen?',
            'body' => 'In een gratis adviesgesprek van 30 minuten bekijken we samen jouw situatie. Geen verplichtingen, wel concrete inzichten.',
            'button_label' => 'Plan je gratis adviesgesprek',
            'link_type' => 'url',
            'page_id' => null,
            'href' => '/gratis-adviesgesprek',
        ];
    }

    /**
     * De opgeslagen instelling, over de defaults heen, met een uitgerekende href.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $stored = Setting::get(self::KEY, []);

        $cta = [
            ...self::defaults(),
            ...(is_array($stored) ? $stored : []),
        ];

        return [
            ...$cta,
            'href' => self::resolveHref($cta),
        ];
    }

    /**
     * De site-CTA met de (optionele) eigen velden van een case eroverheen.
     * Lege velden vallen terug op de site-instelling, zodat je één keer
     * centraal de bestemming wijzigt en alle cases meeschuiven.
     *
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, mixed>
     */
    public static function mergedWith(?array $overrides): array
    {
        $cta = self::current();

        foreach (['title', 'body', 'button_label'] as $field) {
            if (filled($overrides[$field] ?? null)) {
                $cta[$field] = $overrides[$field];
            }
        }

        // Een eigen knop-URL op de case wint; anders blijft de site-bestemming staan.
        if (filled($overrides['button_url'] ?? null)) {
            $cta['href'] = $overrides['button_url'];
        }

        return $cta;
    }

    /**
     * Leid de uiteindelijke href af uit de instelling.
     *
     * Bij link_type 'page' wordt de href live uit de gekozen pagina berekend
     * (slug → pad), zodat de knop blijft kloppen ook al verandert de slug. Bij
     * 'url' (of als de pagina niet meer bestaat) valt hij terug op de
     * opgeslagen href. Zelfde afweging als in SiteHeader::resolveHref().
     *
     * @param  array<string, mixed>  $cta
     */
    private static function resolveHref(array $cta): ?string
    {
        if (($cta['link_type'] ?? null) === 'page' && ! empty($cta['page_id'])) {
            $page = Page::find($cta['page_id']);

            if ($page !== null) {
                return $page->is_homepage ? '/' : '/'.$page->slug;
            }
        }

        return $cta['href'] ?? null;
    }
}
