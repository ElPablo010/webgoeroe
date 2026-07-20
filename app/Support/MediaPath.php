<?php

namespace App\Support;

/**
 * Houdt media-URL's relatief ("/storage/...").
 *
 * De medialibrary slaat bewust relatieve paden op, zodat content een
 * domeinwissel overleeft. Externe clients (MCP) leveren echter geregeld een
 * absolute URL aan — bijvoorbeeld omdat ze een host afleiden uit een `url`-veld
 * dat de server zelf teruggaf. Staat APP_URL op de server verkeerd, dan bevriest
 * die foute host in de database en breekt elke <img> op de publieke site.
 *
 * Daarom normaliseren we bij het schrijven: een absolute URL waarvan het pad in
 * "/storage/" begint, is een pad uit onze eigen library en wordt teruggebracht
 * tot dat pad. Een externe URL die niet naar /storage/ wijst laten we ongemoeid.
 */
class MediaPath
{
    public static function relative(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return $url;
        }

        return $path;
    }

    /**
     * Normaliseer elke media-URL in een geneste content-array (bv. het
     * `content`-blok van een case: solution.image_url, testimonial.avatar_url).
     *
     * @param  array<mixed>  $content
     * @return array<mixed>
     */
    public static function relativeInContent(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = self::relativeInContent($value);
            } elseif (is_string($value) && str_ends_with((string) $key, '_url')) {
                $content[$key] = self::relative($value);
            }
        }

        return $content;
    }
}
