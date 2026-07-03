<?php

namespace App\Filament\Concerns;

trait ManagesPageSections
{
    protected ?array $pendingSections = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sections'] = $this->record?->sections
            ->map(fn ($section) => [
                'type' => $section->section_type,
                'data' => $section->content ?? [],
            ])
            ->values()
            ->all() ?? [];

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractSections($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractSections($data);
    }

    protected function extractSections(array $data): array
    {
        $this->pendingSections = $data['sections'] ?? [];
        unset($data['sections']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncSections();
    }

    protected function afterSave(): void
    {
        $this->syncSections();
    }

    protected function syncSections(): void
    {
        if ($this->pendingSections === null) {
            return;
        }

        $this->record->sections()->delete();

        foreach ($this->pendingSections as $index => $entry) {
            $this->record->sections()->create([
                'section_type' => $entry['type'],
                'position' => $index,
                'content' => $entry['data'] ?? [],
            ]);
        }
    }
}
