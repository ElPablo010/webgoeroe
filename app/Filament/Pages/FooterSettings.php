<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\MediaPickerField;
use App\Models\Setting;
use App\Support\SiteFooter;
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

class FooterSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Footer';

    protected static ?string $title = 'Footer';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.footer-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteFooter::current());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact')
                    ->description('Het contactblok bovenaan de footer: bezoekadres, reservaties en mail.')
                    ->columns(3)
                    ->schema([
                        Group::make()
                            ->statePath('contact')
                            ->columnSpanFull()
                            ->columns(3)
                            ->schema([
                                // Kolom "Bezoek ons"
                                TextInput::make('visit_label')
                                    ->label('Titel – bezoek ons')
                                    ->maxLength(40),
                                TextInput::make('reservations_label')
                                    ->label('Titel – reservaties')
                                    ->maxLength(40),
                                TextInput::make('mail_label')
                                    ->label('Titel – mail')
                                    ->maxLength(40),

                                Textarea::make('address')
                                    ->label('Adres')
                                    ->rows(2)
                                    ->maxLength(160)
                                    ->helperText('Eén regel per lijn (bv. straat op regel 1, postcode + gemeente op regel 2).'),
                                Group::make()
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Telefoonnummer')
                                            ->maxLength(40),
                                        TextInput::make('phone_hours')
                                            ->label('Openingsuren')
                                            ->maxLength(80)
                                            ->helperText('Tekst onder het nummer. Laat leeg om te verbergen.'),
                                    ]),
                                Group::make()
                                    ->schema([
                                        TextInput::make('email')
                                            ->label('E-mailadres')
                                            ->email()
                                            ->maxLength(160),
                                        TextInput::make('email_subtext')
                                            ->label('Tekst onder de e-mail')
                                            ->maxLength(80)
                                            ->helperText('Bv. "We antwoorden binnen 24 u". Laat leeg om te verbergen.'),
                                    ]),
                            ]),
                    ]),

                Section::make('Merk & tekst')
                    ->description('Het blok naast de voetermenu\'s: logo, naam, ondertitel en de tekst eronder.')
                    ->schema([
                        // Media-veld met dot-notation BUITEN de statePath-group —
                        // identiek aan de builder-secties (bv. media.src). Binnen
                        // een geneste statePath-group resolvet MediaPickerField zijn
                        // $set niet betrouwbaar, vandaar de dot-notation vanaf de root.
                        MediaPickerField::make('brand.logo', 'Logo', required: false, helperText: 'Upload een logo of kies er één uit de mediabibliotheek. Laat leeg om geen logo te tonen.'),
                        Group::make()
                            ->statePath('brand')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Naam')
                                    ->maxLength(120),
                                TextInput::make('subtitle')
                                    ->label('Ondertitel')
                                    ->maxLength(120)
                                    ->helperText('Kleine tekst onder de naam (bv. een baseline). Laat leeg om te verbergen.'),
                                Textarea::make('tagline')
                                    ->label('Tekst')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->helperText('De wervende zin onder logo + naam.'),
                            ]),
                    ]),

                Section::make('Social links')
                    ->description('Een icoon verschijnt enkel wanneer je er een link voor invult.')
                    ->columns(3)
                    ->schema([
                        Group::make()
                            ->statePath('social')
                            ->columnSpanFull()
                            ->columns(3)
                            ->schema([
                                TextInput::make('facebook')
                                    ->label('Facebook URL')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('instagram')
                                    ->label('Instagram URL')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('youtube')
                                    ->label('YouTube URL')
                                    ->url()
                                    ->maxLength(255),
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
        Setting::set(SiteFooter::KEY, $this->form->getState());

        Notification::make()
            ->title('Footer opgeslagen')
            ->success()
            ->send();
    }
}
