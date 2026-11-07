<?php

use App\Models\Page;
use App\Support\LandingPages;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Integrations\GoHighLevel\SubmissionSync;
use Webgoeroe\Core\Support\LeadCheck;

beforeEach(function () {
    Page::query()->create(['title' => 'Speed to lead elektriciens', 'slug' => 'speed-to-lead-elektriciens', 'locale' => 'nl', 'published' => true]);
    (require database_path('migrations/2026_11_07_120000_create_lekkencheck_elektriciens_page.php'))->up();
});

/**
 * Landingspagina /lekkencheck-elektriciens (migratie
 * 2026_11_07_120000_create_lekkencheck_elektriciens_page, core-blok lead_check).
 */
it('maakt de lekkencheck-pagina als landingspagina met 7 vragen', function () {
    $page = Page::query()->where('slug', 'lekkencheck-elektriciens')->sole();
    $section = $page->sections->firstWhere('section_type', 'lead_check');

    expect(LandingPages::contains($page))->toBeTrue()
        ->and($page->meta_robots)->toBe('noindex, nofollow')
        ->and(LeadCheck::questions($section->content))->toHaveCount(7)
        ->and(SubmissionSync::settingsFor('lead_check')['enabled'])->toBeTrue();

    $this->get('/lekkencheck-elektriciens')
        ->assertOk()
        ->assertSee('Hoeveel klanten bellen je concurrent terwijl jij op de werf staat?')
        ->assertSee('Wat is een gemiddelde opdracht bij jou waard?')
        ->assertSee('coreLeadTracking', false);
});

it('geeft een geloofwaardig resultaat voor een gemiddelde elektricien', function () {
    $content = Page::query()->where('slug', 'lekkencheck-elektriciens')->sole()
        ->sections->firstWhere('section_type', 'lead_check')->content;

    // €500-1.500, 10-20 per week, 3 of 4 op 10, 1 op 3 niet opgenomen, dezelfde dag
    // terugbellen, voicemail buiten de uren, soms opvolgen.
    $result = LeadCheck::calculate($content, ['opdrachtwaarde' => 1, 'oproepen' => 2, 'slotratio' => 1, 'gemiste_oproepen' => 2, 'terugbellen' => 1, 'buiten_de_uren' => 1, 'opvolging' => 1]);

    expect($result['monthly']['high'])->toBeLessThanOrEqual($result['revenue'] * 0.3)
        ->and($result['top_leak'])->toBe('Gemiste oproepen')
        ->and(LeadCheck::money($result['yearly']['low']))->toBe('€ 57.300')
        ->and(LeadCheck::money($result['yearly']['high']))->toBe('€ 81.800');
});
