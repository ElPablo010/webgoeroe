<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Voordelen — "waarom wij"-argumenten in een icoon-links layout (2 kolommen
 * op desktop), met een afsluitende boodschap en optionele CTA eronder.
 */
class AdvantagesFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Repeater::make('items')
                ->label('Voordelen')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->schema([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('icon')
                        ->label('Icoon (lucide-naam)')
                        ->placeholder('bv. lightbulb, target, route'),
                    Textarea::make('description')
                        ->label('Beschrijving')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),

            RichEditor::make('closing')
                ->label('Afsluitende boodschap')
                ->toolbarButtons([['bold', 'italic'], ['undo', 'redo']]),

            TextInput::make('cta_label')
                ->label('Knoptekst (optioneel)')
                ->placeholder('Plan een gesprek'),
            PageLinkField::make(required: false),
        ];
    }
}
