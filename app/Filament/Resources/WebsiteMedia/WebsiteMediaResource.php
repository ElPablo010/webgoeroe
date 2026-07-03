<?php

namespace App\Filament\Resources\WebsiteMedia;

use App\Filament\Resources\WebsiteMedia\Pages\CreateWebsiteMedia;
use App\Filament\Resources\WebsiteMedia\Pages\ListWebsiteMedia;
use App\Filament\Resources\WebsiteMedia\Schemas\WebsiteMediaForm;
use App\Filament\Resources\WebsiteMedia\Tables\WebsiteMediaTable;
use App\Models\WebsiteMedia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebsiteMediaResource extends Resource
{
    protected static ?string $model = WebsiteMedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function getModelLabel(): string
    {
        return 'afbeelding';
    }

    public static function getPluralModelLabel(): string
    {
        return 'media';
    }

    public static function getNavigationLabel(): string
    {
        return 'Media';
    }

    public static function form(Schema $schema): Schema
    {
        return WebsiteMediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebsiteMediaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteMedia::route('/'),
            'create' => CreateWebsiteMedia::route('/create'),
        ];
    }
}
