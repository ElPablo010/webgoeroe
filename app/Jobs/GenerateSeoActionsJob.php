<?php

namespace App\Jobs;

use App\Services\Seo\ActionBacklog;
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

    /**
     * @param  bool  $force  Genereer ook als de lijst vol staat — de beheerder
     *                       klikte de waarschuwing bewust weg. De grens op het
     *                       aantal nieuwe acties blijft wél gelden, anders
     *                       levert één klik alsnog een overvolle lijst op.
     */
    public function __construct(public bool $force = false) {}

    public function handle(SeoAdvisorService $advisor, ActionBacklog $backlog): void
    {
        $status = JobStatus::for(self::STATUS_KEY);
        $status->running();

        // Eerst opruimen: achterhaalde voorstellen maken ruimte vrij, dus dit
        // moet vóór de ruimte-berekening gebeuren.
        $expired = $backlog->expireStale();

        if (! $this->force && $backlog->isBlocked()) {
            // Nog vóór de AI-call, zodat een geblokkeerde run niets kost.
            $status->failed($this->fullMessage($backlog));
            RateLimiter::clear(self::RATE_LIMIT_KEY);

            return;
        }

        $room = $this->force ? $backlog->limit() : $backlog->room();

        $actions = $advisor->generateActions($advisor->buildContext());
        $stored = $advisor->storeActions($actions, null, $room);

        Log::info('SEO-acties gegenereerd via de knop', $stored + ['expired' => $expired, 'room' => $room]);

        if (($stored['created'] ?? 0) > 0) {
            $status->done($stored['created'].' nieuwe acties.');

            return;
        }

        // Geen nieuwe acties is geen storing, maar wel iets om te melden —
        // anders lijkt een leeg scherm op een mislukte run. runNotice() legt
        // het geval "alles stond er al" verder uit.
        $status->failed(($stored['proposed'] ?? 0) > 0
            ? 'De analyse stelde '.$stored['proposed'].' acties voor, maar die stonden hier al eerder.'
            : ($advisor->lastError() ?? 'De analyse leverde geen enkel voorstel op.'));

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }

    public function failed(?Throwable $e): void
    {
        JobStatus::for(self::STATUS_KEY)
            ->failed('De analyse liep vast: '.($e?->getMessage() ?: 'onbekende fout').'.');

        RateLimiter::clear(self::RATE_LIMIT_KEY);
    }

    protected function fullMessage(ActionBacklog $backlog): string
    {
        $open = $backlog->openCount();

        return "Er staan nog {$open} acties open. Handel ze eerst af — goedkeuren of "
            ."negeren — dan staan er {$backlog->limit()} nieuwe klaar.";
    }
}
