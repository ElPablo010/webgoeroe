<?php

namespace App\Jobs;

use App\Services\SeoAdvisorService;
use App\Support\JobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Draait het keyword-onderzoek buiten de web-request: een AI-call plus twee
 * DataForSEO-calls samen duren te lang voor een synchrone request op shared
 * hosting (zelfde reden als GenerateSeoActionsJob).
 *
 * Elke afloop — gelukt, niets gevonden of vastgelopen — schrijft z'n stand
 * weg in een JobStatus. Zonder dat ziet het Keywords-scherm geen verschil
 * tussen "loopt nog" en "is stilletjes op niets uitgedraaid", en blijf je
 * naar een leeg scherm kijken.
 */
class SuggestKeywordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sleutel van de stand in `settings`, gedeeld met het Keywords-scherm. */
    public const STATUS_KEY = 'seo_keyword_suggestions_status';

    /** Rate-limiter die dubbelklikken (en dus dubbele API-kosten) tegenhoudt. */
    public const RATE_LIMIT_KEY = 'seo-suggest-keywords';

    public $tries = 1;

    public $timeout = 300;

    public function handle(SeoAdvisorService $advisor): void
    {
        $status = JobStatus::for(self::STATUS_KEY);
        $status->running();

        $suggestions = $advisor->suggestKeywords();

        if ($suggestions === []) {
            $reason = $advisor->lastError() ?? 'Het onderzoek leverde geen enkele zoekterm op.';
            Log::warning('Keyword-onderzoek zonder resultaat', ['reason' => $reason]);

            $status->failed($reason);
            // Niets gevonden is bijna altijd een instelfout: laat opnieuw
            // proberen toe zodra die rechtgezet is, zonder tien minuten wachten.
            RateLimiter::clear(self::RATE_LIMIT_KEY);

            return;
        }

        $count = count($suggestions);
        Log::info('Keyword-onderzoek afgerond', ['candidates' => $count]);

        $status->done($count . ' ' . ($count === 1 ? 'voorstel' : 'voorstellen') . ' gevonden.');
    }

    public function failed(?Throwable $e): void
    {
        JobStatus::for(self::STATUS_KEY)
            ->failed('Het onderzoek liep vast: ' . ($e?->getMessage() ?: 'onbekende fout') . '.');

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }
}
