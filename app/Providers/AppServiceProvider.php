<?php

namespace App\Providers;

use App\Filament\Schemas\Sections\CalculatorFields;
use App\Filament\Schemas\Sections\CaseResultsFields;
use App\Filament\Schemas\Sections\CasesGridFields;
use App\Support\ContentSeo;
use App\Support\SiteCta;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
            ->register('calculator', 'Calculator (gemiste omzet)', CalculatorFields::class)
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
    }
}
