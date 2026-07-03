<?php

namespace App\Filament\Schemas\Sections;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Gedeelde kop-velden die in (bijna) elke content-sectie terugkomen:
 * boventitel (eyebrow) + titel (heading) + intro-tekst. Eén bron zodat het
 * patroon over alle secties identiek is — neem op bovenaan een Fields::make().
 */
class HeadingFields
{
    /**
     * @param  bool  $headingRequired  Of de titel verplicht is (meestal wel).
     * @param  bool  $withIntro         Of er een intro-RichEditor onder de kop komt.
     * @return array<int, mixed>
     */
    public static function make(bool $headingRequired = true, bool $withIntro = true): array
    {
        $fields = [
            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('eyebrow')
                        ->label('Boventitel')
                        ->maxLength(120),
                    TextInput::make('heading')
                        ->label('Titel')
                        ->required($headingRequired)
                        ->maxLength(160),
                ]),
        ];

        if ($withIntro) {
            $fields[] = RichEditor::make('intro')
                ->label('Tekst')
                ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]);
        }

        return $fields;
    }
}
