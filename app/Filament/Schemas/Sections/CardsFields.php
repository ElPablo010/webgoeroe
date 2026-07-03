<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\MediaPickerField;
use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Cards — kaart/tegel-grid met een instelbaar aantal kolommen.
 *
 * Elke kaart kan een icoon óf een afbeelding tonen, met titel, tekst, features
 * en een optionele knop.
 */
class CardsFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('columns')
                        ->label('Kolommen')
                        ->options([
                            '2' => '2 kolommen',
                            '3' => '3 kolommen',
                            '4' => '4 kolommen',
                        ])
                        ->default('3')
                        ->required(),

                    TextInput::make('max_visible')
                        ->label('Initieel zichtbaar')
                        ->helperText('Bv. 6 toont eerst 6 kaarten + een "Toon meer"-knop. Leeg = altijd alle kaarten.')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('Alle'),
                ]),

            Repeater::make('cards')
                ->label('Kaarten')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            TextInput::make('title')
                                ->label('Titel')
                                ->required()
                                ->maxLength(120),
                            Select::make('media_type')
                                ->label('Media-type')
                                ->helperText('Icoon of een afbeelding bovenaan de kaart.')
                                ->options([
                                    'icon' => 'Icoon',
                                    'image' => 'Afbeelding',
                                ])
                                ->default('icon')
                                ->live()
                                ->required(),
                        ]),
                    // Lucide-iconnaam (zie lucide.dev). Vrije tekst zodat je niet
                    // beperkt bent tot een vaste lijst; vul per project een Select
                    // met je eigen iconenset in als dat handiger is voor de klant.
                    TextInput::make('icon')
                        ->label('Icoon (lucide-naam)')
                        ->placeholder('bv. star, heart, map-pin')
                        ->visible(fn (Get $get): bool => ($get('media_type') ?? 'icon') === 'icon'),
                    MediaPickerField::make('image', 'Afbeelding', required: false)
                        ->visible(fn (Get $get): bool => ($get('media_type') ?? 'icon') === 'image'),
                    TextInput::make('subtitle')
                        ->label('Ondertitel')
                        ->maxLength(160),
                    Textarea::make('description')
                        ->label('Beschrijving')
                        ->rows(3)
                        ->maxLength(500),
                    TagsInput::make('features')
                        ->label('Features (chips)')
                        ->placeholder('Voeg feature toe'),
                    TextInput::make('cta_label')
                        ->label('Knoptekst (optioneel)')
                        ->placeholder('Lees meer'),
                    PageLinkField::make(required: false),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
