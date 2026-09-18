<?php

namespace App\Console\Commands;

use App\Services\SeoAdvisorService;
use App\Support\JobStatus;
use App\Jobs\SuggestKeywordsJob;
use Illuminate\Console\Command;

/**
 * Draait het keyword-onderzoek synchroon, buiten de queue om.
 *
 * Bestaat om de knop te kunnen uitsluiten als schuldige: blijft het scherm
 * leeg, dan zegt dit commando binnen een minuut of het onderzoek zelf faalt
 * (API-key, saldo, credentials) of de wachtrij-worker gewoon niet draait.
 * Zelfde reden als `seo:sync-analytics`: exitcode 1 + de reden op het scherm,
 * want `storage/logs/laravel.log` lees je op gedeelde hosting niet even.
 */
class SeoSuggestKeywordsCommand extends Command
{
    protected $signature = 'seo:suggest-keywords';

    protected $description = 'Doet het keyword-onderzoek meteen (AI-seeds + DataForSEO-volumes) en toont waarom het eventueel niets oplevert';

    public function handle(SeoAdvisorService $advisor): int
    {
        $status = JobStatus::for(SuggestKeywordsJob::STATUS_KEY);
        $status->running();

        $this->info('Keyword-onderzoek gestart — dit duurt enkele minuten…');

        $suggestions = $advisor->suggestKeywords();

        if ($suggestions === []) {
            $reason = $advisor->lastError() ?? 'Het onderzoek leverde geen enkele zoekterm op.';
            $status->failed($reason);

            $this->error($reason);

            return self::FAILURE;
        }

        $count = count($suggestions);
        $status->done($count . ' ' . ($count === 1 ? 'voorstel' : 'voorstellen') . ' gevonden.');

        $this->info("{$count} voorstellen klaargezet — ze staan bovenaan Groei → Keywords.");
        $this->table(
            ['Zoekwoord', 'Volume/maand'],
            collect($suggestions)->take(10)->map(fn ($s) => [
                $s['keyword'],
                $s['search_volume'] !== null ? number_format((int) $s['search_volume'], 0, ',', '.') : 'onbekend',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
