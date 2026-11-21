<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Maakt van een tabel in een tekstblok een vergelijkingstabel.
 *
 * Een tabel waarvan de eerste rij enkel kopcellen (<th>) heeft, telt als
 * vergelijking: kopregel in een <thead>, eerste kolom als rijlabel, en de laatste
 * kolom als de uitgelichte keuze (label erboven, kader, vinkje in de laatste rijen).
 * Een tabel zonder kopregel blijft gewoon een tabel in de tekst.
 *
 * Het blok splitst de tekst in stukken: de gewone tekst blijft in de smalle kolom,
 * de vergelijkingstabel krijgt de breedte van de pagina.
 */
class ComparisonTable
{
    /** Aantal laatste rijen met een vinkje in de uitgelichte kolom. */
    private const CHECK_ROWS = 2;

    /**
     * @return array<int, array{type: 'html'|'table', html: string}>
     */
    public static function split(string $html): array
    {
        $parts = preg_split('~(<table\b.*?</table>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $table = str_starts_with(ltrim($part), '<table') ? self::transform($part) : null;
            $out[] = $table !== null
                ? ['type' => 'table', 'html' => $table]
                : ['type' => 'html', 'html' => $part];
        }

        return $out;
    }

    private static function transform(string $html): ?string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $table = $xpath->query('//table')->item(0);
        $rows = $xpath->query('.//tr', $table);

        if (! $table instanceof DOMElement || $rows->length < 2) {
            return null;
        }

        $first = $rows->item(0);
        $headCells = iterator_to_array($xpath->query('./th|./td', $first));

        // Alleen een rij met uitsluitend <th>'s is een kopregel.
        if (count($headCells) < 2 || $xpath->query('./td', $first)->length > 0) {
            return null;
        }

        $last = count($headCells) - 1;
        $thead = $doc->createElement('thead');
        $table->insertBefore($thead, $table->firstChild);
        $first->parentNode->removeChild($first);
        $thead->appendChild($first);

        foreach ($headCells as $i => $th) {
            $th->setAttribute('scope', 'col');
            if ($i === $last) {
                $th->setAttribute('class', 'is-featured');
            }
        }

        $body = $xpath->query('.//tr', $table);
        $dataRows = [];
        foreach ($body as $tr) {
            if ($tr->parentNode->nodeName !== 'thead') {
                $dataRows[] = $tr;
            }
        }

        foreach ($dataRows as $r => $tr) {
            foreach (iterator_to_array($xpath->query('./th|./td', $tr)) as $i => $cell) {
                if ($i === 0) {
                    $th = $doc->createElement('th');
                    while ($cell->firstChild) {
                        $th->appendChild($cell->firstChild);
                    }
                    $th->setAttribute('scope', 'row');
                    $tr->replaceChild($th, $cell);
                } elseif ($i === $last) {
                    $class = 'is-featured';
                    if ($r >= count($dataRows) - self::CHECK_ROWS) {
                        $class .= ' has-check';
                    }
                    $cell->setAttribute('class', $class);
                }
            }
        }

        return $doc->saveHTML($table) ?: null;
    }
}
