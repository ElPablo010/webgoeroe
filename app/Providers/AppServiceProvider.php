<?php

namespace App\Providers;

use App\Filament\Schemas\Sections\CalculatorFields;
use App\Filament\Schemas\Sections\ConversionCalculatorFields;
use App\Filament\Schemas\Sections\CaseResultsFields;
use App\Filament\Schemas\Sections\CasesGridFields;
use App\Models\Page;
use App\Support\ContentSeo;
use App\Support\LandingPages;
use App\Support\SiteCta;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Webgoeroe\Core\Core;
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
                'providers' => ['calcom' => 'Cal.com', 'calendly' => 'Calendly', 'ghl' => 'GoHighLevel-agenda'],
                'url_providers' => ['calcom', 'calendly', 'ghl'],
            ])
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
