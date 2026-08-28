<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Boeking — tweekoloms hero voor een boekingspagina: links badge, titel,
 * tekst en korte voordelen, rechts meteen de boekingskalender. Bewust geen
 * CTA-knop: de kalender ís de conversie.
 */
class BookingHeroFields
{
    public static function make(): array
    {
        return [
            TextInput::make('badge')
                ->label('Badge')
                ->placeholder('GRATIS · GEEN VERPLICHTINGEN')
                ->maxLength(80),

            ...HeadingFields::make(),

            TagsInput::make('benefits')
                ->label('Voordelen (vinkjes)')
                ->helperText('Korte punten onder de tekst, bv. "Persoonlijke analyse".')
                ->placeholder('Voeg voordeel toe'),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('provider')
                        ->label('Provider')
                        ->options([
                            'calendly' => 'Calendly',
                            'calcom'   => 'Cal.com',
                        ])
                        ->default('calendly')
                        ->required()
                        ->selectablePlaceholder(false),
                    Select::make('height')
                        ->label('Hoogte widget')
                        ->options([
                            '600'  => 'Klein (600px)',
                            '700'  => 'Normaal (700px)',
                            '800'  => 'Groot (800px)',
                            '1000' => 'Extra groot (1000px)',
                        ])
                        ->default('700')
                        ->selectablePlaceholder(false),
                ]),

            TextInput::make('calendly_url')
                ->label('URL')
                ->helperText('Calendly: https://calendly.com/naam/gesprek — Cal.com: https://cal.com/naam/gesprek')
                ->url()
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }
}
