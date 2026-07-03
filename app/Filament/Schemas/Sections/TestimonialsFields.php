<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Testimonials — een grid van klantgetuigenissen met naam, bedrijf, quote en
 * optionele profielfoto + sterrenbeoordeling.
 */
class TestimonialsFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(headingRequired: false, withIntro: false),

            Repeater::make('items')
                ->label('Testimonials')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['author'] ?? null)
                ->schema([
                    Textarea::make('quote')
                        ->label('Quote')
                        ->required()
                        ->rows(3)
                        ->maxLength(500),
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('author')
                                ->label('Naam')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('company')
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
                            MediaPickerField::make('avatar', 'Profielfoto', required: false),
                        ]),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
