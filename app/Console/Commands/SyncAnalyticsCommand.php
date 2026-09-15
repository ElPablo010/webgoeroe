<?php

namespace App\Console\Commands;

use App\Services\Ga4Collector;
use Illuminate\Console\Command;

class SyncAnalyticsCommand extends Command
{
    protected $signature = 'seo:sync-analytics';

    protected $description = 'Haalt de Google Analytics-cijfers op (sessies, bezoekers en weergaven per dag, per pagina en per kanaal)';

    public function handle(Ga4Collector $collector): int
    {
        if (! $collector->isConfigured()) {
            $this->warn('Analytics is niet gekoppeld — overgeslagen.');

            return self::SUCCESS;
        }

        $this->info('Analytics-data ophalen…');
        $result = $collector->sync();

        if ($result['error'] !== null) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        if ($result['backfilled']) {
            $this->info('Eerste run: de volledige beschikbare historiek ingelezen.');
        }

        $this->info("{$result['days']} dagen, {$result['pages']} pagina's, {$result['channels']} kanalen bijgewerkt.");

        return self::SUCCESS;
    }
}
