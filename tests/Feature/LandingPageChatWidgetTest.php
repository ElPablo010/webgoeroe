<?php

use App\Models\Page;
use App\Support\LandingPages;

/**
 * Landingspagina's hebben één doel: de GHL-chatwidget hoort er niet op, de
 * gewone pagina's houden hem.
 */
it('hides the chat widget on landing pages but keeps it on normal pages', function () {
    $gewoon = Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);
    $actie = Page::create(['title' => 'Website die verkoopt', 'slug' => 'actie', 'published' => true]);
    LandingPages::add($actie->id);

    $this->get('/over-ons')->assertOk()->assertSee('widgets.leadconnectorhq.com/loader.js', false);
    $this->get('/actie')->assertOk()->assertDontSee('widgets.leadconnectorhq.com/loader.js', false);
});
