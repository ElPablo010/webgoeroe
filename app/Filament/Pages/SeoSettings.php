<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * SEO-instellingen: DataForSEO-credentials, doeldomein, de GEO-prompts en het
 * adres waar de wekelijkse briefing heen gaat. Alles landt als losse sleutels in
 * de Setting-tabel, exact zoals DataForSeoService / SeoAdvisorService /
 * SeoCollector ze uitlezen.
 *
 * De app-brede AI-configuratie (Anthropic-key, merknaam, omschrijving, "feiten
 * voor AI") staat bewust NIET hier maar op de algemene instellingenpagina
 * ({@see GeneralSettings}) — meerdere features delen die.
 */
class SeoSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'SEO-instellingen';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.seo-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    /** Sleutels die 1-op-1 naar de Setting-tabel gaan. */
    protected array $keys = [
        'dataforseo_login',
        'dataforseo_password',
        'seo_target_domain',
        'seo_location_code',
        'seo_language_code',
        'seo_report_email',
    ];

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
        return $schema
            ->components([
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
            ])
            ->statePath('data');
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
            Setting::set($key, $state[$key] ?? null);
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
