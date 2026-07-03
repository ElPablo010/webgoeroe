<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Case-resultaten — KPI statistieken-strip.
 *
 * Toont een grid van stat-tegels met een grote waarde (bv. "3×", "+120%"),
 * een omschrijving en een optionele subbeschrijving.
 */
class CaseResultsFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(headingRequired: false),

            Repeater::make('stats')
                ->label('Statistieken')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => filled($state['value']) ? $state['value'].' — '.($state['label'] ?? '') : null)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('value')
                                ->label('Waarde')
                                ->required()
                                ->maxLength(32)
                                ->placeholder('3×'),
                            TextInput::make('label')
                                ->label('Omschrijving')
                                ->required()
                                ->maxLength(80)
                                ->placeholder('meer conversies'),
                        ]),
                    TextInput::make('sublabel')
                        ->label('Subbeschrijving (optioneel)')
                        ->maxLength(120)
                        ->placeholder('t.o.v. vorig jaar'),
                ])
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
