<?php

namespace App\Filament\Resources\FormSubmissions;

use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Filament\Resources\FormSubmissions\Tables\FormSubmissionsTable;
use App\Models\FormSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return 'inzending';
    }

    public static function getPluralModelLabel(): string
    {
        return 'inzendingen';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inzendingen';
    }

    /** Badge met het aantal ongelezen inzendingen. */
    public static function getNavigationBadge(): ?string
    {
        $count = FormSubmission::query()->whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function table(Table $table): Table
    {
        return FormSubmissionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
        ];
    }

    // Inzendingen worden door bezoekers aangemaakt, niet in de admin.
    public static function canCreate(): bool
    {
        return false;
    }
}
