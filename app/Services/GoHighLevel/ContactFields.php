<?php

namespace App\Services\GoHighLevel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Contactvelden van GoHighLevel voor het formulier van de eigen kalender: de vijf
 * standaardvelden plus de eigen (custom) velden van de locatie. Elk formulierveld op
 * de site wordt aan één van deze velden gekoppeld (`target`):
 *
 * - 'first_name', 'last_name', 'email', 'phone', 'website' → standaardveld
 * - 'custom:<id>'                                          → eigen GHL-veld
 * - 'appointment'                                          → enkel in de afspraakbeschrijving
 *
 * Token: scope locations/customFields.readonly (lijst) en .write (veld aanmaken).
 */
class ContactFields
{
    public const STANDARD = [
        'first_name' => 'Voornaam',
        'last_name' => 'Achternaam',
        'email' => 'E-mailadres',
        'phone' => 'Telefoon',
        'website' => 'Website',
    ];

    public const APPOINTMENT = 'appointment';

    /** Veldtypes van GHL die als tekst in te vullen zijn → het formuliertype op de site. */
    private const INPUT_TYPES = ['TEXT' => 'text', 'LARGE_TEXT' => 'textarea', 'PHONE' => 'tel', 'NUMERICAL' => 'text', 'MONETORY' => 'text', 'DATE' => 'text'];

    private const CACHE_KEY = 'ghl.contact-fields';

    /**
     * Eigen velden van de locatie die als tekst in te vullen zijn, op naam: [id => naam].
     * Een kwartier in de cache; refresh() leegt die (knop "Lijst vernieuwen").
     * Een mislukte opvraging (bv. geen token) wordt niet gecachet: dan null.
     *
     * @return array<string, string>|null
     */
    public function custom(): ?array
    {
        if ($cached = Cache::get(self::CACHE_KEY)) {
            return $cached;
        }

        try {
            $response = $this->request()->get('/locations/'.config('services.gohighlevel.location_id').'/customFields', ['model' => 'contact']);
        } catch (\Throwable $e) {
            Log::error('Contactvelden ophalen uit GHL mislukt.', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::error('Contactvelden ophalen uit GHL mislukt.', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $fields = collect($response->json('customFields', []))
            ->filter(fn (array $field) => isset(self::INPUT_TYPES[$field['dataType'] ?? '']))
            ->mapWithKeys(fn (array $field) => [$field['id'] => trim((string) $field['name'])])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();

        Cache::put(self::CACHE_KEY, $fields, 900);

        return $fields;
    }

    /** Leesbare naam van een target, ook als het GHL-veld (nog) niet in de lijst staat. */
    public function label(?string $target): ?string
    {
        if (blank($target)) {
            return null;
        }

        if (str_starts_with($target, 'custom:')) {
            return ($this->custom() ?? [])[substr($target, 7)] ?? 'Eigen GHL-veld (naam niet op te halen)';
        }

        return self::STANDARD[$target] ?? ($target === self::APPOINTMENT ? 'Alleen in de afspraak' : $target);
    }

    /**
     * Keuzelijst "Opslaan in" in de admin, gegroepeerd.
     *
     * @return array<string, array<string, string>>
     */
    public function options(): array
    {
        return [
            'Standaardvelden' => self::STANDARD,
            'Eigen velden in GoHighLevel' => collect($this->custom() ?? [])->mapWithKeys(fn ($name, $id) => ['custom:'.$id => $name])->all(),
            'Niet in een contactveld' => [self::APPOINTMENT => 'Alleen in de afspraak'],
        ];
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Maakt een eigen contactveld aan in GHL, of geeft het bestaande met dezelfde naam
     * terug (geen dubbele velden). Geeft het target ('custom:<id>') of null bij een fout.
     */
    public function create(string $name, bool $largeText): ?string
    {
        $name = trim($name);
        $existing = collect($this->custom() ?? [])->search(fn ($label) => Str::lower($label) === Str::lower($name));

        if ($existing !== false) {
            return 'custom:'.$existing;
        }

        $response = $this->request()->post('/locations/'.config('services.gohighlevel.location_id').'/customFields', [
            'name' => $name,
            'dataType' => $largeText ? 'LARGE_TEXT' : 'TEXT',
            'model' => 'contact',
        ]);

        $id = $response->json('customField.id');

        if (! $response->successful() || blank($id)) {
            Log::error('Contactveld aanmaken in GHL mislukt.', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $this->refresh();

        return 'custom:'.$id;
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
