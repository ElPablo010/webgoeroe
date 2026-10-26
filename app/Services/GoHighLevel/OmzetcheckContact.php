<?php

namespace App\Services\GoHighLevel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zet een ingevulde omzetcheck om in een contact in GoHighLevel: eerst het
 * contact met de velden (bedrag, extra aanvragen, sector) en de campagnetag,
 * daarna pas de tag die de workflow "Omzetcheck ingevuld" start. Zo kent de
 * eerste mail het bedrag al. Faalt nooit hard: de bezoeker ziet zijn
 * resultaat ook als GHL even niet bereikbaar is; de fout komt in de log.
 */
class OmzetcheckContact
{
    public function isConfigured(): bool
    {
        return filled(config('services.gohighlevel.api_token'))
            && filled(config('services.gohighlevel.location_id'));
    }

    /**
     * @param  array{first_name: string, email: string, sector: string, amount: string, extra_leads: string, attribution?: string}  $data
     */
    public function send(array $data): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Omzetcheck: GHL niet ingesteld (GHL_API_TOKEN), contact niet doorgestuurd.');

            return false;
        }

        $cfg = config('services.gohighlevel.omzetcheck');

        try {
            $upsert = $this->request()->post('/contacts/upsert', [
                'locationId' => config('services.gohighlevel.location_id'),
                'firstName' => $data['first_name'],
                'email' => $data['email'],
                'source' => 'Omzetcheck website',
                'tags' => [$cfg['campaign_tag']],
                'customFields' => [
                    ['id' => $cfg['fields']['amount'], 'field_value' => $data['amount']],
                    ['id' => $cfg['fields']['extra_leads'], 'field_value' => $data['extra_leads']],
                    ['id' => $cfg['fields']['sector'], 'field_value' => $data['sector']],
                    ...(filled(config('services.gohighlevel.attribution_field')) && filled($data['attribution'] ?? null)
                        ? [['id' => config('services.gohighlevel.attribution_field'), 'field_value' => $data['attribution']]]
                        : []),
                ],
            ]);

            $contactId = $upsert->json('contact.id');

            if (! $upsert->successful() || blank($contactId)) {
                Log::warning('Omzetcheck: contact aanmaken in GHL mislukt.', ['status' => $upsert->status(), 'body' => $upsert->body()]);

                return false;
            }

            $tag = $this->request()->post("/contacts/{$contactId}/tags", ['tags' => [$cfg['trigger_tag']]]);

            if (! $tag->successful()) {
                Log::warning('Omzetcheck: tag zetten in GHL mislukt.', ['status' => $tag->status(), 'body' => $tag->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Omzetcheck: GHL niet bereikbaar.', ['error' => $e->getMessage()]);

            return false;
        }
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
