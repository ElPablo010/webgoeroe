<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Vul de publicatiedatum aan met nu wanneer het bericht gepubliceerd wordt
     * en er nog geen datum is ingevuld — zoals de veld-helptekst belooft.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['published'] ?? false) && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('primary')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
            Action::make('view')
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->tooltip('Bekijk op site')
                ->url(fn (): string => $this->getRecord()->publicUrl()),
            Action::make('create')
                ->icon(Heroicon::OutlinedPlus)
                ->hiddenLabel()
                ->tooltip('Nieuw blogbericht toevoegen')
                ->url(fn (): string => PostResource::getUrl('create')),
            DeleteAction::make(),
        ];
    }
}
