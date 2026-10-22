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
            Log::warning('Boeking: vrije tijdstippen ophalen mislukt.', ['status' => $response->status(), 'body' => $response->body()]);

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
     * @param  array{calendar_id: string, start: string, first_name: string, last_name: string, email: string, phone?: ?string, website?: ?string, message?: ?string}  $data
     * @return array{ok: bool, reason?: string, start?: string}  reason: 'taken' (tijdstip net bezet) of 'error'
     */
    public function book(array $data): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Boeking: GHL niet ingesteld (GHL_API_TOKEN), afspraak niet gemaakt.');

            return ['ok' => false, 'reason' => 'error'];
        }

        try {
            $start = CarbonImmutable::parse($data['start'])->setTimezone(self::TIMEZONE);
            $calendar = $this->calendar($data['calendar_id']);
            $name = trim($data['first_name'].' '.$data['last_name']);

            $contact = $this->request()->post('/contacts/upsert', array_filter([
                'locationId' => config('services.gohighlevel.location_id'),
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'source' => 'Boeking website',
            ]));

            $contactId = $contact->json('contact.id');

            if (! $contact->successful() || blank($contactId)) {
                Log::warning('Boeking: contact aanmaken in GHL mislukt.', ['status' => $contact->status(), 'body' => $contact->body()]);

                return ['ok' => false, 'reason' => 'error'];
            }

            $location = collect($calendar['teamMembers'][0]['locationConfigurations'] ?? [])->first();

            $appointment = $this->request()->post('/calendars/events/appointments', array_filter([
                'locationId' => config('services.gohighlevel.location_id'),
                'calendarId' => $data['calendar_id'],
                'contactId' => $contactId,
                'assignedUserId' => $calendar['teamMembers'][0]['userId'] ?? null,
                'title' => trim(($calendar['name'] ?? 'Gesprek').' – '.$name),
                'description' => $this->description($data),
                'startTime' => $start->toIso8601String(),
                'endTime' => $start->addMinutes((int) ($calendar['slotDuration'] ?? 30))->toIso8601String(),
                'appointmentStatus' => 'confirmed',
                'meetingLocationType' => $calendar['teamMembers'][0]['meetingLocationType'] ?? null,
                'meetingLocationId' => $location['meetingId'] ?? null,
                'overrideLocationConfig' => false,
            ], fn ($value) => $value !== null));

            if ($appointment->successful()) {
                return ['ok' => true, 'start' => $start->toIso8601String()];
            }

            Log::warning('Boeking: afspraak maken in GHL mislukt.', ['status' => $appointment->status(), 'body' => $appointment->body()]);

            // GHL meldt een bezet of ongeldig tijdstip als 4xx met "slot" in de tekst.
            return ['ok' => false, 'reason' => preg_match('/slot|available|beschikbaar/i', $appointment->body()) ? 'taken' : 'error'];
        } catch (\Throwable $e) {
            Log::warning('Boeking: GHL niet bereikbaar.', ['error' => $e->getMessage()]);

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

    private function description(array $data): string
    {
        $lines = [(string) config('services.gohighlevel.booking.description')];

        if (filled($data['website'] ?? null)) {
            $lines[] = 'Website: '.$data['website'];
        }

        if (filled($data['message'] ?? null)) {
            $lines[] = 'Wat je uit het gesprek hoopt te halen: '.$data['message'];
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
