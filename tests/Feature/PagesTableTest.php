<?php

use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Livewire\Livewire;

it('renders the pages table with the homepage marked on its title', function () {
    $this->actingAs(User::factory()->create());

    $homepage = Page::create([
        'title' => 'Welkom',
        'slug' => 'home',
        'is_homepage' => true,
        'published' => true,
    ]);
    $other = Page::create([
        'title' => 'Over ons',
        'slug' => 'over-ons',
        'published' => true,
    ]);

    Livewire::test(ListPages::class)
        ->assertCanSeeTableRecords([$homepage, $other])
        // Geen aparte Homepage-kolom meer; het zit in de titelkolom.
        ->assertTableColumnDoesNotExist('is_homepage')
        ->assertTableColumnStateSet('title', 'Welkom', $homepage);
});
