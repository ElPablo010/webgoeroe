<?php

namespace App\Filament\Pages;

use App\Http\Controllers\SearchConsoleOAuthController;
use App\Models\Setting;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * SEO-instellingen: **alles wat je moet invullen** voor de Groei-module staat
 * hier bij elkaar. De Google-koppeling (Search Console én Analytics), het
 * meet-ID voor de site, de DataForSEO-credentials, de GEO-prompts en het adres
 * waar de wekelijkse briefing heen gaat.
 *
 * Bewust één plek: de koppelvelden stonden vroeger op het Verkeer-scherm, maar
 * dat is een cijferscherm. Instelwerk dat je één keer doet hoort niet tussen
 * cijfers die je wekelijks leest. Verkeer houdt enkel nog de twee
 * ververs-knoppen.
 *
 * Alles landt als losse sleutels in de Setting-tabel, exact zoals
 * `GoogleApiClient` / `DataForSeoService` / `SeoAdvisorService` / `SeoCollector`
 * ze uitlezen.
 *
 * De app-brede AI-configuratie (Anthropic-key, merknaam, omschrijving, "feiten
 * voor AI") staat bewust NIET hier maar op de algemene instellingenpagina
 * ({@see GeneralSettings}) — meerdere features delen die.
 */
class SeoSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Groei';

    /** Niet "Instellingen": de sidebar heeft al een groep met die naam. */
    protected static ?string $navigationLabel = 'SEO-instellingen';

    protected static ?string $title = 'SEO-instellingen';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.seo-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    /** Sleutels die 1-op-1 naar de Setting-tabel gaan. */
    protected array $keys = [
        'google_oauth_client_id',
        'google_oauth_client_secret',
        'google_service_account_json',
        'gsc_site_url',
        'google_analytics_id',
        'ga4_property_id',
        'dataforseo_login',
        'dataforseo_password',
        'seo_target_domain',
        'seo_location_code',
        'seo_language_code',
        'seo_report_email',
    ];

    /**
     * Enkel beheerders.
     *
     * Zwaarder dan op het cijferscherm: hier staan het client-secret, het
     * DataForSEO-wachtwoord en de service-account-sleutel. In projecten met
     * klant-rollen (bv. scholen die zelf inloggen) mag die wel in het panel,
     * maar niet hierbij. Projecten zonder `isAdmin()` merken niets.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (! method_exists($user, 'isAdmin') || $user->isAdmin());
    }

    public function mount(): void
    {
        $data = [];
        foreach ($this->keys as $key) {
            $data[$key] = Setting::get($key);
        }
        $data['seo_location_code'] = $data['seo_location_code'] ?: 2056;
        $data['seo_language_code'] = $data['seo_language_code'] ?: 'nl';
        $data['seo_geo_prompts'] = implode("\n", (array) Setting::get('seo_geo_prompts', []));

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $gsc = app(GoogleSearchConsoleService::class);
        $ga = app(GoogleAnalyticsService::class);

        return $schema
            ->components([
                Section::make('Google-koppeling')
                    ->description('Eén toestemming voor Search Console én Analytics. Maak in Google Cloud een OAuth-client ("Webtoepassing") met onderstaande omleidings-URI, zet daar de Search Console API, de Analytics Data API en de Analytics Admin API aan, en zet de app op "In productie" — op "Testing" vervalt de koppeling na 7 dagen.')
                    ->headerActions([
                        Action::make('connect')
                            ->label(fn () => $gsc->hasOAuth() ? 'Analytics mee koppelen' : 'Verbinden met Google')
                            ->icon(Heroicon::OutlinedLink)
                            ->color('primary')
                            ->visible(fn () => $gsc->canStartOAuth() && (! $gsc->hasOAuth() || $ga->needsReconsent()))
                            ->url(route('seo.gsc.oauth.redirect')),

                        Action::make('chooseSite')
                            ->label('Andere site kiezen')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->color('gray')
                            ->visible(fn () => $gsc->hasOAuth() || $gsc->serviceAccountEmail() !== null)
                            ->modalDescription('Eén Google-account kan meerdere sites beheren. Hier kies je van welke site we de cijfers ophalen.')
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
                                Notification::make()->title("We volgen {$gsc->siteUrl} op")->body('Ververs de cijfers op het Verkeer-scherm.')->success()->send();
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
                                Notification::make()->title('Google-koppeling verbroken')->success()->send();
                            }),
                    ])
                    ->schema([
                        TextInput::make('google_oauth_client_id')->label('Client-ID')->maxLength(255),
                        TextInput::make('google_oauth_client_secret')->label('Client-secret')->password()->revealable()->maxLength(255),
                        Placeholder::make('redirect_uri')
                            ->label('Omleidings-URI (kopieer exact naar Google Cloud)')
                            ->content(fn () => new HtmlString('<code style="font-size:.8125rem;user-select:all;">'.e(SearchConsoleOAuthController::redirectUri()).'</code>'))
                            ->columnSpanFull(),
                        TextInput::make('gsc_site_url')
                            ->label('Search Console-property')
                            ->maxLength(191)
                            ->helperText('Wordt na het koppelen automatisch ingevuld. Domein-property = sc-domain:jouwdomein.be; URL-voorvoegsel = https://www.jouwdomein.be/ (mét slash).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Google Analytics')
                    ->description('Twee verschillende nummers uit twee verschillende schermen in Analytics. Het meet-ID laat de site meten, het property-ID laat deze admin de cijfers ophalen.')
                    ->headerActions([
                        Action::make('chooseProperty')
                            ->label('Andere property kiezen')
                            ->icon(Heroicon::OutlinedChartBar)
                            ->color('gray')
                            ->visible(fn () => $gsc->hasOAuth() && $ga->hasGrantedScope(GoogleAnalyticsService::SCOPE))
                            ->schema([
                                Select::make('property')
                                    ->label('Analytics-property')
                                    ->options(fn () => $this->propertyOptions())
                                    ->required(),
                            ])
                            ->action(function (array $data) use ($ga) {
                                $ga->setPropertyId((string) $data['property']);
                                $this->data['ga4_property_id'] = $ga->propertyId;
                                Notification::make()->title('Analytics-property ingesteld')->body('Ververs de cijfers op het Verkeer-scherm.')->success()->send();
                            }),
                    ])
                    ->schema([
                        TextInput::make('google_analytics_id')
                            ->label('Meet-ID (op de site)')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(32)
                            ->rule('regex:/^G-[A-Z0-9]{6,}$/i')
                            ->validationMessages(['regex' => 'Een meet-ID ziet eruit als G-XXXXXXXXXX.'])
                            ->helperText('Beheer → Gegevensstreams → de webstream. Zet de meetcode op de site. Leeg = er gaat geen enkel verzoek naar Google. Laadt pas na toestemming in de cookiebanner.'),
                        TextInput::make('ga4_property_id')
                            ->label('Property-ID (voor de cijfers)')
                            ->numeric()
                            ->maxLength(64)
                            ->helperText('Beheer → Property-instellingen. Wordt na het koppelen automatisch ingevuld.'),
                    ])
                    ->columns(2),

                Section::make('DataForSEO')
                    ->description('Credentials voor de positie- en zichtbaarheidsdata. Aanmaken op dataforseo.com.')
                    ->schema([
                        TextInput::make('dataforseo_login')->label('Login')->maxLength(255),
                        TextInput::make('dataforseo_password')->label('Wachtwoord')->password()->revealable()->maxLength(255),
                        TextInput::make('seo_target_domain')->label('Doeldomein')->helperText('Zonder https:// — bv. jouwdomein.be. Leeg = afgeleid uit APP_URL.'),
                        TextInput::make('seo_location_code')->label('Locatiecode')->numeric()->helperText('DataForSEO-locatie. 2056 = België, 2528 = Nederland.'),
                        TextInput::make('seo_language_code')->label('Taalcode')->maxLength(8)->helperText('bv. nl'),
                    ])
                    ->columns(2),

                Section::make('Wekelijkse briefing')
                    ->description('De AI-key en de "feiten voor AI" staan op de algemene instellingenpagina — hier enkel waar de SEO-briefing heen gaat.')
                    ->schema([
                        TextInput::make('seo_report_email')->label('Rapport-ontvanger')->email()->helperText('Waar de wekelijkse briefing heen gaat. Leeg = MAIL_FROM_ADDRESS.'),
                    ]),

                Section::make('GEO / AI-zichtbaarheid')
                    ->description('Zoekvragen waarmee we checken of AI-assistenten (ChatGPT) je merk vermelden.')
                    ->schema([
                        Textarea::make('seo_geo_prompts')
                            ->label('Prompts (één per lijn)')
                            ->rows(5)
                            ->helperText('bv. "Waar kan ik salsa leren in Antwerpen?"'),
                    ]),

                Section::make('Alternatief: service account')
                    ->description('Enkel als OAuth niet kan. Plak de JSON-sleutel en voeg het service-account-e-mailadres als gebruiker toe aan de property in Search Console. Google blokkeert het aanmaken van zulke sleutels standaard, dus OAuth blijft de hoofdweg.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('google_service_account_json')->label('JSON-sleutel')->rows(4),
                    ]),
            ])
            ->statePath('data');
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

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($this->keys as $key) {
            Setting::set($key, filled($state[$key] ?? null) ? trim((string) $state[$key]) : null);
        }

        $prompts = collect(preg_split('/\r\n|\r|\n/', (string) ($state['seo_geo_prompts'] ?? '')))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
        Setting::set('seo_geo_prompts', $prompts);

        Notification::make()->title('SEO-instellingen opgeslagen')->success()->send();
    }
}
