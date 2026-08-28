<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Werkwijze — de aanpak als genummerde stappen-flow (01, 02, …). Nummers
 * worden automatisch afgeleid uit de volgorde, met een afsluitende
 * boodschap en optionele CTA onder de stappen.
 */
class ProcessStepsFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Repeater::make('steps')
                ->label('Stappen')
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
