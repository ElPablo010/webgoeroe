<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Tekst en media — een tekstkolom naast een beeld, video of beeldengrid.
 *
 * Het media-type bepaalt welke velden zichtbaar zijn:
 *  - afbeelding   → één MediaPickerField + alt
 *  - afbeeldingen → een repeater van MediaPickerFields (grid)
 *  - video        → een video-URL (YouTube/Vimeo embed of mp4)
 */
class TextMediaFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('media_type')
                        ->label('Media-type')
                        ->options([
                            'image' => 'Afbeelding',
                            'images' => 'Afbeeldingen (grid)',
                            'video' => 'Video',
                        ])
                        ->default('image')
                        ->live()
                        ->required(),
                    Select::make('media_side')
                        ->label('Media-positie')
                        ->options([
                            'left' => 'Links',
                            'right' => 'Rechts',
                        ])
                        ->default('right')
                        ->required(),
                ]),

            // Eén afbeelding
            MediaPickerField::make('media.src', 'Afbeelding', required: false)
                ->visible(fn (Get $get): bool => ($get('media_type') ?? 'image') === 'image'),
            TextInput::make('media.alt')
                ->label('Afbeelding — alt-tekst')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => ($get('media_type') ?? 'image') === 'image'),

            // Grid van afbeeldingen
            Repeater::make('images')
                ->label('Afbeeldingen')
                ->visible(fn (Get $get): bool => ($get('media_type') ?? 'image') === 'images')
                ->schema([
                    MediaPickerField::make('src', 'Afbeelding', required: false),
                    TextInput::make('alt')->label('Alt-tekst')->maxLength(255),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),

            // Video
            TextInput::make('video_url')
                ->label('Video-URL')
                ->placeholder('https://www.youtube.com/watch?v=… of https://…/film.mp4')
                ->url()
                ->visible(fn (Get $get): bool => ($get('media_type') ?? 'image') === 'video'),

            CtaLinkSchema::repeater(),
        ];
    }
}
