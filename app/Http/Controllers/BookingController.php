<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\PageSection;
use App\Services\GoHighLevel\CalendarBooking;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Eigen boekingsscherm ("Eigen kalender" in het boeking-blok) op een GoHighLevel-agenda.
 * Alleen agenda's die in een boeking-sectie van de site staan zijn bereikbaar,
 * zodat deze endpoints geen willekeurige GHL-agenda openzetten.
 */
class BookingController extends Controller
{
    public function slots(Request $request, CalendarBooking $booking): JsonResponse
    {
        $data = $request->validate([
            'calendar' => ['required', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! $this->isAllowedCalendar($data['calendar'])) {
            abort(404);
        }

        // Vandaag boeken we niet meer: het eerste vrije moment is morgen.
        $earliest = self::earliest();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], CalendarBooking::TIMEZONE)->startOfDay() : $earliest;
        $from = $from->max($earliest)->min($earliest->addDays(90));

        $days = Cache::remember(
            "booking.slots.{$data['calendar']}.{$from->toDateString()}",
            60,
            fn () => $booking->slots($data['calendar'], $from, $from->addDays(30)),
        );

        return response()->json(['days' => (object) $days]);
    }

    public function store(Request $request, CalendarBooking $booking): JsonResponse
    {
        // Honeypot: een bot vult het verborgen veld in. Doe alsof het gelukt is.
        if (filled($request->input('hp'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'calendar' => ['required', 'string', 'max:64'],
            'start' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->isAllowedCalendar($data['calendar'])) {
            abort(404);
        }

        $start = CarbonImmutable::parse($data['start']);

        if ($start->lt(self::earliest())) {
            return response()->json(['ok' => false, 'reason' => 'taken'], 409);
        }

        $result = $booking->book([
            'calendar_id' => $data['calendar'],
            'start' => $data['start'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        if (! $result['ok']) {
            Cache::forget("booking.slots.{$data['calendar']}.".self::earliest()->toDateString());

            return response()->json($result, $result['reason'] === 'taken' ? 409 : 502);
        }

        FormSubmission::create([
            'type' => 'booking',
            'data' => [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'start' => $result['start'],
            ],
            'page_url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ]);

        return response()->json($result);
    }

    /** Begin van morgen (Brusselse tijd): vroeger dan dat is niet te boeken. */
    private static function earliest(): CarbonImmutable
    {
        return CarbonImmutable::now(CalendarBooking::TIMEZONE)->addDay()->startOfDay();
    }

    /** De agenda-ID is het laatste deel van de agenda-URL in een GoHighLevel-boeking-sectie. */
    private function isAllowedCalendar(string $calendarId): bool
    {
        return Cache::remember("booking.allowed.{$calendarId}", 300, function () use ($calendarId): bool {
            return PageSection::query()
                ->where('section_type', 'booking')
                ->get()
                ->contains(function (PageSection $section) use ($calendarId): bool {
                    $content = $section->content ?? [];

                    return ($content['provider'] ?? null) === 'ghl'
                        && basename((string) parse_url((string) ($content['url'] ?? ''), PHP_URL_PATH)) === $calendarId;
                });
        });
    }
}
