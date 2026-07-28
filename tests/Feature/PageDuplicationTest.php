<?php

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function makePageForDuplication(array $attributes = []): Page
{
    return Page::create(array_merge([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'published' => true,
    ], $attributes));
}

it('duplicates a page with all its sections', function () {
    $page = makePageForDuplication();
    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => ['heading' => 'Titel'],
    ]);
    $page->sections()->create([
        'section_type' => 'text',
        'position' => 1,
        'content' => ['body' => '<p>Tekst</p>'],
    ]);

    $copy = $page->duplicate();

    expect($copy->id)->not->toBe($page->id)
        ->and($copy->title)->toBe('Over ons (kopie)')
        ->and($copy->slug)->toBe('over-ons-kopie')
        ->and($copy->sections()->count())->toBe(2)
        ->and($copy->sections()->pluck('section_type')->all())->toBe(['hero', 'text'])
        ->and($copy->sections()->first()->content)->toBe(['heading' => 'Titel']);

    // De bron blijft ongemoeid.
    expect($page->refresh()->sections()->count())->toBe(2);
});

it('never publishes the copy and never makes it the homepage', function () {
    $page = makePageForDuplication(['slug' => 'home', 'is_homepage' => true, 'published' => true]);

    $copy = $page->duplicate();

    expect($copy->published)->toBeFalse()
        ->and($copy->is_homepage)->toBeFalse()
        ->and($copy->translation_of)->toBeNull();
});

it('keeps slugs unique when duplicating the same page twice', function () {
    $page = makePageForDuplication();

    $first = $page->duplicate();
    $second = $page->duplicate();

    expect($first->slug)->toBe('over-ons-kopie')
        ->and($second->slug)->toBe('over-ons-kopie-2');
});

it('duplicates through the table action and redirects to the copy', function () {
    $this->actingAs(User::factory()->create());

    $page = makePageForDuplication();
    $page->sections()->create([
        'section_type' => 'hero',
        'position' => 0,
        'content' => ['heading' => 'Titel'],
    ]);

    $component = Livewire::test(ListPages::class)
        ->callAction(TestAction::make('duplicate')->table($page))
        ->assertHasNoActionErrors();

    $copy = Page::where('slug', 'over-ons-kopie')->firstOrFail();

    expect($copy->published)->toBeFalse()
        ->and($copy->sections()->count())->toBe(1);

    $component->assertRedirect(PageResource::getUrl('edit', ['record' => $copy]));
});

it('copies the seo fields along', function () {
    $page = makePageForDuplication([
        'meta_title' => 'Over ons | Webgoeroe',
        'meta_description' => 'Wie we zijn.',
        'is_cornerstone' => true,
        'seo_image_url' => '/storage/website/test.webp',
    ]);

    $copy = $page->duplicate();

    expect($copy->meta_title)->toBe('Over ons | Webgoeroe')
        ->and($copy->meta_description)->toBe('Wie we zijn.')
        ->and($copy->is_cornerstone)->toBeTrue()
        ->and($copy->seo_image_url)->toBe('/storage/website/test.webp');
});
