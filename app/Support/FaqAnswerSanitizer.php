<?php

namespace App\Support;

use App\Models\Page;

/**
 * Houdt AI- en dashboard-aangeleverde FAQ-antwoorden veilig en intern:
 * enkel eenvoudige opmaak, en links uitsluitend naar bestaande eigen
 * pagina's. Al de rest wordt gestript (tags) of uitgepakt (links) — het
 * model mag geen paden verzinnen en nooit naar buiten linken.
 *
 * Vergeet niet: de publieke FAQ-partial moet antwoorden dan als HTML
 * renderen ({!! !!}), platte antwoorden door nl2br(e(...)) halen, en het
 * FAQPage-JSON-LD krijgt strip_tags (structured data = platte tekst).
 */
class FaqAnswerSanitizer
{
    private const ALLOWED_TAGS = '<a><strong><em><u><br><p><ul><ol><li>';

    /**
     * @param  array<int,string>  $allowedPaths  Interne paden die een link mag hebben.
     */
    public static function sanitize(string $html, array $allowedPaths): string
    {
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        $clean = preg_replace_callback('/<a\b[^>]*>(.*?)<\/a>/is', function ($m) use ($allowedPaths) {
            $inner = $m[1];

            if (! preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $m[0], $href)) {
                return $inner;
            }

            $url = trim($href[1]);
            // Anker of query mag; het kale pad moet een bestaande pagina zijn.
            $path = preg_split('/[#?]/', $url)[0];

            if (! str_starts_with($url, '/') || ! in_array($path, $allowedPaths, true)) {
                return $inner;
            }

            // Enkel href overleeft — target/rel/style/onclick verdwijnen.
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . $inner . '</a>';
        }, $clean);

        return trim($clean);
    }

    /**
     * De paden waarnaar een FAQ-antwoord mag linken: alle gepubliceerde
     * pagina's (relatief pad, incl. eventueel taalvoorvoegsel).
     *
     * @return array<int,string>
     */
    public static function allowedPaths(): array
    {
        return Page::where('published', true)
            ->get()
            ->map(fn ($p) => method_exists($p, 'publicUrl') ? parse_url($p->publicUrl(), PHP_URL_PATH) : '/' . ltrim($p->slug, '/'))
            ->filter()
            ->values()
            ->all();
    }
}
