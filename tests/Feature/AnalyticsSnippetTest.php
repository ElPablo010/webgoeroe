<?php

use App\Models\Page;
use App\Models\Setting;

/**
 * De meetcode op de publieke site (Groei-meetlaag).
 *
 * Twee harde eisen: zonder meet-ID gaat er niets naar Google, en mét een ID
 * laadt gtag.js nog steeds pas ná toestemming in de cookiebanner. Een gewone
 * <script src="…gtag/js"> zou al laden vóór de bezoeker iets gekozen heeft en
 * is dus altijd fout, hoe de rest ook in elkaar zit.
 */
beforeEach(function () {
    $page = Page::create([
        'title' => 'Welkom',
        'slug' => 'home',
        'is_homepage' => true,
        'published' => true,
    ]);

    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => ['heading' => 'Welkomsttitel'],
    ]);
});

it('laadt geen Analytics zolang er geen meet-ID ingevuld is', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('googletagmanager.com', false)
        ->assertDontSee('data-analytics="ga4"', false);
});

it('laadt Analytics enkel via de consent-gestuurde loader', function () {
    Setting::set('google_analytics_id', 'G-TEST12345');

    $response = $this->get('/')->assertOk();

    $response->assertSee('data-analytics="ga4"', false)
        ->assertSee('G-TEST12345')
        ->assertSee('cookie-consent-changed', false);

    // Nooit een directe <script src="…gtag/js">: die zou vóór toestemming laden.
    expect($response->getContent())
        ->not->toMatch('/<script[^>]+src="https:\/\/www\.googletagmanager\.com/');
});
