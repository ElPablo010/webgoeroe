<?php

use App\Enums\UserRole;
use App\Filament\Pages\SeoSettings;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * De zijbalk van het adminpanel: de groepsvolgorde en de uitlogknop.
 *
 * Allebei dingen die stilletjes sneuvelen. De volgorde valt terug op
 * "volgorde van ontdekken" zodra `navigationGroups()` wegvalt, en de uitlogknop
 * hangt aan een render hook die niemand opmerkt als hij niet meer vuurt.
 */
it('zet de groepen in een vaste volgorde, met Instellingen onderaan', function () {
    $groups = array_map(
        fn ($group) => is_string($group) ? $group : $group->getLabel(),
        Filament::getPanel('admin')->getNavigationGroups(),
    );

    expect($groups)->toBe(['Website', 'Groei', 'Instellingen']);
});

it('toont een uitlogknop onderaan de zijbalk', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));

    get(SeoSettings::getUrl())
        ->assertOk()
        ->assertSee('Uitloggen')
        ->assertSee(Filament::getPanel('admin')->getLogoutUrl(), false);
});

it('rendert de groepen in volgorde, met uitloggen als laatste', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $html = get(SeoSettings::getUrl())->assertOk()->getContent();

    // Positie in de markup = positie op het scherm. Zo vangen we ook een render
    // hook die op de verkeerde plek hangt, wat een losse assertSee niet ziet.
    $at = fn (string $needle) => strpos($html, $needle);

    // "Uitloggen" staat twee keer op de pagina: ook in het accountmenu rechts­
    // boven. De zijbalk-knop is de laatste, dus strrpos in plaats van strpos.
    $logout = strrpos($html, 'Uitloggen');

    expect($at('Website'))->toBeLessThan($at('Groei'))
        ->and($at('Groei'))->toBeLessThan($at('Instellingen'))
        ->and($at('Instellingen'))->toBeLessThan($logout);
});

it('toont die knop niet aan wie niet ingelogd is', function () {
    get(SeoSettings::getUrl())
        ->assertRedirect()
        ->assertDontSee('Uitloggen');
});
