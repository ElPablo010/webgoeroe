<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Gemiste-omzet-calculator — de bezoeker vult zelf zijn cijfers in (gemiste
 * oproepen, gemiddelde opdracht, slagingskans) en ziet wat gemiste oproepen
 * kosten. De startwaarden zijn rekenvoorbeelden, geen claims.
 */
class CalculatorFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Grid::make(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('default_missed')
                        ->label('Startwaarde: gemiste oproepen per week')
                        ->numeric()->minValue(0)->default(5),
                    TextInput::make('default_value')
                        ->label('Startwaarde: gemiddelde opdracht (€)')
                        ->numeric()->minValue(0)->default(800),
                    TextInput::make('default_rate')
                        ->label('Startwaarde: slagingskans (%)')
                        ->numeric()->minValue(0)->maxValue(100)->default(30),
                ]),

            TextInput::make('disclaimer')
                ->label('Voetnoot')
                ->maxLength(255)
                ->default('Rekenvoorbeeld met jouw eigen cijfers. Geen belofte van resultaat.'),

            TextInput::make('cta_label')
                ->label('Knoptekst (optioneel)')
                ->placeholder('Bekijk hoe we dit oplossen'),
            PageLinkField::make(required: false),
        ];
    }
}
