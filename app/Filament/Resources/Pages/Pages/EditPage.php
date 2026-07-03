<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\ManagesPageSections;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    use ManagesPageSections;

    protected static string $resource = PageResource::class;

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
                ->tooltip('Nieuwe pagina aanmaken')
                ->url(fn (): string => PageResource::getUrl('create')),
            DeleteAction::make(),
        ];
    }
}
