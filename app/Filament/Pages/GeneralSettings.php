<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\PageLinkField;
use App\Models\Setting;
use App\Support\SiteCta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Algemene, app-brede instellingen die niet bij één specifieke feature horen:
 * de bedrijfsidentiteit (merknaam, omschrijving) en de gedeelde AI-configuratie
 * (Anthropic-key + "feiten voor AI"). Meerdere features lezen hier uit — vandaag
 * de SEO-laag, later bv. de chatbot — dus deze pagina hoort in élk project thuis.
 *
 * Alles landt als losse sleutels in de Setting-tabel; features lezen die keys
 * rechtstreeks uit (bewust géén seo_-prefix, want ze zijn app-breed).
 */
class GeneralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Instellingen';

    protected static ?string $navigationLabel = 'Algemeen';

    protected static ?string $title = 'Algemene instellingen';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.general-settings';

    /** @var array<string,mixed> */
    public array $data = [];

    /** Sleutels die 1-op-1 naar de Setting-tabel gaan. */
    protected array $keys = [
        'brand_name',
        'business_description',
        'anthropic_api_key',
        'ai_facts',
    ];

    public function mount(): void
    {
        $data = [];
        foreach ($this->keys as $key) {
            $data[$key] = Setting::get($key);
        }

        // De CTA is een genest blok en gaat als één groep naar de Setting-tabel.
        $data['cta'] = SiteCta::current();

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bedrijfsgegevens')
                    ->description('Basisidentiteit van de site. Wordt o.a. als context aan AI-functies meegegeven.')
                    ->schema([
                        TextInput::make('brand_name')
                            ->label('Merknaam')
                            ->helperText('Leeg = APP_NAME.'),
                        TextInput::make('business_description')
                            ->label('Korte omschrijving')
                            ->helperText('bv. "een dansschool in Antwerpen". Geeft AI-functies context.'),
                    ])
                    ->columns(2),

                Section::make('AI')
                    ->description('Gedeelde AI-configuratie voor alle features die op Claude draaien (SEO-advies, later de chatbot).')
                    ->schema([
                        TextInput::make('anthropic_api_key')
                            ->label('Anthropic API-key')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->helperText('Leeg = de ANTHROPIC_API_KEY uit .env (indien gezet).'),
                        Textarea::make('ai_facts')
                            ->label('Feiten voor AI')
                            ->rows(6)
                            ->columnSpanFull()
                            ->helperText('Adres, openingsuren, prijzen, USP\'s… AI-functies gebruiken ENKEL deze feiten in gegenereerde content — zo verzinnen ze niets.'),
                    ]),

                Section::make('Call-to-action')
                    ->description('De afsluitende banner onderaan elk blogartikel en elke case. Wijzig je de bestemming hier, dan volgen alle CTA\'s mee.')
                    ->schema([
                        Group::make()
                            ->statePath('cta')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Titel')
                                    ->maxLength(120),
                                Textarea::make('body')
                                    ->label('Tekst')
                                    ->rows(3),
                                TextInput::make('button_label')
                                    ->label('Knoptekst')
                                    ->maxLength(80),
                                PageLinkField::make(required: false),
                            ]),
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

        Setting::set(SiteCta::KEY, $state['cta'] ?? []);

        Notification::make()->title('Algemene instellingen opgeslagen')->success()->send();
    }
}
