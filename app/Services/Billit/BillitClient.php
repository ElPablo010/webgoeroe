<?php

namespace App\Services\Billit;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Dunne laag op de Billit-API (https://docs.billit.be). Faalt niet hard: een
 * mislukte call geeft null/false terug, en lastError() zegt waarom — met
 * Billits eigen foutomschrijving erin.
 */
class BillitClient
{
    private ?string $lastError = null;

    public function isConfigured(): bool
    {
        return filled(config('services.billit.api_key')) && filled(config('services.billit.party_id'));
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Maakt een factuur aan (nog niet verstuurd). Billit zoekt de klant op
     * btw-nummer/e-mail of maakt hem aan, en kent zelf het factuurnummer toe.
     */
    public function createOrder(array $order): ?int
    {
        $response = $this->request()->post('/v1/orders', $order);

        if (! $response->successful()) {
            $this->lastError = 'Factuur aanmaken mislukt: '.$this->describe($response);

            return null;
        }

        return (int) $response->body();
    }

    /**
     * Verstuurt zonder TransportType: Billit kiest Peppol als de klant daar
     * op staat, anders e-mail (zie docs "Send via Email").
     */
    public function sendOrder(int $orderId): bool
    {
        $response = $this->request()->post('/v1/orders/commands/send', [
            'OrderIDs' => [$orderId],
        ]);

        if (! $response->successful()) {
            $this->lastError = 'Factuur versturen mislukt: '.$this->describe($response);

            return false;
        }

        return true;
    }

    private function request(): PendingRequest
    {
        $this->lastError = null;

        return Http::baseUrl(rtrim(config('services.billit.base_url'), '/'))
            ->withHeaders([
                'ApiKey' => (string) config('services.billit.api_key'),
                'PartyID' => (string) config('services.billit.party_id'),
            ])
            ->acceptJson()
            ->timeout(30);
    }

    private function describe($response): string
    {
        $errors = collect($response->json('errors') ?? [])
            ->map(fn ($e) => trim(($e['Code'] ?? '').' '.($e['Description'] ?? '')))
            ->filter()
            ->implode('; ');

        return 'HTTP '.$response->status().($errors !== '' ? " ({$errors})" : '');
    }
}
