<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Pagina toevoegen'),
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk op site')
                ->url('/'),
        ];
    }
}
