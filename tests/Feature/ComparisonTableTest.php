<?php

use App\Support\ComparisonTable;

it('maakt van een tabel met kopregel een vergelijkingstabel in een eigen stuk', function () {
    $html = '<p>Intro</p><table><tbody><tr><th></th><th>A</th><th>B</th></tr>'
        .'<tr><td>Rij 1</td><td>x</td><td>y</td></tr><tr><td>Rij 2</td><td>x</td><td>y</td></tr></tbody></table><p>Uit</p>';

    $parts = ComparisonTable::split($html);

    expect(array_column($parts, 'type'))->toBe(['html', 'table', 'html']);
    expect($parts[1]['html'])
        ->toContain('<thead>')
        ->toContain('scope="row"')
        ->toContain('class="is-featured"')
        ->toContain('is-featured has-check');
});

it('laat een tabel zonder kopregel ongemoeid', function () {
    $html = '<table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></tbody></table>';

    expect(ComparisonTable::split($html)[0]['type'])->toBe('html');
});
