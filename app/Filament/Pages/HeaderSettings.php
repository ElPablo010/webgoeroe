<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\MediaPickerField;
use App\Filament\Schemas\Components\PageLinkField;
use App\Models\Setting;
use App\Support\SiteHeader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class HeaderSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Header';

    protected static ?string $title = 'Header';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.header-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteHeader::current());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Logo & naam')
                    ->description('Het logo en de tekst die links in de header verschijnen.')
                    ->schema([
                        MediaPickerField::make('logo', 'Logo', required: false, helperText: 'Upload een logo of kies er één uit de mediabibliotheek. Laat leeg om geen logo te tonen.'),
                        MediaPickerField::make('favicon', 'Favicon', required: false, helperText: 'Het icoontje in de browser-tab. Bij voorkeur een vierkant PNG of SVG (min. 512×512). Laat leeg om het logo als favicon te gebruiken.'),
                        TextInput::make('name')
                            ->label('Naam')
                            ->maxLength(120)
                            ->helperText('De bedrijfsnaam naast het logo.'),
                        TextInput::make('subtitle')
                            ->label('Ondertitel')
                            ->maxLength(120)
                            ->helperText('Kleine tekst onder de naam (bv. de locatie). Laat leeg om te verbergen.'),
                    ]),

                Section::make('CTA-knop')
                    ->description('Stel de knop in die rechtsboven in de header verschijnt.')
                    ->schema([
                        Group::make()
                            ->statePath('cta')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Label')
                                    ->maxLength(64)
                                    ->helperText('Laat leeg om de knop te verbergen.'),
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
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk op site')
                ->url('/'),
        ];
    }

    public function save(): void
    {
        Setting::set(SiteHeader::KEY, $this->form->getState());

        Notification::make()
            ->title('Header opgeslagen')
            ->success()
            ->send();
    }
}
