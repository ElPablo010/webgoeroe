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
 * Genereert de verbeteracties buiten de web-request. De AI-call schrijft
 * volledige landingspagina's uit en duurt daardoor ruim een minuut — te lang
 * voor een synchrone request op shared hosting, waar de knop dan in een
 * time-out loopt zonder dat er iets wordt opgeslagen.
 *
 * Houdt z'n stand bij in een JobStatus, om dezelfde reden als
 * SuggestKeywordsJob: draait de queue-worker niet, dan blijft de job zonder
 * één foutmelding staan en kijk je naar een leeg scherm dat er identiek
 * uitziet als "er valt niets te beoordelen".
 */
class GenerateSeoActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sleutel van de stand in `settings`, gedeeld met het Acties-scherm. */
    public const STATUS_KEY = 'seo_actions_status';

    /** Rate-limiter die dubbelklikken (en dus dubbele AI-kosten) tegenhoudt. */
    public const RATE_LIMIT_KEY = 'seo-generate-actions';

    public $tries = 1;

    public $timeout = 600;

    public function handle(SeoAdvisorService $advisor): void
    {
        $status = JobStatus::for(self::STATUS_KEY);
        $status->running();

        $actions = $advisor->generateActions($advisor->buildContext());
        $stored = $advisor->storeActions($actions);

        Log::info('SEO-acties gegenereerd via de knop', $stored);

        if (($stored['created'] ?? 0) > 0) {
            $status->done($stored['created'] . ' nieuwe acties.');

            return;
        }

        // Geen nieuwe acties is geen storing, maar wel iets om te melden —
        // anders lijkt een leeg scherm op een mislukte run. runNotice() legt
        // het geval "alles stond er al" verder uit.
        $status->failed(($stored['proposed'] ?? 0) > 0
            ? 'De analyse stelde ' . $stored['proposed'] . ' acties voor, maar die stonden hier al eerder.'
            : ($advisor->lastError() ?? 'De analyse leverde geen enkel voorstel op.'));

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }

    public function failed(?Throwable $e): void
    {
        JobStatus::for(self::STATUS_KEY)
            ->failed('De analyse liep vast: ' . ($e?->getMessage() ?: 'onbekende fout') . '.');

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }
}
