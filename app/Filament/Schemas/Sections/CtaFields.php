<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\TextInput;

/**
 * CTA — een call-to-action banner: kop + korte tekst + één of meer knoppen.
 * Het optionele `note`-veld toont een kleine caption onder de knoppen
 * (bv. "Geen creditcard · Geen verplichtingen").
 */
class CtaFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            CtaLinkSchema::repeater(),

            TextInput::make('note')
                ->label('Noot onder knoppen (optioneel)')
                ->placeholder('bv. Geen creditcard · Geen verplichtingen')
                ->maxLength(200),
        ];
    }
}
