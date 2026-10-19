<?php

use App\Models\Page;
use App\Models\User;
use App\Support\LandingPages;
use Filament\Tables\Columns\IconColumn;
use Livewire\Livewire;
use Webgoeroe\Core\Filament\Resources\Pages\Pages\ListPages;

/**
 * Pagina's: een vinkje "Landingspagina" naast "Gepubliceerd" en een filter op
 * type, zodat je de advertentiepagina's snel terugvindt.
 */
beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows which pages are landing pages and filters on type', function () {
    $over = Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);
    $actie = Page::create(['title' => 'Website die verkoopt', 'slug' => 'actie', 'published' => true]);
    LandingPages::add($actie->id);

    Livewire::test(ListPages::class)
        ->assertTableColumnStateSet('landing_page', true, $actie)
        ->assertTableColumnStateSet('landing_page', false, $over)
        ->assertTableColumnExists('landing_page', fn (IconColumn $column): bool => $column->getLabel() === 'Landingspagina')
        ->filterTable('type', 'landing')
        ->assertCanSeeTableRecords([$actie])
        ->assertCanNotSeeTableRecords([$over])
        ->filterTable('type', 'page')
        ->assertCanSeeTableRecords([$over])
        ->assertCanNotSeeTableRecords([$actie]);
});
