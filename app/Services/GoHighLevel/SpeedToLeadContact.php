<?php

namespace App\Services\GoHighLevel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Zet een aanmelding op de landingspagina "Speed to lead elektriciens" om in
 * een contact in GoHighLevel, met de tag die GHL-workflow 1.3 start
 * (bevestigingsmail en melding naar Pieter). Faalt nooit hard: de inzending
 * staat al op de site en de bezoeker ziet altijd zijn bedankboodschap; een
 * GHL-fout komt in de log.
 */
class SpeedToLeadContact
{
    public function isConfigured(): bool
    {
        return filled(config('services.gohighlevel.api_token'))
            && filled(config('services.gohighlevel.location_id'));
    }

    /**
     * @param  array{name: string, company: string, email: string, phone: string}  $data
     */
    public function send(array $data): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Speed to lead: GHL niet ingesteld (GHL_API_TOKEN), contact niet doorgestuurd.');

            return false;
        }

        // GHL-mails spreken de lead aan met {{contact.first_name}}: splits de naam.
        [$firstName, $lastName] = array_pad(explode(' ', trim($data['name']), 2), 2, '');

        try {
            $response = Http::baseUrl(rtrim((string) config('services.gohighlevel.base_url'), '/'))
                ->withToken((string) config('services.gohighlevel.api_token'))
                ->withHeaders(['Version' => '2021-07-28'])
                ->acceptJson()
                ->timeout(10)
                ->post('/contacts/upsert', [
                    'locationId' => config('services.gohighlevel.location_id'),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'companyName' => $data['company'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'source' => config('services.gohighlevel.speed_to_lead.source'),
                    'tags' => [config('services.gohighlevel.speed_to_lead.tag')],
                ]);

            if (! $response->successful()) {
                Log::warning('Speed to lead: contact aanmaken in GHL mislukt.', ['status' => $response->status(), 'body' => $response->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Speed to lead: GHL niet bereikbaar.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
