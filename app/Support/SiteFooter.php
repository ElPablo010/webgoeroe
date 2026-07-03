<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Footer-instellingen: contactblok, brand-blok (logo/naam/ondertitel/tagline)
 * en social links.
 *
 * defaults() levert neutrale startwaarden zodat de footer rendert vóór de klant
 * iets aanpast in de admin. current() legt de opgeslagen waarden per groep over
 * de defaults heen.
 *
 * TODO (per project): vul defaults() met de echte NAP-gegevens (naam, adres,
 * telefoon, e-mail) — die voeden ook de LocalBusiness-structured-data in Seo.
 */
class SiteFooter
{
    public const KEY = 'footer';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'contact' => [
                'visit_label' => 'Bezoek ons',
                'address' => '',
                'reservations_label' => 'Bel ons',
                'phone' => '',
                'phone_hours' => '',
                'mail_label' => 'Mail',
                'email' => '',
                'email_subtext' => '',
            ],
            'brand' => [
                'logo' => null,
                'name' => config('app.name'),
                'subtitle' => '',
                'tagline' => '',
            ],
            'social' => [
                'facebook' => '',
                'instagram' => '',
                'youtube' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $stored = Setting::get(self::KEY, []);
        $merged = [];

        // Per groep mergen (één niveau diep), zodat losse veld-defaults bewaard
        // blijven wanneer een opgeslagen blob ze (nog) niet bevat.
        foreach (self::defaults() as $group => $values) {
            $merged[$group] = is_array($values)
                ? [...$values, ...($stored[$group] ?? [])]
                : ($stored[$group] ?? $values);
        }

        return $merged;
    }
}
