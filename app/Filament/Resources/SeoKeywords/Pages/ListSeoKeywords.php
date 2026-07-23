<?php

namespace App\Filament\Resources\SeoKeywords\Pages;

use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use App\Models\SeoKeyword;
use App\Services\DataForSeoService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSeoKeywords extends ListRecords
{
    protected static string $resource = SeoKeywordResource::class;

    public function getSubheading(): ?string
    {
        return 'Zoektermen waarvan we wekelijks je Google-positie meten.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Keyword toevoegen')
                ->mutateDataUsing(fn (array $data) => [...$data, ...static::locale()]),

            Action::make('bulkImport')
                ->label('Meerdere importeren')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading('Keywords importeren')
                ->modalDescription('Plak één zoekwoord per regel. Keywords die al opgevolgd worden, worden overgeslagen.')
                ->modalSubmitActionLabel('Importeren')
                ->schema([
                    Textarea::make('keywords')
                        ->label('Zoekwoorden')
                        ->rows(10)
                        ->required(),
                    TextInput::make('tag')
                        ->label('Groep')
                        ->maxLength(100)
                        ->helperText('Optioneel — wordt op alle geïmporteerde keywords gezet.'),
                ])
                ->action(fn (array $data) => $this->bulkImport($data)),
        ];
    }

    /**
     * Importeer één keyword per regel.
     *
     * `firstOrCreate` op de volledige unieke sleutel (keyword + locatie + taal)
     * maakt dit veilig om twee keer te draaien: bestaande regels worden niet
     * aangeraakt, ook hun groep en actief-status niet.
     *
     * @param  array<string, mixed>  $data
     */
    protected function bulkImport(array $data): void
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', (string) $data['keywords']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique();

        $added = 0;

        foreach ($lines as $line) {
            $keyword = SeoKeyword::firstOrCreate(
                ['keyword' => $line, ...static::locale()],
                ['tag' => $data['tag'] ?: null, 'is_active' => true],
            );

            if ($keyword->wasRecentlyCreated) {
                $added++;
            }
        }

        $skipped = $lines->count() - $added;

        Notification::make()
            ->title("{$added} nieuwe keywords geïmporteerd")
            ->body($skipped > 0 ? "{$skipped} stonden er al in en zijn overgeslagen." : null)
            ->success()
            ->send();
    }

    /**
     * Locatie en taal waarvoor we meten — ingesteld bij SEO → Instellingen.
     * Ze horen bij de unieke sleutel van een keyword, dus elke insert zet ze mee.
     *
     * @return array{location_code: int, language_code: string}
     */
    protected static function locale(): array
    {
        $api = app(DataForSeoService::class);

        return [
            'location_code' => $api->locationCode,
            'language_code' => $api->languageCode,
        ];
    }
}
