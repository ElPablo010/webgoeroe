<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Landingspagina's: pagina's voor advertentieverkeer zonder menu en footer,
 * zodat de bezoeker niet wegklikt en op de pagina blijft tot hij converteert.
 *
 * Welke pagina's dat zijn, kies je op Instellingen → Algemeen (pagina-ID's
 * onder de setting-sleutel 'landing_pages'). De layout toont dan geen header en
 * enkel een minimale footer met de verplichte juridische links.
 */
class LandingPages
{
    public const KEY = 'landing_pages';

    /**
     * @return list<int>
     */
    public static function ids(): array
    {
        $stored = Setting::get(self::KEY, []);

        return array_values(array_map('intval', is_array($stored) ? $stored : []));
    }

    public static function contains(mixed $page): bool
    {
        return $page !== null && in_array((int) $page->getKey(), self::ids(), true);
    }

    public static function add(int $pageId): void
    {
        Setting::set(self::KEY, array_values(array_unique([...self::ids(), $pageId])));
    }
}
