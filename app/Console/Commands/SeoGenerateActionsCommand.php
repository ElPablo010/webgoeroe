<?php

namespace App\Console\Commands;

use App\Services\SeoAdvisorService;
use Illuminate\Console\Command;

/**
 * Genereert verbeteracties uit de reeds verzamelde SEO-data (zonder DataForSEO
 * opnieuw te bevragen). Handig om het goedkeuringsdashboard te vullen of te
 * testen tussen de wekelijkse runs door.
 */
class SeoGenerateActionsCommand extends Command
{
    protected $signature = 'seo:generate-actions';

    protected $description = 'Genereert SEO-verbeteracties uit de bestaande data (één AI-call, geen DataForSEO-kost)';

    public function handle(SeoAdvisorService $advisor): int
    {
        $context = $advisor->buildContext();
        $actions = $advisor->generateActions($context);

        if (! $actions) {
            $this->warn('Geen acties gegenereerd (Anthropic-key ontbreekt, of geen bruikbare voorstellen).');

            return self::SUCCESS;
        }

        $stored = $advisor->storeActions($actions);

        $this->info("{$stored['created']} nieuwe verbeteracties aangemaakt ({$stored['proposed']} voorgesteld).");
        if ($stored['duplicates'] > 0) {
            $this->warn("{$stored['duplicates']} voorstellen overgeslagen: die stonden er al.");
        }

        return self::SUCCESS;
    }
}
