<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class CtaLinkSchema
{
    public static function repeater(string $name = 'ctas', string $label = 'Knoppen (CTA\'s)'): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->collapsible()
            ->collapsed()
            ->collapseAllAction(RepeaterToggleStyle::make())
            ->expandAllAction(RepeaterToggleStyle::make())
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('label')
                            ->label('Label')
                            ->required()
                            ->maxLength(64),
                        Select::make('variant')
                            ->label('Stijl')
                            ->options([
                                'primary' => 'Primair',
                                'secondary' => 'Secundair',
                                'ghost' => 'Ghost (minimaal)',
                            ])
                            ->default('primary')
                            ->required(),
                    ]),
                PageLinkField::make(),
            ])
            ->defaultItems(0)
            ->reorderable()
            ->columns(1);
    }
}
