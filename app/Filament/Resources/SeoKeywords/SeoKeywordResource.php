<?php

namespace App\Filament\Resources\SeoKeywords;

use App\Filament\Resources\SeoKeywords\Pages\ListSeoKeywords;
use App\Filament\Resources\SeoKeywords\Schemas\SeoKeywordForm;
use App\Filament\Resources\SeoKeywords\Tables\SeoKeywordsTable;
use App\Models\SeoKeyword;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * De lijst zoekwoorden waarvan we de Google-positie opvolgen.
 *
 * Aanmaken en bewerken gebeurt in een modal op de lijstpagina — een keyword is
 * drie velden, daar hoeft geen aparte pagina voor open te gaan.
 */
class SeoKeywordResource extends Resource
{
    protected static ?string $model = SeoKeyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'keyword';

    public static function getModelLabel(): string
    {
        return 'keyword';
    }

    public static function getPluralModelLabel(): string
    {
        return 'keywords';
    }

    public static function getNavigationLabel(): string
    {
        return 'Keywords';
    }

    public static function form(Schema $schema): Schema
    {
        return SeoKeywordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoKeywordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoKeywords::route('/'),
        ];
    }
}
