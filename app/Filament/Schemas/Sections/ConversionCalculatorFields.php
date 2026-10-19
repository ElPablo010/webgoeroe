<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Webgoeroe\Core\Filament\Schemas\Components\PageLinkField;
use Webgoeroe\Core\Filament\Schemas\Sections\HeadingFields;

/**
 * Website-conversiecalculator — de bezoeker vult bezoekers, huidige aanvragen,
 * sluitratio en klantwaarde in en ziet wat zijn website laat liggen t.o.v. een
 * doelconversie. Een sector kiezen vult sluitratio en klantwaarde voor; met
 * ?sector=<sleutel> in de URL staat die sector meteen geselecteerd (Meta-ads per
 * sector). De startwaarden zijn rekenvoorbeelden, geen claims.
 */
class ConversionCalculatorFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Grid::make(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('default_visitors')
                        ->label('Startwaarde: bezoekers per maand')
                        ->numeric()->minValue(0)->default(500),
                    TextInput::make('default_leads')
                        ->label('Startwaarde: aanvragen per maand')
                        ->numeric()->minValue(0)->default(5),
                    TextInput::make('target_rate')
                        ->label('Doelconversie (%)')
                        ->numeric()->minValue(0)->maxValue(100)->default(3),
                ]),

            Repeater::make('sectors')
                ->label('Sectoren (vullen sluitratio en klantwaarde voor)')
                ->schema([
                    TextInput::make('key')
                        ->label('Sleutel (voor ?sector=)')
                        ->required()
                        ->alphaDash()
                        ->maxLength(40),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(60),
                    TextInput::make('close_rate')
                        ->label('Sluitratio (%)')
                        ->numeric()->minValue(0)->maxValue(100)->required(),
                    TextInput::make('client_value')
                        ->label('Klantwaarde (€)')
                        ->numeric()->minValue(0)->required(),
                ])
                ->columns(4)
                ->defaultItems(0)
                ->collapsible(),

            TextInput::make('disclaimer')
                ->label('Voetnoot')
                ->maxLength(255)
                ->default('Schatting op basis van jouw cijfers. Geen garantie, wel een realistisch beeld.'),

            TextInput::make('cta_label')
                ->label('Knoptekst (optioneel)')
                ->placeholder('Boek een gratis Bottleneck Scan'),
            PageLinkField::make(required: false),
        ];
    }
}
