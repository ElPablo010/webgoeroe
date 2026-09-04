<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GscStatsOverview;
use App\Filament\Widgets\GscTrendChart;
use App\Http\Controllers\SearchConsoleOAuthController;
use App\Models\GscDailyMetric;
use App\Models\GscDimensionMetric;
use App\Models\Setting;
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
 * Verkeer — het GEMETEN Google-verkeer uit Search Console (Groei-meetlaag):
 * clicks, vertoningen, CTR en positie met verloop, de zoektermen en
 * pagina's die het verkeer leveren, en de kansen (veel vertoningen, positie
 * 4-20). Plus de koppeling zelf: OAuth op het eigen Google-account.
 *
 * Niet verwarren met het DataForSEO-overzicht: dat is een schatting die
 * maandelijks ververst; dit is wat Google zelf registreerde.
 */
class SearchConsole extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Groei';

    protected static ?string $navigationLabel = 'Verkeer';

    protected static ?string $title = 'Google-verkeer';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.search-console';

    /** @var array<string,mixed> */
    public array $data = [];

    public function getSubheading(): ?string
    {
        $gsc = app(GoogleSearchConsoleService::class);

        if (! $gsc->hasOAuth() && $gsc->authMethod() === null) {
            return 'Gemeten verkeer uit Google Search Console. Koppel hieronder je Google-account om te starten.';
        }

        $synced = $this->tablesReady() ? GscDailyMetric::where('site_url', $gsc->siteUrl)->max('updated_at') : null;

        return ($gsc->siteUrl !== '' ? $gsc->siteUrl : 'Nog geen property gekozen')
            . ' — ' . ($synced ? 'laatst ververst ' . Carbon::parse($synced)->diffForHumans() : 'nog geen cijfers opgehaald');
    }

    public function mount(): void
    {
        $this->form->fill([
            'gsc_oauth_client_id' => Setting::get('gsc_oauth_client_id'),
            'gsc_oauth_client_secret' => Setting::get('gsc_oauth_client_secret'),
            'gsc_site_url' => Setting::get('gsc_site_url'),
            'gsc_service_account_json' => Setting::get('gsc_service_account_json'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Google-koppeling')
                    ->description('Maak in Google Cloud een OAuth-client (type "Webtoepassing") aan met onderstaande omleidings-URI, zet de Search Console API aan en zet de app op "In productie" (anders vervalt de koppeling na 7 dagen).')
                    ->schema([
                        TextInput::make('gsc_oauth_client_id')->label('Client-ID')->maxLength(255),
                        TextInput::make('gsc_oauth_client_secret')->label('Client-secret')->password()->revealable()->maxLength(255),
                        Placeholder::make('redirect_uri')
                            ->label('Omleidings-URI (kopieer exact naar Google Cloud)')
                            ->content(fn () => new HtmlString('<code style="font-size:.8125rem;user-select:all;">' . e(SearchConsoleOAuthController::redirectUri()) . '</code>'))
                            ->columnSpanFull(),
                        TextInput::make('gsc_site_url')
                            ->label('Property')
                            ->maxLength(191)
                            ->helperText('Wordt na het koppelen automatisch ingevuld. Domein-property = sc-domain:jouwdomein.be; URL-voorvoegsel = https://www.jouwdomein.be/ (mét slash).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Alternatief: service account')
                    ->description('Enkel als OAuth niet kan. Plak de JSON-sleutel en voeg het service-account-e-mailadres als gebruiker toe aan de property in Search Console.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('gsc_service_account_json')->label('JSON-sleutel')->rows(4),
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

        return [
            Action::make('connect')
                ->label('Verbinden met Google')
                ->icon(Heroicon::OutlinedLink)
                ->color('primary')
                ->visible(fn () => $gsc->canStartOAuth() && ! $gsc->hasOAuth())
                ->url(route('seo.gsc.oauth.redirect')),

            Action::make('sync')
                ->label('Ververs nu')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->visible(fn () => $gsc->isConfigured())
                ->action('syncNow'),

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

        foreach (['gsc_oauth_client_id', 'gsc_oauth_client_secret', 'gsc_site_url', 'gsc_service_account_json'] as $key) {
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

    /** @return array<string,string> */
    protected function siteOptions(): array
    {
        $sites = app(GoogleSearchConsoleService::class)->listSites() ?? [];

        $options = [];
        foreach ($sites as $site) {
            $options[$site] = str_starts_with($site, 'sc-domain:')
                ? substr($site, strlen('sc-domain:')) . ' (domein)'
                : $site . ' (URL-voorvoegsel)';
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

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [GscStatsOverview::class, GscTrendChart::class];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function tables(): array
    {
        $collector = app(GscCollector::class);

        return [
            'queries' => $collector->top(GscDimensionMetric::DIMENSION_QUERY, 15),
            'pages' => $collector->top(GscDimensionMetric::DIMENSION_PAGE, 15),
            'opportunities' => $collector->opportunities(15),
        ];
    }
}
