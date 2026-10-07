<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Agenda (boeking) — een ingebedde boekingskalender (Calendly of Cal.com) in
 * twee opmaken:
 *   - section: gecentreerde kop + kalender eronder (het vroegere `calendly`-blok)
 *   - hero:    tweekoloms, links badge/titel/tekst/voordelen, rechts meteen de
 *              kalender (het vroegere `booking_hero`-blok). Bewust geen
 *              CTA-knop: de kalender ís de conversie.
 */
class BookingFields
{
    public static function make(): array
    {
        $isHero = fn (Get $get): bool => $get('layout') === 'hero';

        return [
            Select::make('layout')
                ->label('Opmaak')
                ->options([
                    'hero' => 'Hero (tekst links, kalender rechts)',
                    'section' => 'Sectie (kop boven, kalender eronder)',
                ])
                ->default('section')
                ->required()
                ->selectablePlaceholder(false)
                ->live(),

            TextInput::make('badge')
                ->label('Badge')
                ->placeholder('GRATIS · GEEN VERPLICHTINGEN')
                ->maxLength(80)
                ->visible($isHero),

            ...HeadingFields::make(headingRequired: false),

            TagsInput::make('benefits')
                ->label('Voordelen (vinkjes)')
                ->helperText('Korte punten onder de tekst, bv. "Persoonlijke analyse".')
                ->placeholder('Voeg voordeel toe')
                ->visible($isHero),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('provider')
                        ->label('Provider')
                        ->options([
                            'calcom' => 'Cal.com',
                            'calendly' => 'Calendly',
                        ])
                        ->default('calendly')
                        ->required()
                        ->selectablePlaceholder(false),
                    Select::make('height')
                        ->label('Hoogte widget')
                        ->options([
                            '600' => 'Klein (600px)',
                            '700' => 'Normaal (700px)',
                            '800' => 'Groot (800px)',
                            '1000' => 'Extra groot (1000px)',
                        ])
                        ->default('700')
                        ->selectablePlaceholder(false),
                ]),

            TextInput::make('url')
                ->label('URL')
                ->helperText('Calendly: https://calendly.com/naam/gesprek — Cal.com: https://cal.com/naam/gesprek')
                ->url()
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('button_label')
                        ->label('Knoptekst (optioneel)')
                        ->nullable()
                        ->maxLength(80),
                    TextInput::make('privacy_note')
                        ->label('Privacy-opmerking (optioneel)')
                        ->helperText('Kleine tekst onder de kalender.')
                        ->nullable()
                        ->maxLength(255),
                ]),
        ];
    }
}
