<?php

use App\Filament\Pages\GeneralSettings;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteCta;
use Livewire\Livewire;

/**
 * De CTA-banner onderaan blog- en case-pagina's komt uit één instelling
 * (Instellingen → Algemeen). Een case mag afwijken; leeg = erven.
 */
beforeEach(function () {
    $page = Page::create([
        'title' => 'Gratis adviesgesprek',
        'slug' => 'gratis-adviesgesprek',
        'locale' => 'nl',
        'published' => true,
    ]);

    Setting::set(SiteCta::KEY, [
        ...SiteCta::defaults(),
        'link_type' => 'page',
        'page_id' => $page->id,
        'href' => '/gratis-adviesgesprek',
    ]);

    $this->ctaPage = $page;
});

function ctaPost(): Post
{
    return Post::create([
        'title' => 'Slimmer werken met AI',
        'slug' => 'slimmer-werken-met-ai',
        'body' => '<p>Inhoud van het artikel.</p>',
        'published' => true,
        'published_at' => now(),
    ]);
}

function ctaCase(array $cta): CaseStudy
{
    return CaseStudy::create([
        'title' => 'Bookingplatform',
        'slug' => 'bookingplatform-cta',
        'client' => 'Voorbeeld',
        'published' => true,
        'content' => [
            'challenge' => ['body' => 'De uitdaging.'],
            'solution' => ['body' => 'De oplossing.'],
            'cta' => $cta,
        ],
    ]);
}

it('links the blog CTA to the configured page', function () {
    $post = ctaPost();

    $this->get("/blog/{$post->slug}")
        ->assertOk()
        ->assertSee('href="/gratis-adviesgesprek"', escape: false)
        ->assertSee('Plan je gratis adviesgesprek')
        ->assertDontSee('href="/contact"', escape: false);
});

it('links the case CTA to the configured page when the case has no own button', function () {
    $case = ctaCase(['title' => 'Past deze aanpak bij jouw situatie?']);

    $this->get("/cases/{$case->slug}")
        ->assertOk()
        // Eigen titel blijft staan, knop erft de site-instelling.
        ->assertSee('Past deze aanpak bij jouw situatie?')
        ->assertSee('href="/gratis-adviesgesprek"', escape: false)
        ->assertSee('Plan je gratis adviesgesprek');
});

it('lets a case override the CTA button', function () {
    $case = ctaCase([
        'title' => 'Eigen titel',
        'button_label' => 'Bel ons',
        'button_url' => '/contact',
    ]);

    $this->get("/cases/{$case->slug}")
        ->assertOk()
        ->assertSee('href="/contact"', escape: false)
        ->assertSee('Bel ons');
});

it('follows the page when its slug changes', function () {
    $this->ctaPage->update(['slug' => 'adviesgesprek']);

    expect(SiteCta::current()['href'])->toBe('/adviesgesprek');
});

it('saves the CTA from the general settings page', function () {
    $page = Page::create([
        'title' => 'Contact',
        'slug' => 'contact',
        'locale' => 'nl',
        'published' => true,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(GeneralSettings::class)
        ->fillForm([
            'cta' => [
                'title' => 'Nieuwe titel',
                'body' => 'Nieuwe tekst',
                'button_label' => 'Nieuwe knop',
                'link_type' => 'page',
                'page_id' => $page->id,
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteCta::current())
        ->title->toBe('Nieuwe titel')
        ->button_label->toBe('Nieuwe knop')
        ->href->toBe('/contact');
});

it('falls back to the stored url when the linked page is gone', function () {
    $this->ctaPage->delete();

    expect(SiteCta::current()['href'])->toBe('/gratis-adviesgesprek');
});
