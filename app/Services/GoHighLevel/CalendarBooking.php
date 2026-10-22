<?php

namespace App\Services\GoHighLevel;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Eigen boekingsscherm op een GoHighLevel-agenda: vrije tijdstippen ophalen en
 * een afspraak maken (met contact), zodat de bezoeker niet in een iframe hoeft.
 * De afspraak komt in dezelfde agenda terecht als een boeking via GHL's eigen
 * widget, dus workflows op "Appointment status" draaien gewoon mee. Google Meet
 * wordt via de agenda-instelling aangemaakt (meetingLocationType gmeet).
 *
 * Token: Private Integration met scopes calendars.readonly, calendars/events.write,
 * contacts.write (en contacts.readonly).
 */
class CalendarBooking
{
    public const TIMEZONE = 'Europe/Brussels';

    public function isConfigured(): bool
    {
        return filled(config('services.gohighlevel.api_token'))
            && filled(config('services.gohighlevel.location_id'));
    }

    /**
     * Vrije tijdstippen per dag, zoals GHL ze geeft (ISO met offset, Brusselse tijd).
     *
     * @return array<string, list<string>>
     */
    public function slots(string $calendarId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $response = $this->request()->get("/calendars/{$calendarId}/free-slots", [
            'startDate' => $from->setTimezone(self::TIMEZONE)->startOfDay()->getTimestampMs(),
            'endDate' => $to->setTimezone(self::TIMEZONE)->endOfDay()->getTimestampMs(),
            'timezone' => self::TIMEZONE,
        ]);

        if (! $response->successful()) {
            Log::error('Boeking: vrije tijdstippen ophalen mislukt.', ['status' => $response->status(), 'body' => $response->body()]);

            return [];
        }

        $days = [];

        foreach ((array) $response->json() as $date => $day) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && ! empty($day['slots'])) {
                $days[$date] = array_values($day['slots']);
            }
        }

        ksort($days);

        return $days;
    }

    /**
     * @param  array{first_name?: ?string, last_name?: ?string, email: string, phone?: ?string, website?: ?string}  $contact  standaardvelden
     * @param  array<string, string>  $customFields  [GHL-veld-ID => antwoord]
     * @param  array<string, string>  $notes  [vraag => antwoord], enkel in de afspraakbeschrijving
     * @return array{ok: bool, reason?: string, start?: string}  reason: 'taken' (tijdstip net bezet) of 'error'
     */
    public function book(string $calendarId, string $start, array $contact, array $customFields = [], array $notes = []): array
    {
        if (! $this->isConfigured()) {
            Log::error('Boeking: GHL niet ingesteld (GHL_API_TOKEN), afspraak niet gemaakt.');

            return ['ok' => false, 'reason' => 'error'];
        }

        try {
            $start = CarbonImmutable::parse($start)->setTimezone(self::TIMEZONE);
            $calendar = $this->calendar($calendarId);
            $name = trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '')) ?: $contact['email'];

            $upsert = $this->request()->post('/contacts/upsert', array_filter([
                'locationId' => config('services.gohighlevel.location_id'),
                'firstName' => $contact['first_name'] ?? null,
                'lastName' => $contact['last_name'] ?? null,
                'email' => $contact['email'],
                'phone' => $contact['phone'] ?? null,
                'website' => $contact['website'] ?? null,
                'source' => 'Boeking website',
                'customFields' => collect($customFields)
                    ->map(fn ($value, $id) => ['id' => $id, 'field_value' => $value])
                    ->values()
                    ->all() ?: null,
            ]));

            $contactId = $upsert->json('contact.id');

            if (! $upsert->successful() || blank($contactId)) {
                Log::error('Boeking: contact aanmaken in GHL mislukt.', ['status' => $upsert->status(), 'body' => $upsert->body()]);

                return ['ok' => false, 'reason' => 'error'];
            }

            $member = $calendar['teamMembers'][0] ?? [];
            $location = collect($member['locationConfigurations'] ?? [])->first();

            $appointment = $this->request()->post('/calendars/events/appointments', array_filter([
                'locationId' => config('services.gohighlevel.location_id'),
                'calendarId' => $calendarId,
                'contactId' => $contactId,
                'assignedUserId' => $member['userId'] ?? null,
                'title' => trim(($calendar['name'] ?? 'Gesprek').' – '.$name),
                'description' => $this->description($notes),
                'startTime' => $start->toIso8601String(),
                'endTime' => $start->addMinutes((int) ($calendar['slotDuration'] ?? 30))->toIso8601String(),
                'appointmentStatus' => 'confirmed',
                'meetingLocationType' => $member['meetingLocationType'] ?? null,
                'meetingLocationId' => $location['meetingId'] ?? null,
                'overrideLocationConfig' => false,
            ], fn ($value) => $value !== null));

            if ($appointment->successful()) {
                return ['ok' => true, 'start' => $start->toIso8601String()];
            }

            Log::error('Boeking: afspraak maken in GHL mislukt.', ['status' => $appointment->status(), 'body' => $appointment->body()]);

            // GHL meldt een bezet of ongeldig tijdstip als 4xx met "slot" in de tekst.
            return ['ok' => false, 'reason' => preg_match('/slot|available|beschikbaar/i', $appointment->body()) ? 'taken' : 'error'];
        } catch (\Throwable $e) {
            Log::error('Boeking: GHL niet bereikbaar.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'error'];
        }
    }

    /** Agenda-instellingen (teamlid, duur, Meet-locatie), een uur in de cache. */
    private function calendar(string $calendarId): array
    {
        return Cache::remember("ghl.calendar.{$calendarId}", 3600, function () use ($calendarId): array {
            $response = $this->request()->get("/calendars/{$calendarId}");

            return $response->successful() ? (array) $response->json('calendar') : [];
        });
    }

    /** Inleiding + de antwoorden ("vraag: antwoord"), zichtbaar in de agenda en de Google-uitnodiging. */
    private function description(array $notes): string
    {
        $lines = [(string) config('services.gohighlevel.booking.description')];

        foreach ($notes as $question => $answer) {
            $lines[] = $question."\n".$answer;
        }

        return implode("\n\n", array_filter($lines));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.gohighlevel.base_url'), '/'))
            ->withToken((string) config('services.gohighlevel.api_token'))
            ->withHeaders(['Version' => '2021-04-15'])
            ->acceptJson()
            ->timeout(15);
    }
}
