<?php

namespace App\Filament\Resources\WebsiteMedia\Tables;

use App\Models\WebsiteMedia;
use App\Services\Website\WebsiteMediaService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebsiteMediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Grid van thumbnail-kaarten i.p.v. een rijen-tabel: zelfde visuele
            // opzet als de "Kies uit media"-picker (2 kolommen op mobiel → 4 op
            // desktop). De Filament-tabel-chrome (zoekbalk, paginatie, bulk-acties)
            // blijft gewoon bovenaan staan.
            ->contentGrid(['default' => 2, 'sm' => 3, 'md' => 4])
            ->columns([
                Stack::make([
                    ImageColumn::make('thumbnail')
                        ->getStateUsing(fn (WebsiteMedia $record): string => $record->url)
                        ->imageHeight('10rem')
                        ->imageWidth('100%')
                        // Inline style i.p.v. Tailwind-classes: Filament laadt de
                        // app-Tailwind niet, dus object-cover/rounded zouden niet
                        // toegepast worden en de thumbnail uitrekken.
                        ->extraImgAttributes(['style' => 'object-fit: cover; border-radius: 0.5rem;']),
                    TextColumn::make('original_filename')
                        ->label('Bestand')
                        ->searchable()
                        ->size(TextSize::ExtraSmall)
                        ->color('gray')
                        // Klik op de bestandsnaam kopieert de WebP-URL — vervangt
                        // de losse URL-kolom uit de oude tabelweergave.
                        ->copyable()
                        ->copyableState(fn (WebsiteMedia $record): string => $record->url)
                        ->copyMessage('URL gekopieerd')
                        ->tooltip('Klik om de URL te kopiëren'),
                    TextColumn::make('dimensions')
                        ->getStateUsing(fn (WebsiteMedia $record): string => $record->width.'×'.$record->height.' px · '.number_format($record->size_bytes / 1024, 0).' kB')
                        ->size(TextSize::ExtraSmall)
                        ->color('gray'),
                ])->space(1),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->button()
                    ->hiddenLabel()
                    ->tooltip('Verwijderen')
                    ->using(fn (WebsiteMedia $record) => app(WebsiteMediaService::class)->delete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
