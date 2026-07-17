<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Vul de publicatiedatum aan met nu wanneer het bericht gepubliceerd wordt
     * en er nog geen datum is ingevuld — zoals de veld-helptekst belooft.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['published'] ?? false) && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

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
