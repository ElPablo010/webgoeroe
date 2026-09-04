<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Models\SeoKeyword;
use App\Services\DataForSeoService;
use App\Services\SeoAdvisorService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * Voorgestelde keywords (uit "Stel keywords voor" — SuggestKeywordsJob) met
 * checkboxes. Aangevinkte keywords gaan de opvolging in en verdwijnen uit de
 * voorstellen. Nooit automatisch toevoegen: elke opgevolgde keyword kost
 * wekelijks een SERP-meting. Enkel bovenaan het Keywords-scherm.
 */
class SeoKeywordSuggestions extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.seo-keyword-suggestions';

    protected int|string|array $columnSpan = 'full';

    /** @var array<int, string> */
    public array $selected = [];

    public static function canView(): bool
    {
        return static::stored()['items'] !== [];
    }

    /**
     * @return array{generated_at: ?string, items: array<int, array{keyword:string, search_volume:?int}>}
     */
    protected static function stored(): array
    {
        $data = json_decode((string) Setting::get(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING), true);

        return [
            'generated_at' => is_array($data) ? ($data['generated_at'] ?? null) : null,
            'items' => is_array($data) && is_array($data['items'] ?? null) ? array_values($data['items']) : [],
        ];
    }

    /**
     * Voorstellen die nog niet opgevolgd worden (case-insensitive) — een
     * keyword dat intussen handmatig toegevoegd is, hoort hier niet meer.
     *
     * @return array<int, array{keyword:string, search_volume:?int}>
     */
    public function suggestions(): array
    {
        $tracked = SeoKeyword::pluck('keyword')
            ->map(fn ($kw) => mb_strtolower(trim($kw)))
            ->flip();

        return collect(static::stored()['items'])
            ->filter(fn ($item) => is_array($item) && filled($item['keyword'] ?? null))
            ->reject(fn ($item) => isset($tracked[mb_strtolower(trim($item['keyword']))]))
            ->values()
            ->all();
    }

    public function generatedAt(): ?string
    {
        $at = static::stored()['generated_at'];

        return $at ? Carbon::parse($at)->format('d/m/Y') : null;
    }

    public function addSelected(): void
    {
        $chosen = collect($this->selected)->map(fn ($kw) => trim((string) $kw))->filter()->unique();

        if ($chosen->isEmpty()) {
            Notification::make()->title('Vink eerst één of meer keywords aan')->warning()->send();

            return;
        }

        $api = app(DataForSeoService::class);
        $added = 0;

        foreach ($chosen as $keyword) {
            $record = SeoKeyword::firstOrCreate(
                ['keyword' => $keyword, 'location_code' => $api->locationCode, 'language_code' => $api->languageCode],
                ['is_active' => true],
            );
            if ($record->wasRecentlyCreated) {
                $added++;
            }
        }

        $this->forget($chosen->all());
        $this->selected = [];

        Notification::make()
            ->title("{$added} " . ($added === 1 ? 'keyword' : 'keywords') . ' toegevoegd aan de opvolging')
            ->body('De eerste positie verschijnt na de volgende verversing.')
            ->success()
            ->send();

        $this->dispatch('seo-keywords-added');
    }

    public function discardAll(): void
    {
        Setting::set(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING, null);
        $this->selected = [];

        Notification::make()->title('Voorstellen gewist')->success()->send();
    }

    /** @param  array<int, string>  $keywords */
    protected function forget(array $keywords): void
    {
        $lower = array_map(fn ($kw) => mb_strtolower($kw), $keywords);
        $stored = static::stored();
        $stored['items'] = collect($stored['items'])
            ->reject(fn ($item) => in_array(mb_strtolower(trim((string) ($item['keyword'] ?? ''))), $lower, true))
            ->values()
            ->all();

        Setting::set(SeoAdvisorService::KEYWORD_SUGGESTIONS_SETTING, json_encode($stored));
    }
}
