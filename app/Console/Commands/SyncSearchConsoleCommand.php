<?php

namespace App\Console\Commands;

use App\Services\GscCollector;
use Illuminate\Console\Command;

class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'seo:sync-search-console';

    protected $description = 'Haalt de Google Search Console-cijfers op (clicks, vertoningen, posities per zoekterm en pagina)';

    public function handle(GscCollector $collector): int
    {
        if (!$collector->isConfigured()) {
            $this->warn('Search Console is niet gekoppeld — overgeslagen.');

            return self::SUCCESS;
        }

        $this->info('Search Console-data ophalen…');
        $result = $collector->sync();

        if ($result['backfilled']) {
            $this->info('Eerste run: 16 maanden historiek ingelezen.');
        }

        $this->info("{$result['days']} dagen, {$result['queries']} zoektermen, {$result['pages']} pagina's bijgewerkt.");

        return self::SUCCESS;
    }
}
