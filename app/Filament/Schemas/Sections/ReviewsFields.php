<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Reviews — een grid van klantgetuigenissen met naam, bedrijf/functie, quote
 * en optionele profielfoto + sterrenbeoordeling.
 *
 * Itemvelden volgen de gedeelde core-standaard: highlight, quote, name, role,
 * rating, image.
 */
class ReviewsFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(headingRequired: false, withIntro: false),

            Repeater::make('items')
                ->label('Reviews')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->schema([
                    TextInput::make('highlight')
                        ->label('Stat-titel (optioneel)')
                        ->helperText('Kort resultaat boven de quote, bv. "Van 3 naar 14 aanvragen per maand".')
                        ->maxLength(120),
                    Textarea::make('quote')
                        ->label('Quote')
                        ->required()
                        ->rows(3)
                        ->maxLength(500),
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('name')
                                ->label('Naam')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('role')
                                ->label('Bedrijf / functie')
                                ->maxLength(100),
                        ]),
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            Select::make('rating')
                                ->label('Beoordeling (sterren)')
                                ->options([
                                    '3' => '⭐⭐⭐  3 sterren',
                                    '4' => '⭐⭐⭐⭐  4 sterren',
                                    '5' => '⭐⭐⭐⭐⭐  5 sterren',
                                ])
                                ->default('5'),
                            MediaPickerField::make('image', 'Profielfoto', required: false),
                        ]),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
