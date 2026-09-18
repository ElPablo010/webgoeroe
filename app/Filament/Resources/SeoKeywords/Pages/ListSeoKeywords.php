<?php

namespace App\Filament\Resources\SeoKeywords\Pages;

use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use App\Filament\Widgets\SeoKeywordSuggestions;
use App\Jobs\SuggestKeywordsJob;
use App\Models\Setting;
use App\Models\SeoKeyword;
use App\Services\DataForSeoService;
use App\Support\JobStatus;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;

class ListSeoKeywords extends ListRecords
{
    protected static string $resource = SeoKeywordResource::class;

    public function getSubheading(): ?string
    {
        return 'Zoektermen waarvan we wekelijks je Google-positie meten.';
    }

    /**
     * Het voorstellenblok (checkboxes) boven de tabel — zichtbaar zodra er
     * voorstellen bewaard zijn óf er een onderzoek loopt of misliep (zie
     * SeoKeywordSuggestions::canView).
     *
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [SeoKeywordSuggestions::class];
    }

    /** Het widget voegde keywords toe: opnieuw renderen ververst de tabel. */
    #[On('seo-keywords-added')]
    public function refreshAfterSuggestions(): void
    {
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('suggest')
                ->label('Stel keywords voor')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Keyword-onderzoek starten')
                ->modalDescription('De AI bedenkt zoektermen in de taal van je klanten en DataForSEO hangt er zoekvolumes aan. Dat duurt enkele minuten; de voorstellen verschijnen daarna bovenaan dit scherm. Niets wordt automatisch opgevolgd.')
                ->modalSubmitActionLabel('Starten')
                ->disabled(fn () => blank(Setting::get('anthropic_api_key') ?: config('services.anthropic.api_key')))
                ->action(fn () => $this->suggestKeywords()),

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
     * Zet het keyword-onderzoek op de queue. AI-call + twee DataForSEO-calls
     * zijn te traag voor een web-request op shared hosting. Rate-limited: één
     * run per tien minuten volstaat, elke run kost API-credits.
     *
     * De stand gaat mee in een JobStatus, zodat het blok hierboven kan tonen
     * dát het loopt — ook als je het scherm intussen verlaat.
     */
    protected function suggestKeywords(): void
    {
        $status = JobStatus::for(SuggestKeywordsJob::STATUS_KEY);

        if ($status->isBusy()) {
            Notification::make()
                ->title('Er loopt al een keyword-onderzoek')
                ->body('De stand staat bovenaan dit scherm en ververst vanzelf.')
                ->warning()
                ->send();

            return;
        }

        // Tweede slot: ook als de vorige run al klaar is, kost elke nieuwe run
        // API-credits. Eén per tien minuten volstaat ruimschoots.
        if (RateLimiter::tooManyAttempts(SuggestKeywordsJob::RATE_LIMIT_KEY, 1)) {
            $minutes = (int) ceil(RateLimiter::availableIn(SuggestKeywordsJob::RATE_LIMIT_KEY) / 60);

            Notification::make()
                ->title('Net al een onderzoek gedaan')
                ->body("Elke run kost API-credits. Probeer over {$minutes} minuten opnieuw.")
                ->warning()
                ->send();

            return;
        }
        RateLimiter::hit(SuggestKeywordsJob::RATE_LIMIT_KEY, 600);

        $status->queued();

        SuggestKeywordsJob::dispatch();

        Notification::make()
            ->title('Keyword-onderzoek gestart')
            ->body('De stand verschijnt bovenaan dit scherm en ververst vanzelf — je mag gerust weggaan en later terugkomen.')
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
