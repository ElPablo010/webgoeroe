<?php

namespace App\Filament\Resources\WebsiteMedia\Pages;

use App\Filament\Resources\WebsiteMedia\WebsiteMediaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListWebsiteMedia extends ListRecords
{
    protected static string $resource = WebsiteMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Media toevoegen'),
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk op site')
                ->url('/'),
        ];
    }
}
