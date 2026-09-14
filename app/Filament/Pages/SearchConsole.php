<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Ga4StatsOverview;
use App\Filament\Widgets\GscStatsOverview;
use App\Filament\Widgets\GscTrendChart;
use App\Models\Ga4DailyMetric;
use App\Models\Ga4DimensionMetric;
use App\Models\GscDailyMetric;
use App\Models\GscDimensionMetric;
use App\Services\Ga4Collector;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
use App\Services\GscCollector;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;

/**
 * Verkeer — het GEMETEN verkeer (Groei-meetlaag), uit twee bronnen die elkaar
 * aanvullen:
 *
 *  - **Uit Google Zoeken** (Search Console): clicks, vertoningen, CTR en
 *    positie met verloop, de zoektermen en pagina's die het verkeer leveren,
 *    en de kansen (veel vertoningen, positie 4-20). Dit is hoe mensen je vonden.
 *  - **Op de site** (Analytics): sessies, bezoekers, weergaven en betrokkenheid,
 *    de meest bekeken pagina's en de kanalen waarlangs het volk binnenkomt.
 *    Dit is wat ze daarna deden.
 *
 * De kerncijfers van allebei staan bóven de tabs en zijn dus altijd zichtbaar;
 * enkel de detailtabellen zitten per bron achter een tabblad. Zo lees je in één
 * oogopslag of meer bezoek ook meer gedrag opleverde, zonder dat de pagina
 * uitdijt tot vijf tabellen onder elkaar.
 *
 * Puur een cijferscherm: koppelen, API-keys en de property-keuze staan op
 * {@see SeoSettings}. Hier blijven enkel de twee ververs-knoppen.
 *
 * Niet verwarren met het DataForSEO-overzicht: dat is een schatting die
 * maandelijks ververst; dit is wat Google zelf registreerde.
 */
class SearchConsole extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Groei';

    protected static ?string $navigationLabel = 'Verkeer';

    protected static ?string $title = 'Verkeer';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.search-console';

    /**
     * Welk tabblad openstaat: 'search' of 'site'.
     *
     * Livewire-state, zodat de blade enkel de tabellen van het actieve tabblad
     * opvraagt. Alles tegelijk renderen zou elke paginalading twee keer zoveel
     * queries kosten voor cijfers die je op dat moment niet bekijkt.
     */
    public string $tab = 'search';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['search', 'site'], true) ? $tab : 'search';
    }

    public function getSubheading(): ?string
    {
        $gsc = app(GoogleSearchConsoleService::class);

        if (! $gsc->hasOAuth() && $gsc->authMethod() === null) {
            return 'Gemeten verkeer. Koppel je Google-account op SEO-instellingen om te starten.';
        }

        $synced = $this->tablesReady() ? GscDailyMetric::where('site_url', $gsc->siteUrl)->max('updated_at') : null;

        return ($gsc->siteUrl !== '' ? $gsc->siteUrl : 'Nog geen property gekozen')
            .' — '.($synced ? 'laatst ververst '.Carbon::parse($synced)->diffForHumans() : 'nog geen cijfers opgehaald');
    }

    /**
     * Enkel de twee ververs-knoppen.
     *
     * Het koppelen en instellen staat op {@see SeoSettings}: dit is een
     * cijferscherm, en instelwerk dat je één keer doet hoort daar niet tussen.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Ververs Google-verkeer')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->visible(fn () => app(GoogleSearchConsoleService::class)->isConfigured())
                ->action('syncNow'),

            Action::make('syncAnalytics')
                ->label('Ververs Analytics')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->visible(fn () => app(GoogleAnalyticsService::class)->isConfigured())
                ->action('syncAnalyticsNow'),

            Action::make('settings')
                ->label('SEO-instellingen')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(fn () => SeoSettings::getUrl()),
        ];
    }

    public function syncNow(): void
    {
        $collector = app(GscCollector::class);

        if (! $collector->isConfigured()) {
            Notification::make()->title('Search Console is nog niet gekoppeld')->body($this->tablesReady() ? 'Koppel je Google-account op SEO-instellingen en kies een property.' : 'Draai eerst php artisan migrate.')->danger()->send();

            return;
        }

        $result = $collector->sync();

        if ($result['days'] === 0) {
            Notification::make()->title('Geen data ontvangen')->body('Controleer op SEO-instellingen of de juiste property gekozen is.')->danger()->send();

            return;
        }

        Notification::make()
            ->title($result['backfilled'] ? 'Eerste 16 maanden ingelezen' : 'Cijfers bijgewerkt')
            ->body("{$result['days']} dagen, {$result['queries']} zoektermen, {$result['pages']} pagina's.")
            ->success()
            ->send();
    }

    public function syncAnalyticsNow(): void
    {
        $collector = app(Ga4Collector::class);

        if (! $collector->isConfigured()) {
            Notification::make()
                ->title('Analytics is nog niet gekoppeld')
                ->body($this->analyticsTablesReady() ? 'Koppel opnieuw met Google op SEO-instellingen en kies een property.' : 'Draai eerst php artisan migrate.')
                ->danger()
                ->send();

            return;
        }

        $result = $collector->sync();

        if ($result['days'] === 0) {
            Notification::make()
                ->title('Geen data ontvangen')
                ->body('Staat de meetcode al op de site, en klopt de property? Analytics toont niets van vóór de dag dat het script draaide.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['backfilled'] ? 'Historiek ingelezen' : 'Analytics bijgewerkt')
            ->body("{$result['days']} dagen, {$result['pages']} pagina's, {$result['channels']} kanalen.")
            ->success()
            ->send();
    }

    public function tablesReady(): bool
    {
        return DbSchema::hasTable('gsc_daily_metrics');
    }

    public function hasData(): bool
    {
        $gsc = app(GoogleSearchConsoleService::class);

        return $this->tablesReady() && $gsc->siteUrl !== '' && GscDailyMetric::where('site_url', $gsc->siteUrl)->exists();
    }

    public function analyticsTablesReady(): bool
    {
        return DbSchema::hasTable('ga4_daily_metrics');
    }

    /** Gekoppeld met het Analytics-recht, een property gekozen, én cijfers binnen. */
    public function hasAnalyticsData(): bool
    {
        $ga = app(GoogleAnalyticsService::class);

        return $this->analyticsTablesReady()
            && $ga->propertyId !== ''
            && Ga4DailyMetric::where('property_id', $ga->propertyId)->exists();
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        $widgets = [GscStatsOverview::class];

        // De Analytics-cijferrij staat bóven de tabs en hoort dus bij het vaste
        // deel van de pagina, niet bij één tabblad.
        if ($this->hasAnalyticsData()) {
            $widgets[] = Ga4StatsOverview::class;
        }

        $widgets[] = GscTrendChart::class;

        return $widgets;
    }

    /**
     * De tabellen van het actieve tabblad. Bewust niet allebei tegelijk: dat
     * zou elke paginalading queries kosten voor cijfers die je niet ziet.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function tables(): array
    {
        if ($this->tab === 'site') {
            if (! $this->hasAnalyticsData()) {
                return ['pages' => [], 'channels' => []];
            }

            $collector = app(Ga4Collector::class);

            return [
                'pages' => $collector->top(Ga4DimensionMetric::DIMENSION_PAGE, 15),
                'channels' => $collector->top(Ga4DimensionMetric::DIMENSION_CHANNEL, 15),
            ];
        }

        $collector = app(GscCollector::class);

        return [
            'queries' => $collector->top(GscDimensionMetric::DIMENSION_QUERY, 15),
            'pages' => $collector->top(GscDimensionMetric::DIMENSION_PAGE, 15),
            'opportunities' => $collector->opportunities(15),
        ];
    }
}
