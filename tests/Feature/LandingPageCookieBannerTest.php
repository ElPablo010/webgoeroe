<?php

use App\Models\Page;
use App\Support\LandingPages;

/**
 * Landingspagina's krijgen een compacte cookiebanner (één korte regel, geen
 * titel of icoon), zodat hij het formulier niet afdekt. Gewone pagina's houden
 * de volledige banner.
 */
it('shows a compact cookie banner on landing pages only', function () {
    $gewoon = Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);
    $actie = Page::create(['title' => 'Website die verkoopt', 'slug' => 'actie', 'published' => true]);
    LandingPages::add($actie->id);

    $this->get('/over-ons')->assertOk()->assertSee('Wij respecteren jouw privacy');

    $this->get('/actie')->assertOk()
        ->assertDontSee('Wij respecteren jouw privacy')
        ->assertSee('We gebruiken cookies voor statistieken en advertenties')
        ->assertSee('Accepteren');
});
