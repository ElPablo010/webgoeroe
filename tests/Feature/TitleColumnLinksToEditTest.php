<?php

use App\Filament\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Resources\CaseStudies\Pages\ListCaseStudies;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Livewire\Livewire;

/**
 * De titel in een overzichtstabel is een link naar het bewerkscherm — de
 * kortste weg van lijst naar edit, zonder eerst de knoppenrij te zoeken.
 */
beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('links a page title to its edit screen', function () {
    $page = Page::create([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'published' => true,
    ]);

    Livewire::test(ListPages::class)
        ->assertTableColumnExists(
            'title',
            fn (TextColumn $column): bool => $column->getUrl() === PageResource::getUrl('edit', ['record' => $page]),
            $page,
        );
});

it('links a post title to its edit screen', function () {
    $post = Post::create([
        'title' => 'Slimmer werken met AI',
        'slug' => 'slimmer-werken-met-ai',
        'body' => '<p>Inhoud.</p>',
        'published' => true,
        'published_at' => now(),
    ]);

    Livewire::test(ListPosts::class)
        ->assertTableColumnExists(
            'title',
            fn (TextColumn $column): bool => $column->getUrl() === PostResource::getUrl('edit', ['record' => $post]),
            $post,
        );
});

it('links a case title to its edit screen', function () {
    $case = CaseStudy::create([
        'title' => 'Webshop op maat',
        'slug' => 'webshop-op-maat',
        'client' => 'Voorbeeld',
        'published' => true,
        'content' => [
            'challenge' => ['body' => 'De uitdaging.'],
            'solution' => ['body' => 'De oplossing.'],
        ],
    ]);

    Livewire::test(ListCaseStudies::class)
        ->assertTableColumnExists(
            'title',
            fn (TextColumn $column): bool => $column->getUrl() === CaseStudyResource::getUrl('edit', ['record' => $case]),
            $case,
        );
});
