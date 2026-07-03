<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public function getTitle(): string
    {
        return 'Blogbericht toevoegen';
    }

    public function getBreadcrumb(): string
    {
        return 'Toevoegen';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Opslaan');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Annuleren');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
