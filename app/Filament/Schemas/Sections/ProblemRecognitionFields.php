<?php

namespace App\Filament\Schemas\Sections;

use App\Filament\Schemas\Components\PageLinkField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Probleemherkenning — de bezoeker herkent zichzelf in een groeiprobleem
 * vóór we oplossingen tonen. Probleemkaarten in een 2×2-grid met stille
 * indicatie-chips (bewust géén diensten-CTA's), afgesloten door een
 * verbindende boodschap met één primaire CTA.
 */
class ProblemRecognitionFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(),

            JourneyFields::repeater(),

            Repeater::make('problems')
                ->label('Probleemkaarten')
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
                        ->placeholder('bv. users, timer, repeat'),
                    Textarea::make('description')
                        ->label('Beschrijving')
                        ->rows(3)
                        ->maxLength(500),
                    TagsInput::make('tags')
                        ->label('Indicaties (chips)')
                        ->helperText('Subtiele hints waar het probleem kan zitten, bv. "Traffic · CRO". Geen diensten-CTA\'s.')
                        ->placeholder('Voeg indicatie toe'),
                ])
                ->columns(1)
                ->defaultItems(0)
                ->reorderable(),

            RichEditor::make('closing')
                ->label('Afsluitende boodschap')
                ->toolbarButtons([['bold', 'italic'], ['undo', 'redo']]),

            TextInput::make('cta_label')
                ->label('Knoptekst (optioneel)')
                ->placeholder('Ontdek waar jouw bottleneck zit'),
            PageLinkField::make(required: false),
        ];
    }
}
