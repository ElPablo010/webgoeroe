<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Webgoeroe\Core\Filament\Schemas\Components\PageLinkField;
use Webgoeroe\Core\Filament\Schemas\Sections\HeadingFields;
use Webgoeroe\Core\Integrations\GoHighLevel\Client;
use Webgoeroe\Core\Support\Modules;
use App\Support\LeadCheck;

/**
 * Velden van het blok "Lekkencheck" (0.15.10): vragen met hun rol, het rekenmodel,
 * de gegevens vóór het resultaat, de resultaatteksten en welke GHL-velden het
 * resultaat krijgen. Rekenwijze: Support\LeadCheck.
 */
class LeadCheckFields
{
    public static function make(): array
    {
        $client = new Client;
        $ghl = fn (): bool => Modules::enabled(Modules::GHL);

        return [
            ...HeadingFields::make(headingRequired: false, introLabel: 'Intro (optioneel)'),

            Section::make('Vragen')
                ->description('Elke vraag krijgt een rol: die bepaalt hoe het antwoord meetelt. Bij een lek is de waarde per optie het verlies in %: 0 = groen, de hoogste = rood, daartussen oranje.')
                ->collapsible()
                ->schema([
                    Repeater::make('questions')
                        ->hiddenLabel()
                        ->collapsible()
                        ->collapsed()
                        ->reorderable()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => filled($state['question'] ?? null)
                            ? $state['question'].' ('.(LeadCheck::ROLES[$state['role'] ?? ''] ?? '?').')'
                            : null)
                        ->addActionLabel('Vraag toevoegen')
                        ->defaultItems(0)
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                TextInput::make('question')
                                    ->label('Vraag')
                                    ->required()
                                    ->maxLength(200)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                        if (blank($get('key')) && filled($state)) {
                                            $set('key', Str::of($state)->slug('_')->limit(40, '')->toString());
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('key')
                                    ->label('Sleutel')
                                    ->helperText('Kort, zonder spaties (bv. reactiesnelheid). Zo heet het veld in de aanvraag.')
                                    ->required()
                                    ->regex('/^[a-z0-9_]+$/')
                                    ->notIn(LeadCheck::RESERVED_KEYS)
                                    ->maxLength(40),
                            ]),
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                Select::make('role')
                                    ->label('Rol')
                                    ->options(LeadCheck::ROLES)
                                    ->default('leak_clients')
                                    ->required()
                                    ->live(),
                                Select::make('input')
                                    ->label('Antwoord')
                                    ->options(['choice' => 'Keuze uit opties', 'number' => 'Getal invullen'])
                                    ->default('choice')
                                    ->visible(fn (Get $get): bool => in_array($get('role'), ['value', 'volume', 'close_rate'], true))
                                    ->live(),
                                Select::make('period')
                                    ->label('Per')
                                    ->options(['month' => 'maand', 'week' => 'week'])
                                    ->default('month')
                                    ->visible(fn (Get $get): bool => $get('role') === 'volume'),
                            ]),
                            Textarea::make('info')
                                ->label('Uitleg bij de vraag (info-icoontje, optioneel)')
                                ->rows(2)
                                ->maxLength(500),
                            Repeater::make('options')
                                ->label(fn (Get $get): string => match ($get('role')) {
                                    'value' => 'Opties (waarde = bedrag in €)',
                                    'volume' => 'Opties (waarde = aantal aanvragen)',
                                    'close_rate' => 'Opties (waarde = % dat een opdracht wordt)',
                                    'leak_leads' => 'Opties (waarde = % extra aanvragen die je mist)',
                                    'leak_clients' => 'Opties (waarde = % klanten die je verliest)',
                                    default => 'Opties',
                                })
                                ->schema([
                                    TextInput::make('label')->label('Antwoord')->required()->maxLength(120),
                                    TextInput::make('value')->label('Waarde')->numeric()->default(0)->required(),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->reorderable()
                                ->addActionLabel('Optie toevoegen')
                                ->visible(fn (Get $get): bool => ($get('input') ?? 'choice') !== 'number' || ! in_array($get('role'), ['value', 'volume', 'close_rate'], true)),
                            Grid::make(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get): bool => ($get('input') ?? 'choice') === 'number' && in_array($get('role'), ['value', 'volume', 'close_rate'], true))
                                ->schema([
                                    TextInput::make('min')->label('Minimum')->numeric(),
                                    TextInput::make('max')->label('Maximum')->numeric(),
                                ]),
                            Grid::make(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get): bool => in_array($get('role'), LeadCheck::LEAK_ROLES, true))
                                ->schema([
                                    TextInput::make('leak_label')
                                        ->label('Naam van het lek')
                                        ->placeholder('bv. Reactiesnelheid')
                                        ->maxLength(60),
                                    TextInput::make('leak_tag')
                                        ->label('Tag in GHL als dit lek oranje of rood is')
                                        ->placeholder('bv. lek-reactie')
                                        ->maxLength(60)
                                        ->visible($ghl),
                                    Textarea::make('advice')
                                        ->label('Tekst in het resultaat als dit lek oranje of rood is')
                                        ->helperText('Eén zin wat het kost, één concrete tip.')
                                        ->rows(2)
                                        ->maxLength(500)
                                        ->columnSpanFull(),
                                ]),
                            Select::make('ghl_field')
                                ->label('Antwoord opslaan in GHL-veld (optioneel)')
                                ->options(fn (mixed $state): array => self::ghlOptions($client, $state))
                                ->searchable()
                                ->visible($ghl),
                        ]),
                ]),

            Section::make('Rekenmodel')
                ->description('Vaste waarden voor wat je niet vraagt, en hoe voorzichtig het resultaat is.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Select::make('mode')
                        ->label('Resultaat')
                        ->options(['amount' => 'Bedrag (omzet die je laat liggen)', 'score' => 'Enkel score per lek, geen bedrag'])
                        ->default('amount')
                        ->selectablePlaceholder(false),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        TextInput::make('default_value')->label('Opdrachtwaarde als je ze niet vraagt (€)')->numeric()->default(1000),
                        TextInput::make('default_volume')->label('Aanvragen per maand als je ze niet vraagt')->numeric()->default(10),
                        TextInput::make('default_close_rate')->label('Slotratio als je ze niet vraagt (%)')->numeric()->default(30),
                    ]),
                    Grid::make(['default' => 1, 'md' => 4])->schema([
                        TextInput::make('max_close_rate')->label('Hoogste slotratio zonder lekken (%)')->numeric()->default(80),
                        TextInput::make('max_loss')
                            ->label('Hoogste verlies (% van de huidige omzet)')
                            ->helperText('Houdt het resultaat geloofwaardig. Bv. 30.')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(200)
                            ->default(100),
                        TextInput::make('low_factor')
                            ->label('Ondergrens van de bandbreedte (%)')
                            ->helperText('50 = de laagste schatting is de helft van de hoogste.')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(100)
                            ->default(50),
                        Select::make('result_period')
                            ->label('Bedrag tonen per')
                            ->options(['year' => 'jaar', 'month' => 'maand', 'both' => 'maand en jaar'])
                            ->default('year'),
                    ]),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('price')
                            ->label('Vergelijken met een prijs per maand (€, optioneel)')
                            ->helperText('Toont hoeveel klanten die prijs terugverdienen: {prijs_klanten}.')
                            ->numeric(),
                        TextInput::make('price_text')
                            ->label('Zin bij die vergelijking')
                            ->placeholder('Onze aanpak kost €297 per maand: dat is {prijs_klanten} klant.')
                            ->maxLength(200),
                    ]),
                ]),

            Section::make('Gegevens vóór het resultaat')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('gate_heading')->label('Titel')->default('Waar mogen we je resultaat naartoe sturen?')->maxLength(160),
                        Toggle::make('phone_required')->label('Gsm verplicht')->default(true)->inline(false),
                    ]),
                    Textarea::make('gate_text')->label('Tekst')->rows(2)->maxLength(400),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('gate_button')->label('Knop')->default('Toon mijn resultaat')->maxLength(60),
                        TextInput::make('privacy_note')->label('Privacyzin onder de knop')->maxLength(200),
                    ]),
                ]),

            Section::make('Resultaat')
                ->description('Placeholders: {voornaam}, {laag_jaar}, {hoog_jaar}, {laag_maand}, {hoog_maand}, {klanten}, {score}, {totaal}, {lekken}, {grootste_lek}, {prijs_klanten}.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('result_heading')
                        ->label('Titel')
                        ->default('{voornaam}, je laat naar schatting {laag_jaar} tot {hoog_jaar} per jaar liggen.')
                        ->maxLength(200),
                    TextInput::make('result_heading_score')
                        ->label('Titel bij "Enkel score"')
                        ->default('{voornaam}, {score} van de {totaal} punten zijn in orde.')
                        ->maxLength(200),
                    Textarea::make('result_text')
                        ->label('Tekst')
                        ->default('Dat zijn ongeveer {klanten} klanten per maand. Het grootste lek: {grootste_lek}.')
                        ->rows(3)
                        ->maxLength(800),
                    TextInput::make('disclaimer')
                        ->label('Kleine letters')
                        ->default('Een voorzichtige schatting op basis van je antwoorden, geen belofte.')
                        ->maxLength(300),
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('cta_label')->label('Knop onder het resultaat (optioneel)')->maxLength(60),
                    ]),
                    PageLinkField::make(required: false),
                ]),

            Section::make('Resultaat naar GoHighLevel')
                ->description('Naam, e-mail en gsm gaan altijd mee. Tags, bron en deal: onderaan bij GoHighLevel. Zet "Lekkencheck" aan op Instellingen → Integraties.')
                ->statePath('ghl_result')
                ->collapsible()
                ->collapsed()
                ->visible($ghl)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema(collect(self::RESULT_FIELDS)->map(
                        fn (string $label, string $key) => Select::make($key)->label($label)->options(fn (mixed $state): array => self::ghlOptions($client, $state))->searchable()
                    )->values()->all()),
                ]),
        ];
    }

    /**
     * De eigen velden uit GHL, plus het huidige veld als dat niet (meer) in de
     * lijst staat (GHL even onbereikbaar, veld hernoemd): opslaan mag nooit
     * blokkeren op een koppeling.
     *
     * @return array<string, string>
     */
    protected static function ghlOptions(Client $client, mixed $state): array
    {
        $options = $client->customFields();

        if (is_string($state) && $state !== '' && ! array_key_exists($state, $options)) {
            $options[$state] = "Gekoppeld veld ({$state})";
        }

        return $options;
    }

    /** Resultaatvelden die naar een eigen GHL-veld kunnen (sleutel in de aanvraag → label). */
    public const RESULT_FIELDS = [
        'result_year' => 'Bedrag per jaar',
        'result_month' => 'Bedrag per maand',
        'lost_clients' => 'Gemiste klanten per maand',
        'top_leak' => 'Grootste lek',
        'top_leak_advice' => 'Tip bij het grootste lek',
        'leaks' => 'Lekken (oranje en rood)',
        'score' => 'Score',
    ];
}
