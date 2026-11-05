<?php

namespace App\Providers;

use App\Filament\Schemas\Sections\CalculatorFields;
use App\Filament\Schemas\Sections\ConversionCalculatorFields;
use App\Filament\Schemas\Sections\CaseResultsFields;
use App\Models\Page;
use App\Services\Meta\ConversionsApi;
use App\Support\LandingPages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Filament\Schemas\Components\MediaPickerField;

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
        $this->registerBookings();
        // Startformulier van de landingspagina "Speed to lead elektriciens".
        Core::formTypes()->register('speed_to_lead_start', 'Speed to lead: starten', 'forms.speed-to-lead-start-form', [], 'Speed to lead: starten');
        $this->registerGeneralSettings();
        $this->registerPageTable();
    }

    /**
     * Eigen kalender (core): herkomst van de advertentie meebewaren (`herkomst` staat
     * op Integraties aan het GHL-veld "Herkomst advertentie" gekoppeld) en het
     * Meta-serverevent "Schedule" na een geslaagde boeking.
     */
    protected function registerBookings(): void
    {
        Core::bookings()
            ->rules(ConversionsApi::RULES)
            ->data(function (Request $request): array {
                $attribution = ConversionsApi::attribution($request);

                return ['attribution' => $attribution ?: null, 'herkomst' => ConversionsApi::attributionLine($attribution) ?: null];
            })
            ->booked(fn (Request $request, Model $submission, array $contact) => app(ConversionsApi::class)
                ->send('Schedule', $request, array_filter(['email' => $contact['email'] ?? null, 'first_name' => $contact['first_name'] ?? null])));
    }

    /**
     * Blokken die enkel deze site heeft, en de extra velden op core-blokken.
     * De publieke views staan in resources/views/components/site/sections.
     */
    protected function registerBlocks(): void
    {
        Core::blocks()
            // Agenda (boeking): GoHighLevel (iframe, pop-up of eigen kalender, met de
            // formuliervelden) komt uit de core zodra "Koppeling GoHighLevel" aanstaat.
            ->extend('booking', function (array $fields): array {
                // Opmaak in dezelfde woorden en op dezelfde plek als bij het formulierblok:
                // na de tekstvelden, net vóór de agenda-instellingen. Badge en vinkjes
                // gelden voor beide opmaken (de core toont ze enkel bij "hero").
                $layout = null;
                foreach ($fields as $i => $field) {
                    if ($field instanceof Select && $field->getName() === 'layout') {
                        $layout = $field->label('Layout (op brede schermen)')->options([
                            'hero' => 'Naast de tekst (kalender rechts)',
                            'section' => 'Onder de tekst (gecentreerd)',
                        ]);
                        unset($fields[$i]);
                    }
                    if (is_object($field) && method_exists($field, 'getName') && in_array($field->getName(), ['badge', 'benefits'], true)) {
                        $field->visible(true);
                    }
                }
                $fields = array_values($fields);
                if ($layout) {
                    $providerGrid = collect($fields)->search(fn ($f) => $f instanceof Grid
                        && collect($f->getDefaultChildComponents())->contains(fn ($c) => is_object($c) && method_exists($c, 'getName') && $c->getName() === 'provider'));
                    array_splice($fields, $providerGrid === false ? count($fields) : $providerGrid, 0, [$layout]);
                }

                return $fields;
            })
            ->register('calculator', 'Calculator (gemiste omzet)', CalculatorFields::class)
            ->register('conversion_calculator', 'Calculator (website-conversie)', ConversionCalculatorFields::class)
            // Levert aanvragen van type omzetcheck: tags, bron en deal per blok (core).
            ->ghl('conversion_calculator', 'omzetcheck')
            ->register('case_results', 'Case-resultaten', CaseResultsFields::class)
            // Cases-grid komt uit de module webgoeroe/cases (eigen ontwerp in
            // components/site/sections/cases-grid).
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

                return self::formTypeAndLayoutSideBySide($fields);
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

        // Op elk bloktype: twee blokken die op dezelfde plek afwisselen, met twee
        // knoppen erboven (App\Support\SectionSwap). Bv. startformulier en agenda.
        foreach (Core::blocks()->types() as $type) {
            Core::blocks()->extend($type, fn (array $fields): array => [...$fields, self::sectionSwapFields()]);
        }
    }

    /**
     * Formulierblok: "Formulier" en "Layout (op brede schermen)" naast elkaar. In de core
     * staan ze elk in een eigen raster (naast "Telefoonnummer verplicht"); hier zetten we
     * de twee keuzes samen en de schakelaar eronder. Vindt het die velden niet (andere
     * core-versie), dan blijft alles zoals het was.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, mixed>
     */
    protected static function formTypeAndLayoutSideBySide(array $fields): array
    {
        $children = fn (mixed $c): array => $c instanceof Grid && is_array($c->getDefaultChildComponents()) ? $c->getDefaultChildComponents() : [];
        $named = fn (array $list, string $name): ?object => collect($list)->first(fn ($f) => is_object($f) && method_exists($f, 'getName') && $f->getName() === $name);

        $typeIndex = $layoutIndex = null;
        foreach ($fields as $i => $field) {
            if ($named($children($field), 'form_type')) {
                $typeIndex = $i;
            }
            if ($named($children($field), 'form_layout')) {
                $layoutIndex = $i;
            }
        }

        if ($typeIndex === null || $layoutIndex === null || $typeIndex === $layoutIndex) {
            return $fields;
        }

        $typeChildren = $children($fields[$typeIndex]);
        $type = $named($typeChildren, 'form_type');
        $layout = $named($children($fields[$layoutIndex]), 'form_layout');
        $rest = array_values(array_filter($typeChildren, fn ($f) => $f !== $type));

        $fields[$typeIndex] = Grid::make(['default' => 1, 'md' => 2])->schema([$type, $layout]);
        $fields[$layoutIndex] = $rest ? Grid::make(['default' => 1, 'md' => 2])->schema($rest) : null;

        return array_values(array_filter($fields));
    }

    /**
     * Velden "Wisselen met een ander blok". Het andere blok moet een anker hebben en
     * direct voor of na dit blok staan; anders gebeurt er niets (geen verborgen inhoud).
     */
    protected static function sectionSwapFields(): Section
    {
        return Section::make('Wisselen met een ander blok')
            ->description('Twee blokken op dezelfde plek, met twee knoppen erboven om te wisselen. Geef beide blokken een anker-ID en zet ze direct onder elkaar.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('swap_with')
                            ->label('Wisselen met (anker-ID van het andere blok)')
                            ->placeholder('bv. afspraak')
                            ->maxLength(64)
                            ->regex('/^[a-z0-9\-]*$/i')
                            ->validationMessages(['regex' => 'Enkel letters, cijfers en streepjes.']),
                        TextInput::make('swap_label')
                            ->label('Knoptekst voor dit blok')
                            ->placeholder('bv. Ik wil starten')
                            ->helperText('Leeg = de titel van het blok.')
                            ->maxLength(60),
                    ]),
                Toggle::make('swap_hidden')
                    ->label('Eerst verborgen')
                    ->helperText('Aan = dit blok verschijnt pas na een klik op zijn knop.'),
            ]);
    }

    /**
     * Instellingen → Algemeen. De CTA-banner onder blog en cases staat sinds
     * november 2026 in Instellingen → Blog en → Cases (modules).
     */
    protected function registerGeneralSettings(): void
    {
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
