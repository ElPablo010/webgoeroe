<?php

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * De bloknamen volgen de gedeelde core-standaard (text, reviews, booking,
 * hero.height). Zie CLAUDE.md → "Bloknamen volgen de gedeelde core-standaard".
 */
function alignedPage(array $sections): Page
{
    $page = Page::create([
        'title' => 'Core',
        'slug' => 'core',
        'published' => true,
    ]);

    foreach ($sections as $i => [$type, $content]) {
        $page->sections()->create([
            'section_type' => $type,
            'position' => $i,
            'content' => $content,
        ]);
    }

    return $page;
}

it('renders the text, reviews and booking blocks', function () {
    alignedPage([
        ['hero', ['heading' => 'Kop', 'height' => 'medium']],
        ['text', ['heading' => 'Lange tekst', 'body' => '<p>Doorlopende inhoud.</p>']],
        ['reviews', ['heading' => 'Wat klanten zeggen', 'items' => [
            ['highlight' => 'Van 3 naar 14 aanvragen', 'quote' => 'Top samenwerking', 'name' => 'Kevin D.', 'role' => 'Elektricien', 'rating' => '5'],
        ]]],
        ['booking', ['layout' => 'section', 'heading' => 'Plan een gesprek', 'provider' => 'calendly', 'url' => 'https://calendly.com/x/gesprek', 'privacy_note' => 'We delen je gegevens met niemand.']],
        ['booking', ['layout' => 'hero', 'badge' => 'Gratis', 'heading' => 'Boek nu', 'benefits' => ['Persoonlijke analyse'], 'provider' => 'calendly', 'url' => 'https://calendly.com/x/scan']],
    ]);

    get('/core')
        ->assertOk()
        ->assertSee('min-h-[60vh]', escape: false)
        ->assertSee('Doorlopende inhoud.')
        ->assertSee('Van 3 naar 14 aanvragen')
        ->assertSee('Kevin D.')
        ->assertSee('Elektricien')
        ->assertSee('data-url="https://calendly.com/x/gesprek"', escape: false)
        ->assertSee('We delen je gegevens met niemand.')
        ->assertSee('Persoonlijke analyse')
        // De hero-opmaak zet de embed-parameters zelf achter de URL.
        ->assertSee('https://calendly.com/x/scan?hide_event_type_details=1', escape: false);
});

it('opens the page edit form with the new blocks', function () {
    $page = alignedPage([
        ['hero', ['heading' => 'Kop', 'height' => 'compact']],
        ['text', ['heading' => 'Tekst', 'body' => '<p>Inhoud</p>']],
        ['reviews', ['items' => [['quote' => 'Q', 'name' => 'N']]]],
        ['booking', ['layout' => 'hero', 'provider' => 'calendly', 'url' => 'https://calendly.com/x']],
    ]);

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get("/admin/pages/{$page->id}/edit")
        ->assertOk()
        ->assertSee('Agenda (boeking)')
        ->assertSee('Reviews');
});

it('migrates old section types to the core standard and back', function () {
    $migration = require database_path('migrations/2026_10_07_120000_align_section_types_with_core.php');

    $page = alignedPage([]);
    $insert = fn (string $type, array $content) => DB::table('page_sections')->insertGetId([
        'sectionable_type' => Page::class,
        'sectionable_id' => $page->id,
        'section_type' => $type,
        'position' => 0,
        'content' => json_encode($content),
    ]);

    $hero = $insert('hero', ['heading' => 'H']);
    $heroCompact = $insert('hero', ['heading' => 'H', 'size' => 'compact']);
    $text = $insert('rich_text', ['heading' => 'T', 'body' => '<p>b</p>']);
    $reviews = $insert('testimonials', ['items' => [['title' => 'T', 'quote' => 'Q', 'author' => 'A', 'company' => 'C', 'avatar' => '/a.webp', 'rating' => '5']]]);
    $calendly = $insert('calendly', ['provider' => 'calendly', 'calendly_url' => 'https://calendly.com/a']);
    $bookingHero = $insert('booking_hero', ['badge' => 'B', 'calendly_url' => 'https://calendly.com/b']);

    $migration->up();
    $migration->up(); // idempotent

    $row = fn (int $id) => PageSection::find($id);

    expect($row($hero)->content)->toEqual(['heading' => 'H', 'height' => 'tall'])
        ->and($row($heroCompact)->content)->toEqual(['heading' => 'H', 'height' => 'compact'])
        ->and($row($text)->section_type)->toBe('text')
        ->and($row($reviews)->section_type)->toBe('reviews')
        ->and($row($reviews)->content['items'][0])->toEqual(['highlight' => 'T', 'quote' => 'Q', 'name' => 'A', 'role' => 'C', 'image' => '/a.webp', 'rating' => '5'])
        ->and($row($calendly)->section_type)->toBe('booking')
        ->and($row($calendly)->content)->toEqual(['provider' => 'calendly', 'url' => 'https://calendly.com/a', 'layout' => 'section'])
        ->and($row($bookingHero)->content)->toEqual(['badge' => 'B', 'url' => 'https://calendly.com/b', 'layout' => 'hero']);

    $migration->down();

    expect($row($heroCompact)->content)->toEqual(['heading' => 'H', 'size' => 'compact'])
        ->and($row($text)->section_type)->toBe('rich_text')
        ->and($row($reviews)->section_type)->toBe('testimonials')
        ->and($row($reviews)->content['items'][0])->toEqual(['title' => 'T', 'quote' => 'Q', 'author' => 'A', 'company' => 'C', 'avatar' => '/a.webp', 'rating' => '5'])
        ->and($row($calendly)->section_type)->toBe('calendly')
        ->and($row($calendly)->content)->toEqual(['provider' => 'calendly', 'calendly_url' => 'https://calendly.com/a'])
        ->and($row($bookingHero)->section_type)->toBe('booking_hero');
});
