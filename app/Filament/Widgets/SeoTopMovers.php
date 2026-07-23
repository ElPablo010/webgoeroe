<?php

namespace App\Filament\Widgets;

use App\Support\SeoStats;
use Filament\Widgets\Widget;

/**
 * De sterkste stijgers en dalers t.o.v. de vorige meting, naast elkaar.
 *
 * Bewust geen tabel-widget: het gaat om twee korte, tegenover elkaar gestelde
 * lijstjes, niet om doorzoekbare data. Wie de volledige lijst wil, gaat naar
 * de keywords-pagina.
 */
class SeoTopMovers extends Widget
{
    protected string $view = 'filament.widgets.seo-top-movers';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $movers = SeoStats::topMovers();

        return [
            'up' => $movers['up'],
            'down' => $movers['down'],
        ];
    }
}
