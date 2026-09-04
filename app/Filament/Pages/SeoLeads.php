<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LeadsStatsOverview;
use App\Filament\Widgets\LeadsTrendChart;
use App\Models\Setting;
use App\Support\LeadStats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Leads — wat de website oplevert, gemeten in de eigen database (Groei-
 * meetlaag): kop-cijfers t.o.v. het doel, verloop per maand, verdeling per
 * kanaal / type / landingspagina, de recentste leads, en de nulmeting
 * (livegang-datum, maanddoel, opgave van vóór de meting).
 *
 * Bewust geen GA4: consent mode maakt die cijfers structureel onvolledig,
 * terwijl de app haar eigen conversies exact kent.
 */
class SeoLeads extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Groei';

    protected static ?string $navigationLabel = 'Leads';

    protected static ?string $title = 'Leads';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.seo-leads';

    /** @var array<string,mixed> */
    public array $data = [];

    public function getSubheading(): ?string
    {
        return 'Conversies op de website — contactvragen en andere aanvragen — met hun herkomst. Gemeten in de eigen database, zonder tracking-scripts.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'seo_live_since' => Setting::get('seo_live_since'),
            'seo_goal_leads_month' => Setting::get('seo_goal_leads_month'),
            'seo_leads_baseline' => Setting::get('seo_leads_baseline'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nulmeting & doel')
                    ->description('De livegang-datum tekent een streep in de grafieken; het doel en de opgave van de klant geven de cijfers betekenis.')
                    ->schema([
                        DatePicker::make('seo_live_since')
                            ->label('Live sinds')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->helperText('Datum waarop de (nieuwe) website live ging.'),
                        TextInput::make('seo_goal_leads_month')
                            ->label('Doel per maand')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Hoeveel leads per maand mikken we op?'),
                        TextInput::make('seo_leads_baseline')
                            ->label('Vóór de meting (per maand)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Opgave van de klant: hoeveel aanvragen per maand vóór de livegang. Het enige cijfer dat je niet kunt meten.'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            LeadsStatsOverview::class,
            LeadsTrendChart::class,
        ];
    }

    public function available(): bool
    {
        return LeadStats::available();
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'byChannel' => LeadStats::byChannel(),
            'byType' => LeadStats::byType(),
            'byLandingPath' => LeadStats::byLandingPath(),
            'recent' => LeadStats::recent(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Nulmeting opslaan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('seo_live_since', filled($state['seo_live_since'] ?? null) ? (string) $state['seo_live_since'] : null);
        Setting::set('seo_goal_leads_month', filled($state['seo_goal_leads_month'] ?? null) ? (int) $state['seo_goal_leads_month'] : null);
        Setting::set('seo_leads_baseline', filled($state['seo_leads_baseline'] ?? null) ? (int) $state['seo_leads_baseline'] : null);

        Notification::make()->title('Nulmeting opgeslagen')->success()->send();
    }
}
