<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SeoAdvice;
use App\Filament\Widgets\SeoStatsOverview;
use App\Filament\Widgets\SeoTopMovers;
use App\Filament\Widgets\SeoTrendChart;
use App\Jobs\RunSeoCollectionJob;
use App\Services\DataForSeoService;
use App\Support\SeoStats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Het SEO-overzicht: kerncijfers, verloop, bewegingen en het laatste AI-advies.
 *
 * De pagina is een pure widget-host — alle cijfers komen uit SeoStats, zodat
 * dit scherm en het weekrapport per definitie dezelfde getallen tonen.
 */
class SeoDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Overzicht';

    protected static ?string $title = 'SEO-overzicht';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.seo-dashboard';

    public function getSubheading(): ?string
    {
        $api = app(DataForSeoService::class);

        if (! $api->isConfigured()) {
            return 'DataForSEO is nog niet ingesteld — vul je logingegevens in bij Instellingen.';
        }

        $collected = SeoStats::latest()?->created_at?->diffForHumans();

        return $collected
            ? "{$api->target} — laatst ververst {$collected}"
            : "{$api->target} — nog geen cijfers verzameld";
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SeoStatsOverview::class,
            SeoTrendChart::class,
            SeoTopMovers::class,
            SeoAdvice::class,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Ververs cijfers')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Cijfers verversen')
                ->modalDescription('Dit haalt verse posities en zichtbaarheidscijfers op bij DataForSEO. Dat verbruikt API-credits en duurt enkele minuten.')
                ->modalSubmitActionLabel('Starten')
                ->disabled(fn () => ! app(DataForSeoService::class)->isConfigured())
                ->action('refreshNow'),
        ];
    }

    /**
     * Zet de collectie in de wachtrij. Bewust een job en geen synchrone call:
     * SeoCollector::collectAll() polt met sleep() tot de SERP-tasks klaar zijn
     * en overleeft dus geen web-request.
     */
    public function refreshNow(): void
    {
        if (! app(DataForSeoService::class)->isConfigured()) {
            Notification::make()
                ->title('DataForSEO is niet geconfigureerd')
                ->body('Vul eerst je login en wachtwoord in bij SEO → Instellingen.')
                ->danger()
                ->send();

            return;
        }

        RunSeoCollectionJob::dispatch();

        Notification::make()
            ->title('Verversing gestart')
            ->body('Posities en cijfers verschijnen hier binnen enkele minuten.')
            ->success()
            ->send();
    }
}
