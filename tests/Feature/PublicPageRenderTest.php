<?php

use App\Models\Page;

it('renders the homepage with 200', function () {
    $homepage = Page::create([
        'title' => 'Welkom',
        'slug' => 'home',
        'is_homepage' => true,
        'published' => true,
    ]);
    $homepage->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => [
            'heading' => 'Welkomsttitel',
            'subtitle' => '<p>Hoofdtekst van de test.</p>',
        ],
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Hoofdtekst van de test');
});

it('renders a non-homepage page by slug', function () {
    $page = Page::create([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'published' => true,
    ]);
    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => ['heading' => 'Wie wij zijn'],
    ]);

    $this->get('/over-ons')
        ->assertOk()
        ->assertSee('Wie wij zijn');
});

it('returns 404 for an unknown slug', function () {
    $this->get('/bestaat-niet')->assertNotFound();
});

it('does not render an unpublished page', function () {
    $page = Page::create([
        'title' => 'Concept',
        'slug' => 'concept',
        'published' => false,
    ]);
    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => [],
    ]);

    $this->get('/concept')->assertNotFound();
});

it('uses meta_title and meta_description in HTML head', function () {
    $page = Page::create([
        'title' => 'Plain titel',
        'slug' => 'meta-test',
        'published' => true,
        'meta_title' => 'SEO titel',
        'meta_description' => 'SEO beschrijving voor zoekmachines',
    ]);
    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => [],
    ]);

    $this->get('/meta-test')
        ->assertOk()
        ->assertSee('<title>SEO titel</title>', false)
        ->assertSee('SEO beschrijving voor zoekmachines');
});
