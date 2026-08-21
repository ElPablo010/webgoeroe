<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Bepaalt of twee FAQ-vragen inhoudelijk hetzelfde vragen — als vangnet tegen
 * SEO-voorstellen die een bestaande vraag in andere woorden herhalen.
 *
 * Dit is bewust een woord-heuristiek, geen taalmodel: het model dat de
 * voorstellen genereert krijgt de bestaande vragen in zijn prompt en hoort
 * echte parafrases zelf te vermijden. Deze klasse vangt de evidente gevallen
 * die daar toch doorglippen — identieke vragen op spelling na, en vragen die
 * enkel een detail toevoegen ("… in de buurt van Antwerpen?").
 *
 * Regels, op de betekenisvolle woorden na normalisatie (kleine letters,
 * accenten weg, vulwoorden weg — vraagwoorden als "waar"/"wanneer"/"wat"
 * blijven staan, die dragen de intentie):
 *  - is de ene woordenset een deelverzameling van de andere, dan is het
 *    dezelfde vraag met een extra detail → overlap;
 *  - delen ze meer dan twee derde van hun woorden (Jaccard), dan is het een
 *    lichte herformulering → overlap.
 */
class FaqQuestionMatcher
{
    /**
     * Vulwoorden die geen intentie dragen. Vraagwoorden (waar, wanneer, wat,
     * hoe, hoeveel, welke, waarom) staan hier bewust NIET in: "waar kan ik
     * salsa leren" en "wanneer kan ik salsa leren" zijn verschillende vragen.
     */
    protected const FILLER = [
        'ik', 'je', 'jij', 'u', 'we', 'wij', 'ze', 'zij', 'jullie', 'men',
        'mijn', 'jouw', 'uw', 'ons', 'onze', 'hun',
        'de', 'het', 'een', 'dit', 'dat', 'deze', 'die', 'er', 'hier', 'daar',
        'in', 'op', 'bij', 'aan', 'van', 'voor', 'naar', 'met', 'om', 'te',
        'tot', 'uit', 'over', 'onder', 'tussen', 'door', 'zonder', 'per',
        'en', 'of', 'maar', 'als', 'dan', 'ook', 'nog', 'al', 'wel', 'even',
        'is', 'zijn', 'ben', 'bent', 'was', 'waren', 'word', 'wordt', 'worden',
        'heb', 'hebt', 'heeft', 'hebben', 'had', 'hadden',
        'kan', 'kun', 'kunt', 'kunnen', 'mag', 'mogen', 'moet', 'moeten',
        'wil', 'wilt', 'willen', 'zou', 'zouden', 'zal', 'zullen',
        'ga', 'gaat', 'gaan', 'kom', 'komt', 'komen', 'doe', 'doet', 'doen',
        'iets', 'graag', 'best',
    ];

    /** Minstens dit aandeel gedeelde woorden geldt als herformulering. */
    protected const JACCARD_THRESHOLD = 0.67;

    /** Vragen deze twee vragen inhoudelijk hetzelfde? */
    public function overlaps(string $a, string $b): bool
    {
        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);

        // Zonder betekenisvolle woorden valt er niets te vergelijken: val
        // terug op de genormaliseerde tekst zelf.
        if (!$tokensA || !$tokensB) {
            return $this->normalize($a) !== '' && $this->normalize($a) === $this->normalize($b);
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $smaller = min(count($tokensA), count($tokensB));

        // Eén enkel gedeeld woord ("prijs") is te weinig bewijs — dan enkel
        // bij een identieke set.
        if ($smaller < 2) {
            return $intersection === $smaller && count($tokensA) === count($tokensB);
        }

        // Deelverzameling: dezelfde vraag met een extra detail erbij.
        if ($intersection === $smaller) {
            return true;
        }

        $union = count($tokensA) + count($tokensB) - $intersection;

        return $union > 0 && ($intersection / $union) >= self::JACCARD_THRESHOLD;
    }

    /**
     * Dekt $text (bv. titel + slug van een bestaande pagina) het keyword al?
     * Waar wanneer élk betekenisvol woord van het keyword terugkomt in de
     * tekst — exact of als samenstelling ("salsa" zit in "salsalessen").
     *
     * Gebruikt om geen tweede pagina voor te stellen op een keyword waar al
     * een pagina voor bestaat (die misschien pas net gemaakt is en gewoon
     * nog niet rankt).
     */
    public function keywordCoveredBy(string $keyword, string $text): bool
    {
        $keywordTokens = $this->tokens($keyword);
        if (!$keywordTokens) {
            return false;
        }

        $textTokens = $this->tokens($text);

        foreach ($keywordTokens as $needle) {
            $found = false;
            foreach ($textTokens as $candidate) {
                if ($this->tokensMatch($needle, $candidate)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Zelfde woord, of het ene woord is het begin van een samenstelling van
     * het andere ("salsa" ~ "salsalessen"). Minstens 4 tekens, anders matcht
     * elk kort woordje zowat alles.
     */
    protected function tokensMatch(string $a, string $b): bool
    {
        return $a === $b
            || (strlen($a) >= 4 && str_starts_with($b, $a))
            || (strlen($b) >= 4 && str_starts_with($a, $b));
    }

    /**
     * De eerste bestaande vraag waarmee $question inhoudelijk overlapt,
     * of null als de vraag echt nieuw is.
     *
     * @param  array<int,string>  $existing
     */
    public function firstOverlapping(string $question, array $existing): ?string
    {
        foreach ($existing as $candidate) {
            if ($this->overlaps($question, (string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /** Kleine letters, accenten weg, interpunctie weg, whitespace samengevoegd. */
    protected function normalize(string $question): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($question)));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }

    /**
     * De unieke, betekenisvolle woorden van een vraag.
     *
     * @return array<int,string>
     */
    protected function tokens(string $question): array
    {
        $words = array_filter(explode(' ', $this->normalize($question)));

        return array_values(array_unique(array_diff($words, self::FILLER)));
    }
}
