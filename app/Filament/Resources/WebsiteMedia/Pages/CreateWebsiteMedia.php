<?php

namespace App\Filament\Resources\WebsiteMedia\Pages;

use App\Filament\Resources\WebsiteMedia\WebsiteMediaResource;
use App\Services\Website\WebsiteMediaService;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateWebsiteMedia extends CreateRecord
{
    protected static string $resource = WebsiteMediaResource::class;

    public function getTitle(): string
    {
        return 'Afbeelding toevoegen';
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

    protected function handleRecordCreation(array $data): Model
    {
        $stored = $data['upload'];
        $path = is_array($stored) ? array_values($stored)[0] : $stored;

        $disk = Storage::disk('local');
        $absolute = $disk->path($path);

        $upload = new UploadedFile(
            $absolute,
            basename($path),
            mime_content_type($absolute) ?: null,
            test: true,
        );

        $media = app(WebsiteMediaService::class)->store($upload);

        $disk->delete($path);

        return $media;
    }
}
