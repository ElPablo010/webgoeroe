<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Setting;

/**
 * Header-instellingen: logo, naam, ondertitel en CTA-knop.
 *
 * defaults() levert neutrale startwaarden zodat de header rendert vóór de klant
 * iets aanpast in de admin. current() legt de opgeslagen waarden over de
 * defaults heen.
 *
 * TODO (per project): vul defaults() met de echte merknaam/locatie/CTA.
 */
class SiteHeader
{
    public const KEY = 'header';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'logo' => null,
            'favicon' => null,
            'name' => config('app.name'),
            'subtitle' => '',
            'cta' => [
                'label' => '',
                'link_type' => 'url',
                'page_id' => null,
                'href' => '/',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $stored = Setting::get(self::KEY, []);

        $cta = [
            ...self::defaults()['cta'],
            ...($stored['cta'] ?? []),
        ];

        return [
            ...self::defaults(),
            ...$stored,
            // CTA is genest, dus apart mergen zodat losse veld-defaults bewaard
            // blijven wanneer een opgeslagen blob ze (nog) niet bevat.
            'cta' => [
                ...$cta,
                'href' => self::resolveHref($cta),
            ],
        ];
    }

    /**
     * De favicon-URL (het tab-icoontje). Valt terug op het header-logo wanneer
     * er geen aparte favicon is ingesteld, zodat het icoontje ook zonder extra
     * upload klopt. Geeft null wanneer geen van beide bestaat.
     */
    public static function favicon(): ?string
    {
        $header = self::current();

        return ($header['favicon'] ?? null) ?: (($header['logo'] ?? null) ?: null);
    }

    /**
     * Het MIME-type dat bij een favicon-URL past (voor het type-attribuut op de
     * <link>). Afgeleid van de bestandsextensie; null wanneer onbekend.
     */
    public static function faviconType(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? $url, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'ico' => 'image/x-icon',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /**
     * Leid de uiteindelijke href af uit de CTA-instelling.
     *
     * Bij link_type 'page' wordt de href live uit de gekozen pagina berekend
     * (slug → pad), zodat de knop blijft kloppen ook al verandert de slug —
     * én omdat PageLinkField z'n href niet betrouwbaar wegschrijft binnen de
     * statePath('cta')-group op de HeaderSettings-pagina. Bij 'url' (of als de
     * pagina niet meer bestaat) valt hij terug op de opgeslagen href.
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
