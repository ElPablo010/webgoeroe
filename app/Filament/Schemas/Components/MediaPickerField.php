<?php

namespace App\Filament\Schemas\Components;

use App\Models\WebsiteMedia;
use App\Services\Website\WebsiteMediaService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaPickerField
{
    /**
     * Helptekst MOET via deze parameter, niet via een losse `->helperText()`-chain.
     * Reden: `helperText()` schrijft naar dezelfde `belowContent`-slot als de
     * Upload-/Kies-acties, en die slot wordt overschreven (geen merge) — waardoor
     * de twee knoppen verdwijnen. Door de tekst hier in dezelfde belowContent-array
     * te zetten als de acties, blijven beide bestaan.
     */
    public static function make(string $name, string $label, bool $required = true, ?string $helperText = null): TextInput
    {
        // De twee acties staan als rij ONDER het inputveld (belowContent) i.p.v.
        // in de krappe label-rij (hintActions). Daardoor staat het label altijd
        // bovenaan, het veld eronder, en blijven "Upload afbeelding" + "Kies uit
        // media" netjes naast elkaar — ook op smalle schermen, zonder dat de
        // label-rij horizontale overflow veroorzaakt.
        $belowContent = [
            Action::make("upload_{$name}")
                ->label('Upload afbeelding')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->link()
                ->modalHeading('Nieuwe afbeelding uploaden')
                ->modalSubmitActionLabel('Opslaan')
                ->schema([
                    FileUpload::make('upload')
                        ->label('Afbeelding')
                        ->image()
                        ->required()
                        ->disk('local')
                        ->directory('tmp-uploads'),
                ])
                ->action(function (array $data, callable $set) use ($name): void {
                    $path = is_array($data['upload']) ? array_values($data['upload'])[0] : $data['upload'];
                    $absolute = Storage::disk('local')->path($path);
                    $upload = new UploadedFile(
                        $absolute,
                        basename($path),
                        mime_content_type($absolute) ?: null,
                        test: true,
                    );
                    $media = app(WebsiteMediaService::class)->store($upload);
                    Storage::disk('local')->delete($path);
                    $set($name, $media->url);
                }),

            Action::make("pick_{$name}")
                ->label('Kies uit media')
                ->icon(Heroicon::OutlinedPhoto)
                ->link()
                ->modalHeading('Kies een afbeelding uit de mediabibliotheek')
                ->modalSubmitActionLabel('Kiezen')
                ->modalWidth('5xl')
                ->schema([
                    ViewField::make('media_id')
                        ->view('filament.forms.media-gallery-picker')
                        ->hiddenLabel()
                        ->required(),
                ])
                ->action(function (array $data, callable $set) use ($name): void {
                    $media = WebsiteMedia::find($data['media_id'] ?? null);
                    if ($media !== null) {
                        $set($name, $media->url);
                    }
                }),
        ];

        // Helptekst als Text-component ACHTER de acties in dezelfde belowContent-array
        // (Filament groepeert de twee aangrenzende acties tot één rij en zet de tekst
        // eronder). Niet via ->helperText() — dat zou de acties overschrijven.
        if (filled($helperText)) {
            $belowContent[] = Text::make($helperText);
        }

        // dehydrated(true): in Filament v5 kan readOnly() de dehydratie stilzwijgend
        // uitschakelen waardoor de URL verloren gaat bij opslaan. Expliciet aan zetten.
        $field = TextInput::make($name)
            ->label($label)
            ->readOnly()
            ->dehydrated(true)
            ->placeholder('Nog geen afbeelding gekozen')
            ->belowContent($belowContent);

        if ($required) {
            $field->required();
        }

        return $field;
    }
}
