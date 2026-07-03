<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Blogbericht toevoegen'),
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk blog op site')
                ->url('/blog'),
        ];
    }
}
