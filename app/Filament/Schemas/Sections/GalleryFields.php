<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

/**
 * Gallery — een beeldengrid. Elke afbeelding gaat via MediaPickerField (upload
 * of kiezen uit de media-library), nooit een kaal URL-veld.
 */
class GalleryFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(headingRequired: false),

            Select::make('columns')
                ->label('Kolommen')
                ->options([
                    '2' => '2 kolommen',
                    '3' => '3 kolommen',
                    '4' => '4 kolommen',
                ])
                ->default('3')
                ->required(),

            Repeater::make('items')
                ->label('Afbeeldingen')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['alt'] ?? null)
                ->schema([
                    MediaPickerField::make('image', 'Afbeelding', required: false),
                    \Filament\Forms\Components\TextInput::make('alt')
                        ->label('Alt-tekst')
                        ->maxLength(255),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
