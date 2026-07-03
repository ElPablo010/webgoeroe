<?php

namespace App\Filament\Resources\CaseStudies\Tables;

use App\Models\CaseStudy;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CaseStudiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client')
                    ->label('Klant')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('industry')
                    ->label('Sector')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Gepubliceerd')
                    ->boolean(),
                IconColumn::make('featured')
                    ->label('Uitgelicht')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Gewijzigd')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('published')
                    ->label('Gepubliceerd'),
                TernaryFilter::make('featured')
                    ->label('Uitgelicht'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Bekijk op site')
                    ->icon(Heroicon::OutlinedEye)
                    ->button()
                    ->hiddenLabel()
                    ->color('primary')
                    ->tooltip('Bekijk op site')
                    ->url(fn (CaseStudy $record): string => $record->publicUrl()),
                EditAction::make()
                    ->button()
                    ->hiddenLabel()
                    ->color('primary')
                    ->tooltip('Bewerken'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
