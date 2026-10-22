<?php

use Illuminate\Support\Facades\Blade;

function renderBooking(array $content): string
{
    return Blade::render('<x-site.sections.booking :content="$content" />', ['content' => $content + [
        'provider' => 'ghl',
        'url' => 'https://api.leadconnectorhq.com/widget/booking/abc123',
        'layout' => 'section',
        'height' => '1100',
    ]]);
}

it('toont de GoHighLevel-agenda standaard als iframe in de pagina', function () {
    $html = renderBooking([]);

    expect($html)->toContain('<iframe')
        ->and($html)->not->toContain('Kies een moment</button>');
});

it('toont de GoHighLevel-agenda als pop-up achter een knop', function () {
    $html = renderBooking(['ghl_display' => 'popup', 'popup_button_label' => 'Plan je scan']);

    expect($html)->toContain('Plan je scan')
        ->and($html)->toContain('x-teleport="body"')
        ->and($html)->toContain('open-booking.window')
        ->and($html)->toContain('<template x-if="open">');
});

it('gebruikt Kies een moment als knoptekst als er geen is ingesteld', function () {
    expect(renderBooking(['ghl_display' => 'popup']))->toContain('Kies een moment');
});
