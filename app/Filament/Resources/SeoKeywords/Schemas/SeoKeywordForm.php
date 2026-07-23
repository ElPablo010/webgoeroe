<?php

namespace App\Filament\Resources\SeoKeywords\Schemas;

use App\Models\SeoKeyword;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeoKeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')
                ->label('Zoekwoord')
                ->required()
                ->maxLength(255)
                ->helperText('De zoekterm zoals een bezoeker die in Google zou typen.'),

            Select::make('tag')
                ->label('Groep')
                ->options(fn () => static::tagOptions())
                ->searchable()
                ->nullable()
                // Nieuwe groepen ontstaan gaandeweg; ze komen uit bestaande
                // keywords, dus laat de gebruiker er hier meteen één toevoegen.
                ->createOptionForm([
                    TextInput::make('tag')->label('Nieuwe groep')->required()->maxLength(100),
                ])
                ->createOptionUsing(fn (array $data) => $data['tag'])
                ->helperText('Optioneel — handig om keywords per thema of dienst te bundelen.'),

            Toggle::make('is_active')
                ->label('Actief opvolgen')
                ->default(true)
                ->helperText('Zet uit om dit keyword te bewaren zonder er credits aan te besteden.'),
        ]);
    }

    /**
     * Bestaande groepen, alfabetisch — voorspelbaar zoeken voor de gebruiker.
     *
     * @return array<string, string>
     */
    protected static function tagOptions(): array
    {
        return SeoKeyword::query()
            ->whereNotNull('tag')
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag', 'tag')
            ->all();
    }
}
