<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Ga4StatsOverview;
use App\Filament\Widgets\GscStatsOverview;
use App\Filament\Widgets\GscTrendChart;
use App\Http\Controllers\SearchConsoleOAuthController;
use App\Models\Ga4DailyMetric;
use App\Models\Ga4DimensionMetric;
use App\Models\GscDailyMetric;
use App\Models\GscDimensionMetric;
use App\Models\Setting;
use App\Services\Ga4Collector;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
use App\Services\GscCollector;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\HtmlString;
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
 * Plus de koppeling zelf: één OAuth-consent op het eigen Google-account die
 * beide rechten dekt.
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

    /** @var array<string,mixed> */
    public array $data = [];

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
            return 'Gemeten verkeer uit Google Search Console. Koppel hieronder je Google-account om te starten.';
        }

        $synced = $this->tablesReady() ? GscDailyMetric::where('site_url', $gsc->siteUrl)->max('updated_at') : null;

        return ($gsc->siteUrl !== '' ? $gsc->siteUrl : 'Nog geen property gekozen')
            .' — '.($synced ? 'laatst ververst '.Carbon::parse($synced)->diffForHumans() : 'nog geen cijfers opgehaald');
    }

    public function mount(): void
    {
        $this->form->fill([
            'google_oauth_client_id' => Setting::get('google_oauth_client_id'),
            'google_oauth_client_secret' => Setting::get('google_oauth_client_secret'),
            'gsc_site_url' => Setting::get('gsc_site_url'),
            'google_service_account_json' => Setting::get('google_service_account_json'),
            'ga4_property_id' => Setting::get('ga4_property_id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Google-koppeling')
                    ->description('Maak in Google Cloud een OAuth-client (type "Webtoepassing") aan met onderstaande omleidings-URI, zet de Search Console API aan en zet de app op "In productie" (anders vervalt de koppeling na 7 dagen).')
                    ->schema([
                        TextInput::make('google_oauth_client_id')->label('Client-ID')->maxLength(255),
                        TextInput::make('google_oauth_client_secret')->label('Client-secret')->password()->revealable()->maxLength(255),
                        Placeholder::make('redirect_uri')
                            ->label('Omleidings-URI (kopieer exact naar Google Cloud)')
                            ->content(fn () => new HtmlString('<code style="font-size:.8125rem;user-select:all;">'.e(SearchConsoleOAuthController::redirectUri()).'</code>'))
                            ->columnSpanFull(),
                        TextInput::make('gsc_site_url')
                            ->label('Property')
                            ->maxLength(191)
                            ->helperText('Wordt na het koppelen automatisch ingevuld. Domein-property = sc-domain:jouwdomein.be; URL-voorvoegsel = https://www.jouwdomein.be/ (mét slash).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Google Analytics')
                    ->description('Dezelfde koppeling hierboven dekt ook Analytics. Zet in Google Cloud wel de Analytics Data API én de Analytics Admin API aan.')
                    ->collapsed()
                    ->schema([
                        TextInput::make('ga4_property_id')
                            ->label('Property-ID')
                            ->numeric()
                            ->maxLength(64)
                            ->helperText('Wordt na het koppelen automatisch ingevuld. Dit is het getal uit Beheer → Property-instellingen, niet het G-XXXX meet-ID uit de meetcode.'),
                    ]),

                Section::make('Alternatief: service account')
                    ->description('Enkel als OAuth niet kan. Plak de JSON-sleutel en voeg het service-account-e-mailadres als gebruiker toe aan de property in Search Console.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('google_service_account_json')->label('JSON-sleutel')->rows(4),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $gsc = app(GoogleSearchConsoleService::class);
        $ga = app(GoogleAnalyticsService::class);

        return [
            // Ook zichtbaar wanneer je al gekoppeld bent maar het Analytics-recht
            // mist. Google kan een bestaand token niet uitbreiden, dus je moet
            // opnieuw door het toestemmingsscherm — maar je hoeft de koppeling
            // daarvoor niet eerst te verbreken. Dit knopje bespaart die omweg.
            Action::make('connect')
                ->label(fn () => $gsc->hasOAuth() ? 'Analytics mee koppelen' : 'Verbinden met Google')
                ->icon(Heroicon::OutlinedLink)
                ->color('primary')
                ->visible(fn () => $gsc->canStartOAuth() && (! $gsc->hasOAuth() || $ga->needsReconsent()))
                ->url(route('seo.gsc.oauth.redirect')),

            Action::make('sync')
                ->label('Ververs nu')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->visible(fn () => $gsc->isConfigured())
                ->action('syncNow'),

            Action::make('syncAnalytics')
                ->label('Analytics verversen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->visible(fn () => app(GoogleAnalyticsService::class)->isConfigured())
                ->action('syncAnalyticsNow'),

            Action::make('chooseProperty')
                ->label('Andere property kiezen')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->visible(fn () => app(GoogleAnalyticsService::class)->hasGrantedScope(GoogleAnalyticsService::SCOPE) && $gsc->hasOAuth())
                ->schema([
                    Select::make('property')
                        ->label('Analytics-property')
                        ->options(fn () => $this->propertyOptions())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ga = app(GoogleAnalyticsService::class);
                    $ga->setPropertyId((string) $data['property']);
                    $this->data['ga4_property_id'] = $ga->propertyId;
                    Notification::make()->title('Analytics-property ingesteld')->body('Klik op "Analytics verversen" om de cijfers op te halen.')->success()->send();
                }),

            Action::make('chooseSite')
                ->label('Andere site kiezen')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->visible(fn () => $gsc->hasOAuth() || $gsc->serviceAccountEmail() !== null)
                ->schema([
                    Select::make('site')
                        ->label('Property')
                        ->options(fn () => $this->siteOptions())
                        ->required()
                        ->helperText('De domein-property geeft het volledigste beeld (alle subdomeinen en varianten).'),
                ])
                ->action(function (array $data) use ($gsc) {
                    $gsc->setSiteUrl((string) $data['site']);
                    $this->data['gsc_site_url'] = $gsc->siteUrl;
                    Notification::make()->title("We volgen {$gsc->siteUrl} op")->body('Klik op "Ververs nu" om de cijfers op te halen.')->success()->send();
                }),

            Action::make('disconnect')
                ->label('Koppeling verbreken')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->visible(fn () => $gsc->hasOAuth())
                ->requiresConfirmation()
                ->modalDescription('De opgehaalde cijfers blijven bewaard; alleen de toestemming wordt vergeten. Opnieuw koppelen kan altijd.')
                ->action(function () use ($gsc) {
                    $gsc->disconnect();
                    Notification::make()->title('Search Console-koppeling verbroken')->success()->send();
                }),

            Action::make('save')
                ->label('Instellingen opslaan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->keyBindings(['mod+s'])
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['google_oauth_client_id', 'google_oauth_client_secret', 'gsc_site_url', 'google_service_account_json', 'ga4_property_id'] as $key) {
            Setting::set($key, filled($state[$key] ?? null) ? trim((string) $state[$key]) : null);
        }

        Notification::make()->title('Instellingen opgeslagen')->success()->send();
    }

    public function syncNow(): void
    {
        $collector = app(GscCollector::class);

        if (! $collector->isConfigured()) {
            Notification::make()->title('Search Console is nog niet gekoppeld')->body($this->tablesReady() ? 'Koppel eerst je Google-account en kies een property.' : 'Draai eerst php artisan migrate.')->danger()->send();

            return;
        }

        $result = $collector->sync();

        if ($result['days'] === 0) {
            Notification::make()->title('Geen data ontvangen')->body('Controleer de koppeling en of de property klopt (zie "Andere site kiezen").')->danger()->send();

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
                ->body($this->analyticsTablesReady() ? 'Koppel opnieuw met Google en kies een property.' : 'Draai eerst php artisan migrate.')
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

    /** @return array<string,string> */
    protected function propertyOptions(): array
    {
        $properties = app(GoogleAnalyticsService::class)->listProperties() ?? [];

        $options = [];
        foreach ($properties as $property) {
            $label = $property['name'];
            if ($property['account'] !== '') {
                $label .= ' ('.$property['account'].')';
            }
            $options[$property['id']] = $label;
        }
        asort($options);

        return $options;
    }

    /** @return array<string,string> */
    protected function siteOptions(): array
    {
        $sites = app(GoogleSearchConsoleService::class)->listSites() ?? [];

        $options = [];
        foreach ($sites as $site) {
            $options[$site] = str_starts_with($site, 'sc-domain:')
                ? substr($site, strlen('sc-domain:')).' (domein)'
                : $site.' (URL-voorvoegsel)';
        }
        asort($options);

        return $options;
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
