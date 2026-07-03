<?php

namespace App\Filament\Resources\CaseStudies\Pages;

use App\Filament\Resources\CaseStudies\CaseStudyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCaseStudy extends EditRecord
{
    protected static string $resource = CaseStudyResource::class;

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
                ->tooltip('Nieuwe case study toevoegen')
                ->url(fn (): string => CaseStudyResource::getUrl('create')),
            DeleteAction::make(),
        ];
    }
}
