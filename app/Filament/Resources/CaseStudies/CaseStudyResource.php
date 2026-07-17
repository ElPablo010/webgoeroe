<?php

namespace App\Filament\Resources\CaseStudies;

use App\Filament\Resources\CaseStudies\Pages\CreateCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\ListCaseStudies;
use App\Filament\Resources\CaseStudies\Schemas\CaseStudyForm;
use App\Filament\Resources\CaseStudies\Tables\CaseStudiesTable;
use App\Models\CaseStudy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CaseStudyResource extends Resource
{
    protected static ?string $model = CaseStudy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Admin-URL wordt /admin/cases (i.p.v. de van de klassenaam afgeleide
     * /admin/case-studies). Model en tabel (case_studies) blijven bewust
     * ongewijzigd — enkel de weergave en de URL heten "cases".
     */
    protected static ?string $slug = 'cases';

    public static function getModelLabel(): string
    {
        return 'case';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cases';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cases';
    }

    public static function form(Schema $schema): Schema
    {
        return CaseStudyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CaseStudiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseStudies::route('/'),
            'create' => CreateCaseStudy::route('/create'),
            'edit' => EditCaseStudy::route('/{record}/edit'),
        ];
    }
}
