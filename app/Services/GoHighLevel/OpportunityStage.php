<?php

namespace App\Services\GoHighLevel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zet een deal in GoHighLevel op een stap, in welke pijplijn hij ook staat.
 * De stap wordt op naam gezocht in de pijplijn van de deal ("proposal sent",
 * "deposit"), zodat een nieuwe pijplijn zonder aanpassing meewerkt zolang ze
 * zo'n stap heeft. Een deal gaat nooit terug naar een vroegere stap.
 * Faalt nooit hard: een fout komt als error in de log (LOG_LEVEL=error op Combell).
 */
class OpportunityStage
{
    public const PROPOSAL_SENT = 'proposal sent';

    public const DEPOSIT = 'deposit';

    public function isConfigured(): bool
    {
        return filled(config('services.gohighlevel.api_token'))
            && filled(config('services.gohighlevel.location_id'));
    }

    /** @return string|null de naam van de stap waar de deal nu staat, null als er niets veranderde */
    public function moveTo(string $opportunityId, string $stageMatch): ?string
    {
        if (! $this->isConfigured()) {
            Log::error('GHL-deal verplaatsen: GHL_API_TOKEN ontbreekt.');

            return null;
        }

        try {
            $opportunity = $this->request()->get("/opportunities/{$opportunityId}");

            if (! $opportunity->successful()) {
                return $this->fail('deal ophalen', $opportunityId, $opportunity->status(), $opportunity->body());
            }

            $pipelineId = $opportunity->json('opportunity.pipelineId');
            $currentStageId = $opportunity->json('opportunity.pipelineStageId');

            $pipelines = $this->request()->get('/opportunities/pipelines', [
                'locationId' => config('services.gohighlevel.location_id'),
            ]);

            if (! $pipelines->successful()) {
                return $this->fail('pijplijnen ophalen', $opportunityId, $pipelines->status(), $pipelines->body());
            }

            $stages = collect($pipelines->json('pipelines'))->firstWhere('id', $pipelineId)['stages'] ?? [];
            $stages = array_values($stages);

            $target = collect($stages)->search(fn (array $stage) => str_contains(mb_strtolower($stage['name'] ?? ''), $stageMatch));
            $current = collect($stages)->search(fn (array $stage) => ($stage['id'] ?? null) === $currentStageId);

            if ($target === false) {
                return $this->fail("geen stap met \"{$stageMatch}\" in de pijplijn", $opportunityId);
            }

            if ($current !== false && $current >= $target) {
                return null;
            }

            $update = $this->request()->put("/opportunities/{$opportunityId}", [
                'pipelineId' => $pipelineId,
                'pipelineStageId' => $stages[$target]['id'],
            ]);

            if (! $update->successful()) {
                return $this->fail('deal bijwerken', $opportunityId, $update->status(), $update->body());
            }

            return $stages[$target]['name'];
        } catch (\Throwable $e) {
            return $this->fail('GHL niet bereikbaar', $opportunityId, null, $e->getMessage());
        }
    }

    private function fail(string $step, string $opportunityId, ?int $status = null, ?string $body = null): null
    {
        Log::error("GHL-deal verplaatsen mislukt: {$step}.", array_filter([
            'opportunity_id' => $opportunityId,
            'status' => $status,
            'body' => $body ? mb_substr($body, 0, 500) : null,
        ]));

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.gohighlevel.base_url'), '/'))
            ->withToken((string) config('services.gohighlevel.api_token'))
            ->withHeaders(['Version' => '2021-07-28'])
            ->acceptJson()
            ->timeout(10);
    }
}
