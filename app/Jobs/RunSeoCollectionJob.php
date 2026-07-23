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
 * Draait een volledige SEO-verzamelcyclus (overzicht + keyword-posities)
 * buiten de web-request, zodat de "ververs nu"-knop niet blokkeert.
 */
class RunSeoCollectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 600;

    public function handle(SeoCollector $collector): void
    {
        if (!$collector->isConfigured()) {
            Log::warning('RunSeoCollectionJob: DataForSEO niet geconfigureerd, overgeslagen.');
            return;
        }

        // Snapshot + volume + keyword-tasks posten. De posities zelf komen
        // asynchroon binnen via FetchSerpResultsJob, die zichzelf herplant
        // tot alles opgehaald is — geen synchroon 180s-wachtvenster meer.
        $context = $collector->startCollection();

        if (!empty($context['map'])) {
            FetchSerpResultsJob::dispatch($context['map'], $context['volumes'])
                ->delay(now()->addSeconds(45));
        }

        Log::info('SEO-verzameling gestart', ['keywords_posted' => count($context['map'])]);
    }
}
