<?php

namespace App\Jobs;

use App\Services\SeoCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Haalt de SERP-posities op zodra hun DataForSEO-task klaar is, en herplant
 * zichzelf voor wat nog niet klaar is. Zo levert één "Ververs nu" uiteindelijk
 * álle posities op, zonder dat de gebruiker moet herklikken.
 */
class FetchSerpResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    /**
     * @param  array<string,string>  $map      keyword => task_id (nog op te halen)
     * @param  array<string,int|null>  $volumes keyword => search_volume
     */
    public function __construct(
        public array $map,
        public array $volumes = [],
        public int $attempt = 1,
    ) {
    }

    /** Max aantal pogingen. Bij een cron van 1×/min ≈ 30 min venster. */
    public const MAX_ATTEMPTS = 30;

    public function handle(SeoCollector $collector): void
    {
        $result = $collector->fetchReadyResults($this->map, $this->volumes);
        $remaining = $result['remaining'];

        Log::info('SERP-resultaten opgehaald', [
            'attempt' => $this->attempt,
            'saved' => $result['saved'],
            'remaining' => count($remaining),
        ]);

        if (empty($remaining)) {
            return;
        }

        // Sommige SERP-tasks blijven lang "in queue" bij DataForSEO. Een task_get
        // op een nog-niet-klare task is gratis, dus we blijven gerust doorpollen.
        if ($this->attempt < self::MAX_ATTEMPTS) {
            self::dispatch($remaining, $this->volumes, $this->attempt + 1)
                ->delay(now()->addSeconds(30));
        } else {
            Log::warning('SERP-tasks niet afgerond na max pogingen', [
                'keywords' => array_keys($remaining),
            ]);
        }
    }
}
