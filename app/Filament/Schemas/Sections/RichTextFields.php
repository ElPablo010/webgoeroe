<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\RichEditor;

/**
 * Lange tekst — voor juridische/informatieve pagina's (cookiebeleid,
 * privacybeleid, algemene voorwaarden) die geen conversie-secties nodig
 * hebben, enkel doorlopende tekst met kopjes, lijsten en eventueel tabellen.
 */
class RichTextFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(withIntro: false),

            RichEditor::make('body')
                ->label('Inhoud')
                ->required()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'blockquote'],
                    ['bulletList', 'orderedList'],
                    ['table'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
        ];
    }
}
