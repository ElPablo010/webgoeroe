<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\ManagesPageSections;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use ManagesPageSections;

    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return 'Pagina toevoegen';
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
