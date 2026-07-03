<?php

namespace App\Filament\Resources\CaseStudies\Pages;

use App\Filament\Resources\CaseStudies\CaseStudyResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateCaseStudy extends CreateRecord
{
    protected static string $resource = CaseStudyResource::class;

    public function getTitle(): string
    {
        return 'Case study toevoegen';
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
