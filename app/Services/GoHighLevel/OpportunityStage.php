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

    /**
     * De deal waar een offerte bij hoort. Een document in GoHighLevel hangt
     * aan het contact, nooit aan een deal, dus: heeft het contact één open
     * deal, dan is het die; heeft het er meer, dan die met exact dezelfde
     * naam als het document. Bij twijfel geen deal (reason zegt waarom),
     * zodat er nooit een factuur op de verkeerde deal komt.
     *
     * @return array{opportunity: ?array, reason: ?string}
     */
    public function findForQuote(string $contactId, ?string $documentName): array
    {
        if (! $this->isConfigured()) {
            return ['opportunity' => null, 'reason' => 'GHL_API_TOKEN ontbreekt op de server.'];
        }

        try {
            $response = $this->request()->get('/opportunities/search', [
                'location_id' => config('services.gohighlevel.location_id'),
                'contact_id' => $contactId,
                'status' => 'open',
                'limit' => 100,
            ]);
        } catch (\Throwable $e) {
            return ['opportunity' => null, 'reason' => 'GHL niet bereikbaar: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            return ['opportunity' => null, 'reason' => "GHL weigerde de opvraging van de deals ({$response->status()})."];
        }

        // De zoekopdracht filtert op contact; dubbelcheck want een fout hier kost een verkeerde factuur.
        $open = collect($response->json('opportunities'))
            ->filter(fn (array $o) => ($o['contactId'] ?? $o['contact']['id'] ?? $contactId) === $contactId
                && ($o['status'] ?? 'open') === 'open')
            ->values();

        if ($open->isEmpty()) {
            return ['opportunity' => null, 'reason' => 'Het contact heeft geen open deal.'];
        }

        if ($open->count() === 1) {
            return ['opportunity' => $open->first(), 'reason' => null];
        }

        $name = $this->normalizeName($documentName);
        $matches = $open->filter(fn (array $o) => $name !== '' && $this->normalizeName($o['name'] ?? null) === $name)->values();

        if ($matches->count() === 1) {
            return ['opportunity' => $matches->first(), 'reason' => null];
        }

        $names = $open->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');

        return ['opportunity' => null, 'reason' => $matches->isEmpty()
            ? "Het contact heeft {$open->count()} open deals ({$names}) en geen enkele heet zoals het document (\"{$documentName}\")."
            : "Meerdere open deals heten \"{$documentName}\"."];
    }

    /** Hoofdletters, dubbele spaties en soorten streepjes tellen niet mee. */
    private function normalizeName(?string $name): string
    {
        $name = str_replace(['–', '—'], '-', (string) $name);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
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
