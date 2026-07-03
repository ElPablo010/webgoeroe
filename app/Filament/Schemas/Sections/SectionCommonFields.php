<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class SectionCommonFields
{
    public static function make(bool $withBackground = true): array
    {
        $sectionId = TextInput::make('section_id')
            ->label('Anker-ID (optioneel)')
            ->helperText('Voor anchor-links zoals #contact. Lege waarde = geen ID.')
            ->maxLength(64)
            ->regex('/^[a-z0-9\-]*$/i')
            ->validationMessages([
                'regex' => 'Enkel letters, cijfers en streepjes.',
            ]);

        // Het sectienummer (de editorial "01", "02", …) wordt niet langer manueel
        // ingegeven maar automatisch afgeleid uit de sectie-volgorde bij het renderen
        // (zie resources/views/pages/show.blade.php). De hero krijgt geen nummer.
        if (! $withBackground) {
            return [$sectionId];
        }

        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    $sectionId,
                    Select::make('background')
                        ->label('Achtergrond')
                        ->options(SectionBackground::options())
                        ->default(SectionBackground::DEFAULT)
                        ->selectablePlaceholder(false),
                ]),
        ];
    }
}
