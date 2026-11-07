<?php

use App\Models\Page;
use App\Support\LandingPages;
use Webgoeroe\Chat\Support\ChatSettings;

/**
 * Landingspagina's hebben één doel: de chatwidget (webgoeroe/chat) hoort er
 * niet op, de gewone pagina's houden hem.
 */
it('hides the chat widget on landing pages but keeps it on normal pages', function () {
    ChatSettings::save(['general' => ['enabled' => true]]);
    Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);
    $actie = Page::create(['title' => 'Website die verkoopt', 'slug' => 'actie', 'published' => true]);
    LandingPages::add($actie->id);

    $this->get('/over-ons')->assertOk()->assertSee('id="wg-chat"', false);
    $this->get('/actie')->assertOk()->assertDontSee('id="wg-chat"', false);
});

it('no longer loads the GoHighLevel chat widget', function () {
    Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);

    $this->get('/over-ons')->assertOk()->assertDontSee('widgets.leadconnectorhq.com/loader.js', false);
});
