<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

/**
 * Klantreis-stappen — een horizontale flow (bv. Bezoeker → Lead → Klant) die
 * boven de kaarten van een sectie getoond wordt. Gedeeld door
 * ProblemRecognitionFields en CardsFields; de bijhorende view is
 * components/site/partials/journey.blade.php.
 */
class JourneyFields
{
    public static function repeater(): Repeater
    {
        return Repeater::make('journey')
            ->label('Klantreis-stappen')
            ->helperText('Horizontale flow boven de kaarten, bv. Bezoeker → Lead → Klant. Leeg = geen klantreis.')
            ->collapsible()
            ->collapsed()
            ->collapseAllAction(RepeaterToggleStyle::make())
            ->expandAllAction(RepeaterToggleStyle::make())
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->schema([
                TextInput::make('label')
                    ->label('Stap')
                    ->required()
                    ->maxLength(60),
                TextInput::make('icon')
                    ->label('Icoon (lucide-naam)')
                    ->placeholder('bv. user, globe, handshake'),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->reorderable();
    }
}
