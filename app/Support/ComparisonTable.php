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

        // Expliciete rollen: de gsm-weergave zet de tabel in een grid (andere kolomvolgorde),
        // en daarmee verliezen schermlezers anders de tabelsemantiek.
        $table->setAttribute('role', 'table');
        $thead = $doc->createElement('thead');
        $thead->setAttribute('role', 'rowgroup');
        $table->insertBefore($thead, $table->firstChild);
        $first->parentNode->removeChild($first);
        $thead->appendChild($first);

        $first->setAttribute('role', 'row');

        foreach ($headCells as $i => $th) {
            $th->setAttribute('scope', 'col');
            $th->setAttribute('role', 'columnheader');
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

        foreach ($xpath->query('.//tbody', $table) as $tbody) {
            $tbody->setAttribute('role', 'rowgroup');
        }

        foreach ($dataRows as $r => $tr) {
            $tr->setAttribute('role', 'row');
            foreach (iterator_to_array($xpath->query('./th|./td', $tr)) as $i => $cell) {
                if ($i === 0) {
                    $th = $doc->createElement('th');
                    while ($cell->firstChild) {
                        $th->appendChild($cell->firstChild);
                    }
                    $th->setAttribute('scope', 'row');
                    $th->setAttribute('role', 'rowheader');
                    $tr->replaceChild($th, $cell);
                } else {
                    $cell->setAttribute('role', 'cell');
                }

                if ($i !== 0 && $i === $last) {
                    $class = 'is-featured';
                    if ($r >= count($dataRows) - self::CHECK_ROWS) {
                        $class .= ' has-check';
                        // Vinkje en inhoud naast elkaar (flex), wat de inhoud ook is: tekst,
                        // alinea's of een link. Een ::before bleef op een eigen regel staan
                        // zodra de cel een blokelement bevatte.
                        $wrap = $doc->createElement('div');
                        $wrap->setAttribute('class', 'wg-compare__check');
                        $tick = $doc->createElement('span', '✓');
                        $tick->setAttribute('class', 'wg-compare__tick');
                        $tick->setAttribute('aria-hidden', 'true');
                        $body = $doc->createElement('div');
                        $body->setAttribute('class', 'wg-compare__body');
                        while ($cell->firstChild) {
                            $body->appendChild($cell->firstChild);
                        }
                        $wrap->appendChild($tick);
                        $wrap->appendChild($body);
                        $cell->appendChild($wrap);
                    }
                    $cell->setAttribute('class', $class);
                }
            }
        }

        return $doc->saveHTML($table) ?: null;
    }
}
