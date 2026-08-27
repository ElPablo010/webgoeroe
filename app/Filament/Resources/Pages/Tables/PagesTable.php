<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Tables\Columns\TitleColumn;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TitleColumn::make(PageResource::class)
                    // Er is er per definitie maar één homepage, dus een eigen
                    // kolom stond 99% leeg. Het hangt nu als icoontje aan de
                    // titel van die ene rij.
                    ->icon(fn (Page $record): ?Heroicon => $record->is_homepage ? Heroicon::Home : null)
                    ->iconPosition(IconPosition::After)
                    ->iconColor('primary')
                    ->tooltip(fn (Page $record): ?string => $record->is_homepage ? 'Dit is de homepage' : null),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('published')
                    ->label('Gepubliceerd')
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
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Bekijk op site')
                    ->icon(Heroicon::OutlinedEye)
                    ->button()
                    ->hiddenLabel()
                    ->color('primary')
                    ->tooltip('Bekijk op site')
                    ->url(fn (Page $record): string => $record->publicUrl()),
                Action::make('duplicate')
                    ->label('Dupliceren')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->button()
                    ->hiddenLabel()
                    ->color('primary')
                    ->tooltip('Dupliceren')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Page $record): string => "Pagina \"{$record->title}\" dupliceren")
                    ->modalDescription('De kopie krijgt een eigen slug, bevat alle secties en staat op niet-gepubliceerd. Je komt meteen in de kopie terecht.')
                    ->modalSubmitActionLabel('Dupliceren')
                    ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
                    ->successNotificationTitle('Pagina gedupliceerd')
                    ->action(function (Page $record, Action $action): void {
                        $copy = $record->duplicate();

                        $action->successRedirectUrl(PageResource::getUrl('edit', ['record' => $copy]));
                    }),
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
