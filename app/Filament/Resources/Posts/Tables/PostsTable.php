<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tags')
                    ->label('Tags')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Gepubliceerd')
                    ->boolean(),
                IconColumn::make('featured')
                    ->label('Uitgelicht')
                    ->boolean(),
                TextColumn::make('published_at')
                    ->label('Gepubliceerd op')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Gewijzigd')
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
                    ->url(fn (Post $record): string => $record->publicUrl()),
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
