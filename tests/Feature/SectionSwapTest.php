<?php

use App\Support\SectionSwap;

/**
 * "Wisselen met een ander blok": twee buren met een anker vormen een paar met
 * twee knoppen; een fout anker of geen buren verbergt nooit inhoud.
 */
function swapSection(array $content): object
{
    return (object) ['content' => $content];
}

it('maakt een paar van twee buren die naar elkaar verwijzen', function () {
    $groups = SectionSwap::groups(collect([
        swapSection(['section_id' => 'intro']),
        swapSection(['section_id' => 'starten', 'swap_with' => 'afspraak', 'swap_label' => 'Ik wil starten']),
        swapSection(['section_id' => 'afspraak', 'swap_with' => 'starten', 'swap_label' => 'Eerst een gesprek', 'swap_hidden' => true]),
    ]));

    expect($groups)->toHaveKeys([1, 2])->not->toHaveKey(0)
        ->and($groups[1]['first'])->toBeTrue()
        ->and($groups[2]['last'])->toBeTrue()
        ->and($groups[1]['initial'])->toBe('starten')
        ->and(array_column($groups[1]['members'], 'label'))->toBe(['Ik wil starten', 'Eerst een gesprek']);
});

it('toont eerst het tweede blok als het eerste verborgen staat', function () {
    $groups = SectionSwap::groups(collect([
        swapSection(['section_id' => 'a', 'swap_with' => 'b', 'swap_hidden' => true]),
        swapSection(['section_id' => 'b', 'heading' => 'Titel B']),
    ]));

    expect($groups[0]['initial'])->toBe('b')
        ->and($groups[1]['members'][1]['label'])->toBe('Titel B');
});

it('verbergt niets bij een onbekend anker of blokken die niet naast elkaar staan', function () {
    expect(SectionSwap::groups(collect([
        swapSection(['section_id' => 'a', 'swap_with' => 'bestaat-niet']),
        swapSection(['section_id' => 'b']),
    ])))->toBe([]);

    expect(SectionSwap::groups(collect([
        swapSection(['section_id' => 'a', 'swap_with' => 'c']),
        swapSection(['section_id' => 'b']),
        swapSection(['section_id' => 'c']),
    ])))->toBe([]);
});
