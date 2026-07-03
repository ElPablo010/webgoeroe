<?php

namespace App\Filament\Resources\FormSubmissions\Tables;

use App\Models\FormSubmission;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->getStateUsing(fn (FormSubmission $record): bool => $record->read_at !== null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEnvelopeOpen)
                    ->falseIcon(Heroicon::OutlinedEnvelope)
                    ->trueColor('gray')
                    ->falseColor('primary')
                    ->tooltip(fn (FormSubmission $record): string => $record->read_at ? 'Gelezen' : 'Nieuw'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(fn (FormSubmission $record): string => $record->typeLabel()),
                TextColumn::make('name')
                    ->label('Van')
                    ->getStateUsing(fn (FormSubmission $record): ?string => $record->data['name'] ?? null),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->getStateUsing(fn (FormSubmission $record): ?string => $record->data['email'] ?? null)
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label('Ontvangen')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                // Bekijken: opent een modal met alle ingezonden velden en markeert
                // de inzending meteen als gelezen.
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->button()
                    ->hiddenLabel()
                    ->color('primary')
                    ->tooltip('Bekijken')
                    ->modalHeading(fn (FormSubmission $record): string => 'Inzending — '.$record->typeLabel())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->modalContent(function (FormSubmission $record) {
                        if ($record->read_at === null) {
                            $record->update(['read_at' => now()]);
                        }

                        return view('filament.form-submissions.view', ['record' => $record]);
                    }),
                DeleteAction::make()
                    ->button()
                    ->hiddenLabel()
                    ->tooltip('Verwijderen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
