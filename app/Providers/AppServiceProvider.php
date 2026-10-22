<?php

namespace App\Providers;

use App\Filament\Schemas\Sections\CalculatorFields;
use App\Filament\Schemas\Sections\ConversionCalculatorFields;
use App\Filament\Schemas\Sections\CaseResultsFields;
use App\Filament\Schemas\Sections\CasesGridFields;
use App\Models\Page;
use App\Services\GoHighLevel\ContactFields;
use App\Support\BookingForm;
use App\Support\ContentSeo;
use App\Support\LandingPages;
use App\Support\SiteCta;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Filament\Schemas\Components\MediaPickerField;
use Webgoeroe\Core\Filament\Schemas\Components\PageLinkField;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Eigen (merk-)consentscherm voor de OAuth-flow, o.a. de claude.ai-connector.
        Passport::authorizationView('oauth.authorize');

        $this->registerBlocks();
        // Startformulier van de landingspagina "Speed to lead elektriciens".
        Core::formTypes()->register('speed_to_lead_start', 'Speed to lead: starten', 'forms.speed-to-lead-start-form', [], 'Speed to lead: starten');
        $this->registerGeneralSettings();
        $this->registerPageTable();

        // Cases en blog in sitemap.xml, llms.txt en de meta/JSON-LD (Seo::fromPost, …).
        ContentSeo::register();
    }

    /**
     * Blokken die enkel deze site heeft, en de extra velden op core-blokken.
     * De publieke views staan in resources/views/components/site/sections.
     */
    protected function registerBlocks(): void
    {
        Core::blocks()
            // Agenda (boeking): naast Cal.com en Calendly ook de GoHighLevel-agenda,
            // zodat een boeking onder hetzelfde contact in GHL landt (workflows
            // stoppen dan de opvolging). URL = de iframe-URL van de agenda.
            ->configure('booking', [
                'providers' => ['calcom' => 'Cal.com', 'calendly' => 'Calendly', 'ghl' => 'GoHighLevel'],
                'url_providers' => ['calcom', 'calendly', 'ghl'],
            ])
            // GoHighLevel kan als iframe in de pagina (standaard), achter een knop in een
            // pop-up, of als eigen kalender op de site (vrije momenten via de GHL-API): dan breekt de GHL-agenda de paginalayout niet en is er geen scroll-in-scroll.
            ->extend('booking', function (array $fields): array {
                $fields[] = Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('ghl_display')
                            ->label('Weergave (GoHighLevel)')
                            ->options([
                                'iframe' => 'Iframe in de pagina',
                                'popup' => 'Pop-up achter een knop',
                                'own' => 'Eigen kalender (zonder iframe)',
                            ])
                            ->default('iframe')
                            ->selectablePlaceholder(false)
                            ->live(),
                        TextInput::make('popup_button_label')
                            ->label('Knoptekst pop-up')
                            ->default('Kies een moment')
                            ->maxLength(80)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('ghl_display') === 'popup'),
                    ])
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('provider') === 'ghl');

                // Eigen kalender: de formuliervelden staan hier (één plek), elk gekoppeld aan
                // een contactveld in GoHighLevel. Nieuwe velden maak je via "+" meteen in GHL aan.
                $fields[] = Repeater::make('form_fields')
                    ->label('Formuliervelden')
                    ->helperText('De vragen bij het boeken, in deze volgorde. Elk antwoord komt in het gekoppelde veld van het contact in GoHighLevel. Een e-mailveld is verplicht.')
                    ->schema([
                        Hidden::make('key')->default(fn (): string => Str::lower(Str::random(8))),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('label')
                                ->label('Vraag')
                                ->required()
                                ->maxLength(200)
                                ->live(onBlur: true),
                            Select::make('target')
                                ->label('Opslaan in (GoHighLevel)')
                                ->options(fn (ContactFields $ghl): array => $ghl->options())
                                ->getOptionLabelUsing(fn (?string $value, ContactFields $ghl): ?string => $ghl->label($value))
                                ->helperText(fn (ContactFields $ghl): ?string => $ghl->custom() === null
                                    ? 'De eigen velden uit GoHighLevel konden niet opgehaald worden (GHL_API_TOKEN ontbreekt of heeft geen recht locations/customFields.readonly).'
                                    : null)
                                ->required()
                                ->searchable()
                                ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (! BookingForm::isValidTarget((string) $value)) {
                                        $fail('Kies een veld uit de lijst.');
                                    }
                                })
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Naam van het nieuwe veld in GoHighLevel')
                                        ->required()
                                        ->maxLength(150),
                                    Toggle::make('large')
                                        ->label('Groot tekstvak (meerdere regels)'),
                                ])
                                ->createOptionModalHeading('Nieuw veld in GoHighLevel')
                                ->createOptionUsing(function (array $data, ContactFields $ghl): string {
                                    $target = $ghl->create($data['name'], (bool) ($data['large'] ?? false));

                                    if ($target === null) {
                                        Notification::make()->title('Het veld kon niet aangemaakt worden in GoHighLevel.')->danger()->send();

                                        throw new Halt;
                                    }

                                    return $target;
                                })
                                ->suffixAction(
                                    Action::make('refreshGhlFields')
                                        ->icon('heroicon-m-arrow-path')
                                        ->tooltip('Lijst vernieuwen uit GoHighLevel')
                                        ->action(fn (ContactFields $ghl) => $ghl->refresh()),
                                ),
                            Select::make('type')
                                ->label('Soort veld')
                                ->options(BookingForm::TYPES)
                                ->default('text')
                                ->required()
                                ->selectablePlaceholder(false),
                            TextInput::make('placeholder')
                                ->label('Voorbeeldtekst in het veld (optioneel)')
                                ->maxLength(120),
                        ]),
                        Toggle::make('required')->label('Verplicht'),
                    ])
                    ->default(BookingForm::defaults())
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->reorderable()
                    ->collapsible()
                    ->collapsed()
                    // Filament klapt bij "toevoegen" álle items open; hier enkel het nieuwe.
                    ->addAction(fn (Action $action) => $action->action(function (Repeater $component): void {
                        $newUuid = $component->generateUuid();
                        $items = $component->getRawState();
                        $newUuid ? $items[$newUuid] = [] : $items[] = [];
                        $newKey = $newUuid ?? array_key_last($items);

                        $component->rawState($items);
                        $component->getChildSchema($newKey)->fill();
                        $component->collapsed(fn (?Schema $item): bool => ! str_ends_with((string) $item?->getStatePath(), '.'.$newKey));
                        $component->callAfterStateUpdated();
                        $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
                    }))
                    ->addActionLabel('Veld toevoegen')
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! collect((array) $value)->contains('target', 'email')) {
                            $fail('Koppel één veld aan "E-mailadres": zonder kan GoHighLevel geen contact maken.');
                        }
                    })
                    ->columnSpanFull()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('provider') === 'ghl' && $get('ghl_display') === 'own');

                return $fields;
            })
            ->register('calculator', 'Calculator (gemiste omzet)', CalculatorFields::class)
            ->register('conversion_calculator', 'Calculator (website-conversie)', ConversionCalculatorFields::class)
            ->register('case_results', 'Case-resultaten', CaseResultsFields::class)
            ->register('cases_grid', 'Case studies grid', CasesGridFields::class)
            // Contactgegevens naast het formulier (e-mail, telefoon, adres), net
            // onder de kop en vóór de formulierkeuze.
            ->extend('form', function (array $fields): array {
                array_splice($fields, max(0, count($fields) - 2), 0, [
                    Grid::make(['default' => 1, 'md' => 3])
                        ->schema([
                            TextInput::make('contact_email')
                                ->label('E-mailadres')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('contact_phone')
                                ->label('Telefoonnummer')
                                ->tel()
                                ->maxLength(64),
                            TextInput::make('contact_address')
                                ->label('Adres')
                                ->maxLength(255),
                        ]),
                ]);

                return $fields;
            })
            // Video naast de tekst (YouTube/Vimeo of mp4), in de plaats van de
            // afbeelding. Enkel bij "Beeld naast de tekst"; leeg = de afbeelding.
            ->extend('hero', function (array $fields): array {
                $fields[] = TextInput::make('video_url')
                    ->label('Video naast de tekst (optioneel)')
                    ->helperText('YouTube-, Vimeo- of mp4-link. Toont de video in plaats van de afbeelding, enkel bij "Beeld naast de tekst". De afbeelding blijft de terugval en de SEO-afbeelding.')
                    ->url()
                    ->maxLength(255);

                return $fields;
            })
            // Optionele afbeelding in de call-to-action: tekst links, beeld rechts.
            // Leeg = het blok blijft gecentreerd zoals voorheen.
            ->extend('cta', function (array $fields): array {
                $fields[] = MediaPickerField::make('image.src', 'Afbeelding (optioneel)', required: false);
                $fields[] = TextInput::make('image.alt')
                    ->label('Afbeelding: alt-tekst')
                    ->maxLength(255);

                return $fields;
            })
            // Reviews klaarzetten zonder ze te tonen, bv. voorbeeldgetuigenissen
            // tot er echte zijn. pages/show slaat een verborgen blok over.
            ->extend('reviews', function (array $fields): array {
                $fields[] = Toggle::make('hidden')
                    ->label('Verbergen op de site')
                    ->helperText('Het blok blijft bewaard maar verschijnt niet op de pagina.');

                return $fields;
            });
    }

    /**
     * Instellingen → Algemeen → Call-to-action: de afsluitende banner onderaan
     * elk blogartikel en elke case (App\Support\SiteCta, sleutel `cta`).
     */
    protected function registerGeneralSettings(): void
    {
        Core::generalSettings()->section(
            fn () => Section::make('Call-to-action')
                ->description('De afsluitende banner onderaan elk blogartikel en elke case. Wijzig je de bestemming hier, dan volgen alle CTA\'s mee.')
                ->schema([
                    Group::make()
                        ->statePath(SiteCta::KEY)
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
            keys: [SiteCta::KEY],
            fill: fn (): array => [SiteCta::KEY => SiteCta::current()],
        );

        Core::generalSettings()->section(
            fn () => Section::make('Landingspagina\'s')
                ->description('Pagina\'s voor advertenties: zonder menu en footer, zodat de bezoeker op de pagina blijft. Onderaan blijven enkel de juridische links staan.')
                ->schema([
                    Select::make(LandingPages::KEY)
                        ->label('Pagina\'s zonder menu en footer')
                        ->multiple()
                        ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable(),
                ]),
            keys: [LandingPages::KEY],
            fill: fn (): array => [LandingPages::KEY => LandingPages::ids()],
        );
    }

    /**
     * Paginatabel: vinkje "Landingspagina" naast "Gepubliceerd", en een filter
     * op type (gewone pagina of landingspagina). Welke pagina's landingspagina's
     * zijn, staat in Instellingen → Algemeen (LandingPages).
     */
    protected function registerPageTable(): void
    {
        Core::pageTable()
            ->columns(fn (): array => [
                IconColumn::make('landing_page')
                    ->label('Landingspagina')
                    ->state(fn (Page $record): bool => LandingPages::contains($record))
                    ->boolean(),
            ])
            ->filters(fn (): array => [
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'page' => 'Gewone pagina',
                        'landing' => 'Landingspagina',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'page' => $query->whereKeyNot(LandingPages::ids()),
                        'landing' => $query->whereKey(LandingPages::ids()),
                        default => $query,
                    }),
            ]);
    }
}
