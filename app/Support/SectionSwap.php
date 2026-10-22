<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Twee blokken die op dezelfde plek afwisselen (paginabouwer: "Wisselen met een ander
 * blok"). Een blok met een anker en "Wisselen met" = het anker van het blok er direct
 * voor of na vormt daarmee een paar. Boven het paar staan twee knoppen; er is altijd
 * maar één van de twee zichtbaar. Bv. een startformulier en een agenda.
 *
 * Velden in de sectie-inhoud: `swap_with` (anker van het andere blok), `swap_label`
 * (knoptekst, anders de titel van het blok) en `swap_hidden` (eerst verborgen).
 *
 * Vangnet: verwijst `swap_with` naar een anker dat niet bestaat of niet ernaast staat,
 * dan vormt het blok geen paar en wordt het gewoon getoond. Er verdwijnt nooit
 * inhoud door een tikfout.
 */
class SectionSwap
{
    /**
     * Per index van de sectielijst: in welk paar ze zit.
     *
     * @return array<int, array{anchor: string, first: bool, last: bool, initial: string, members: list<array{anchor: string, label: string}>, background: ?string}>
     */
    public static function groups(Collection $sections): array
    {
        $list = $sections->values();
        $anchors = [];

        foreach ($list as $i => $section) {
            $anchor = trim((string) ($section->content['section_id'] ?? ''));
            if ($anchor !== '' && ! isset($anchors[$anchor])) {
                $anchors[$anchor] = $i;
            }
        }

        $groups = [];

        foreach ($list as $i => $section) {
            $content = $section->content ?? [];
            $own = trim((string) ($content['section_id'] ?? ''));
            $target = trim((string) ($content['swap_with'] ?? ''));

            if ($own === '' || $target === '' || $target === $own || ! isset($anchors[$target])) {
                continue;
            }

            $j = $anchors[$target];

            // Enkel buren, en een blok zit in hoogstens één paar.
            if (abs($i - $j) !== 1 || isset($groups[$i]) || isset($groups[$j])) {
                continue;
            }

            [$a, $b] = [min($i, $j), max($i, $j)];
            $members = [self::member($list[$a]), self::member($list[$b])];

            $initial = ! empty($list[$a]->content['swap_hidden']) && empty($list[$b]->content['swap_hidden'])
                ? $members[1]['anchor']
                : $members[0]['anchor'];

            foreach ([$a => true, $b => false] as $index => $isFirst) {
                $groups[$index] = [
                    'anchor' => trim((string) $list[$index]->content['section_id']),
                    'first' => $isFirst,
                    'last' => ! $isFirst,
                    'initial' => $initial,
                    'members' => $members,
                    'background' => $list[$a]->content['background'] ?? null,
                ];
            }
        }

        return $groups;
    }

    /** @return array{anchor: string, label: string} */
    private static function member(mixed $section): array
    {
        $content = $section->content ?? [];
        $anchor = trim((string) ($content['section_id'] ?? ''));
        $label = trim((string) ($content['swap_label'] ?? '')) ?: trim(strip_tags((string) ($content['heading'] ?? ''))) ?: $anchor;

        return ['anchor' => $anchor, 'label' => $label];
    }
}
