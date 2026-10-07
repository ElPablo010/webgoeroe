<?php

use App\Models\Page;

// Homepage, slug, 404, concepten en meta in de head: zie de tests van
// webgoeroe/core (CorePublicPagesTest), die met de views van deze site draaien.

it('renders the customer journey above the cards of a cards section', function () {
    $page = Page::create([
        'title' => 'Diensten',
        'slug' => 'diensten-test',
        'published' => true,
    ]);
    $page->sections()->create([
        'section_type' => 'cards',
        'position' => 0,
        'content' => [
            'heading' => 'Wat we inzetten',
            'columns' => 3,
            'journey' => [
                ['icon' => 'inbox', 'label' => 'Lead'],
                ['icon' => 'phone-call', 'label' => 'Opvolging'],
                ['icon' => 'handshake', 'label' => 'Klant'],
            ],
            'cards' => [
                ['media_type' => 'icon', 'icon' => 'zap', 'title' => 'Speed-to-lead', 'description' => 'Snel reageren.'],
            ],
        ],
    ]);

    $response = $this->get('/diensten-test')->assertOk();

    // Journey steps render before the first card, and the section still shows its cards.
    $html = $response->getContent();
    expect(strpos($html, 'Opvolging'))->toBeLessThan(strpos($html, 'Speed-to-lead'));
    $response->assertSeeInOrder(['Lead', 'Opvolging', 'Klant', 'Speed-to-lead']);
});
