<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

/**
 * FAQ — accordeon van vraag/antwoord-paren. Voedt automatisch een FAQPage
 * JSON-LD-node (zie Seo::faqNode) voor rich results + GEO.
 */
class FaqFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            Repeater::make('items')
                ->label('Vragen & antwoorden')
                ->collapsible()
                ->collapsed()
                ->collapseAllAction(RepeaterToggleStyle::make())
                ->expandAllAction(RepeaterToggleStyle::make())
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->schema([
                    TextInput::make('question')
                        ->label('Vraag')
                        ->required()
                        ->maxLength(255),
                    RichEditor::make('answer')
                        ->label('Antwoord')
                        ->required()
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ]),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),
        ];
    }
}
