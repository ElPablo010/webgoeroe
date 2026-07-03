<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

class Url
{
    /**
     * Normaliseer een door de klant ingegeven link tot een bruikbare href.
     *
     * Interne paden (`/over-ons`), ankers (`#contact`) en volledige URLs
     * (http/https/mailto/tel) blijven ongemoeid. Een kale domeinnaam zoals
     * `www.bailandolatino.be` wordt een externe https-link — anders ziet de
     * browser die als relatief pad en plakt hij hem achter de huidige URL.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['/', '#', 'http://', 'https://', 'mailto:', 'tel:'])) {
            return $value;
        }

        return 'https://'.$value;
    }

    /**
     * Resolve de definitieve href uit een PageLinkField-array (link_type, page_id, href).
     *
     * Wanneer link_type = 'page' wordt de slug live opgezocht — het opgeslagen href-veld
     * is niet betrouwbaar omdat Filament verborgen fields soms niet dehydrateert. Page-slugs
     * worden binnen de request gecached zodat meerdere CTA's naar dezelfde pagina geen
     * extra queries kosten.
     *
     * @param  array<string, mixed>  $cta
     */
    public static function resolveCtaHref(array $cta, string $fallback = '/'): string
    {
        static $cache = [];

        if (($cta['link_type'] ?? null) === 'page' && ! empty($cta['page_id'])) {
            $id = (int) $cta['page_id'];

            if (! array_key_exists($id, $cache)) {
                $page = Page::find($id);
                $cache[$id] = $page ? ($page->is_homepage ? '/' : '/'.$page->slug) : null;
            }

            if ($cache[$id] !== null) {
                return $cache[$id];
            }
        }

        return $cta['href'] ?? $fallback;
    }
}
