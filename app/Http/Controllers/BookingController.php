<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\PageSection;
use App\Services\GoHighLevel\CalendarBooking;
use App\Services\GoHighLevel\ContactFields;
use App\Support\BookingForm;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

        $request->validate([
            'section' => ['required', 'integer'],
            'start' => ['required', 'date'],
            'answers' => ['nullable', 'array'],
        ]);

        // De velden komen uit de sectie zelf, nooit uit de browser: wat de bezoeker
        // instuurt bepaalt enkel de antwoorden.
        $section = PageSection::query()->whereKey($request->integer('section'))->where('section_type', 'booking')->first();
        $content = $section?->content ?? [];

        if (! $section || ($content['provider'] ?? null) !== 'ghl') {
            abort(404);
        }

        $calendarId = self::calendarId($content);
        $fields = BookingForm::fields($content);

        $answers = $request->validate(
            collect($fields)->mapWithKeys(fn (array $field) => ['answers.'.$field['key'] => array_filter([
                $field['required'] ? 'required' : 'nullable',
                'string',
                $field['type'] === 'textarea' ? 'max:2000' : 'max:255',
                $field['type'] === 'email' ? 'email:rfc' : null,
            ])])->all(),
            [],
            collect($fields)->mapWithKeys(fn (array $field) => ['answers.'.$field['key'] => Str::lower($field['label'])])->all(),
        )['answers'] ?? [];

        $start = CarbonImmutable::parse($request->input('start'));

        if ($start->lt(self::earliest())) {
            return response()->json(['ok' => false, 'reason' => 'taken'], 409);
        }

        $contact = [];
        $customFields = [];
        $notes = [];

        foreach ($fields as $field) {
            $value = trim((string) ($answers[$field['key']] ?? ''));

            if ($value === '') {
                continue;
            }

            $target = $field['target'];

            if (array_key_exists($target, ContactFields::STANDARD)) {
                $contact[$target] = $value;
            } elseif (str_starts_with($target, 'custom:')) {
                $customFields[substr($target, 7)] = $value;
            }

            // Alles behalve naam, e-mail en telefoon komt ook in de afspraak (Google-uitnodiging).
            if (! in_array($target, ['first_name', 'last_name', 'email', 'phone'], true)) {
                $notes[$field['label']] = $value;
            }
        }

        $result = $booking->book($calendarId, $start->toIso8601String(), $contact, $customFields, $notes);

        if (! $result['ok']) {
            Cache::forget("booking.slots.{$calendarId}.".self::earliest()->toDateString());

            return response()->json($result, $result['reason'] === 'taken' ? 409 : 502);
        }

        FormSubmission::create([
            'type' => 'booking',
            'data' => [
                ...collect($fields)->mapWithKeys(fn (array $field) => [$field['label'] => $answers[$field['key']] ?? null])->filter()->all(),
                'start' => $result['start'],
            ],
            'page_url' => $request->headers->get('referer'),
            'ip' => $request->ip(),
        ]);

        return response()->json($result);
    }

    /** De agenda-ID is het laatste deel van de agenda-URL in de boeking-sectie. */
    public static function calendarId(array $content): string
    {
        return basename((string) parse_url((string) ($content['url'] ?? ''), PHP_URL_PATH));
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

                    return ($content['provider'] ?? null) === 'ghl' && self::calendarId($content) === $calendarId;
                });
        });
    }
}
