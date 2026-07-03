<?php

namespace App\Filament\Schemas\Sections;

use App\Models\CaseStudy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;

/**
 * Cases grid — insluitable case studies grid voor pagina's en andere secties.
 *
 * Laadt gepubliceerde case studies live op basis van optionele filters.
 */
class CasesGridFields
{
    public static function make(): array
    {
        return [
            ...HeadingFields::make(headingRequired: false),

            TextInput::make('limit')
                ->label('Aantal te tonen')
                ->numeric()
                ->minValue(1)
                ->placeholder('6')
                ->helperText('Laat leeg voor alle gepubliceerde case studies.'),

            Select::make('filter_industry')
                ->label('Filter op sector (optioneel)')
                ->options(fn (): array => CaseStudy::query()
                    ->whereNotNull('industry')
                    ->orderBy('industry')
                    ->distinct()
                    ->pluck('industry', 'industry')
                    ->all()
                )
                ->placeholder('Alle sectoren')
                ->nullable(),

            TagsInput::make('filter_tags')
                ->label('Filter op tags (optioneel)')
                ->placeholder('Voeg tag toe'),

            CtaLinkSchema::repeater('cta', 'Link naar overzicht (optioneel)'),
        ];
    }
}
