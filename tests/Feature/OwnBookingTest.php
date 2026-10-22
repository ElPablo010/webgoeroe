<?php

use App\Models\FormSubmission;
use App\Models\Page;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Eigen kalender op een GoHighLevel-agenda: vrije momenten en boeken via de GHL-API,
 * enkel voor agenda's die in een boeking-sectie van de site staan.
 */
beforeEach(function () {
    Cache::flush();
    config([
        'services.gohighlevel.api_token' => 'test-token',
        'services.gohighlevel.location_id' => 'loc-1',
        'services.gohighlevel.base_url' => 'https://ghl.test',
    ]);

    $page = Page::create(['title' => 'Landing', 'slug' => 'landing', 'published' => true]);
    $page->sections()->create([
        'section_type' => 'booking',
        'sort_order' => 1,
        'content' => [
            'provider' => 'ghl',
            'ghl_display' => 'own',
            'url' => 'https://api.leadconnectorhq.com/widget/booking/cal123',
        ],
    ]);
});

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'calendar' => 'cal123',
        'start' => now('Europe/Brussels')->addDays(3)->setTime(10, 0)->toIso8601String(),
        'first_name' => 'Jan',
        'last_name' => 'Peeters',
        'email' => 'jan@voorbeeld.be',
        'phone' => '+32470000000',
        'website' => 'www.voorbeeld.be',
        'message' => 'Meer aanvragen',
    ], $overrides);
}

it('geeft de vrije momenten van een agenda uit de site', function () {
    Http::fake(['ghl.test/calendars/cal123/free-slots*' => Http::response([
        '2030-01-07' => ['slots' => ['2030-01-07T09:30:00+01:00', '2030-01-07T10:00:00+01:00']],
        'traceId' => 'x',
    ])]);

    $this->getJson(route('booking.slots', ['calendar' => 'cal123']))
        ->assertOk()
        ->assertJsonPath('days.2030-01-07.1', '2030-01-07T10:00:00+01:00')
        ->assertJsonMissingPath('days.traceId');
});

it('weigert agenda\'s die niet in een boeking-sectie staan', function () {
    Http::fake();

    $this->getJson(route('booking.slots', ['calendar' => 'andere']))->assertNotFound();
    $this->postJson(route('booking.store'), bookingPayload(['calendar' => 'andere']))->assertNotFound();

    Http::assertNothingSent();
});

it('maakt het contact en de afspraak met Google Meet en bewaart de inzending', function () {
    Http::fake([
        'ghl.test/contacts/upsert' => Http::response(['contact' => ['id' => 'c1']]),
        'ghl.test/calendars/cal123' => Http::response(['calendar' => [
            'name' => 'Bottleneck Scan Websites',
            'slotDuration' => 30,
            'teamMembers' => [['userId' => 'u1', 'meetingLocationType' => 'gmeet', 'locationConfigurations' => [['meetingId' => 'google_conference_0']]]],
        ]]),
        'ghl.test/calendars/events/appointments' => Http::response(['id' => 'a1'], 201),
    ]);

    $this->postJson(route('booking.store'), bookingPayload())->assertOk()->assertJson(['ok' => true]);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/upsert')
        && $r['firstName'] === 'Jan' && $r['lastName'] === 'Peeters' && $r['email'] === 'jan@voorbeeld.be');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/calendars/events/appointments')
        && $r['contactId'] === 'c1' && $r['calendarId'] === 'cal123' && $r['assignedUserId'] === 'u1'
        && $r['meetingLocationType'] === 'gmeet' && $r['meetingLocationId'] === 'google_conference_0'
        && $r['overrideLocationConfig'] === false && $r['appointmentStatus'] === 'confirmed'
        && str_contains($r['title'], 'Jan Peeters') && str_contains($r['description'], 'www.voorbeeld.be'));

    expect(FormSubmission::where('type', 'booking')->count())->toBe(1);
});

it('meldt een net bezet tijdstip als 409', function () {
    Http::fake([
        'ghl.test/contacts/upsert' => Http::response(['contact' => ['id' => 'c1']]),
        'ghl.test/calendars/cal123' => Http::response(['calendar' => ['teamMembers' => [['userId' => 'u1']]]]),
        'ghl.test/calendars/events/appointments' => Http::response(['message' => 'The slot you have chosen is no longer available'], 400),
    ]);

    $this->postJson(route('booking.store'), bookingPayload())
        ->assertStatus(409)
        ->assertJson(['ok' => false, 'reason' => 'taken']);

    expect(FormSubmission::where('type', 'booking')->count())->toBe(0);
});

it('doet alsof het lukt bij de honeypot en stuurt niets door', function () {
    Http::fake();

    $this->postJson(route('booking.store'), bookingPayload(['hp' => 'bot']))->assertOk();

    Http::assertNothingSent();
});

it('vraagt om naam en e-mail', function () {
    $this->postJson(route('booking.store'), bookingPayload(['first_name' => '', 'email' => 'geen-mail']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'email']);
});

it('toont de eigen kalender in het boeking-blok zonder iframe', function () {
    $html = Blade::render('<x-site.sections.booking :content="$content" />', ['content' => [
        'provider' => 'ghl',
        'ghl_display' => 'own',
        'url' => 'https://api.leadconnectorhq.com/widget/booking/cal123',
        'layout' => 'section',
    ]]);

    expect($html)->toContain('ownBooking')
        ->and($html)->toContain('cal123')
        ->and($html)->not->toContain('<iframe');
});

it('vraagt vrije momenten pas vanaf morgen op', function () {
    Http::fake(['ghl.test/calendars/cal123/free-slots*' => Http::response([])]);

    $this->getJson(route('booking.slots', ['calendar' => 'cal123', 'from' => now('Europe/Brussels')->toDateString()]))->assertOk();

    $morgen = now('Europe/Brussels')->addDay()->startOfDay()->getTimestampMs();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'free-slots') && (int) $r['startDate'] === $morgen);
});

it('boekt niets meer voor vandaag', function () {
    Http::fake();

    $this->postJson(route('booking.store'), bookingPayload(['start' => now('Europe/Brussels')->setTime(23, 30)->toIso8601String()]))
        ->assertStatus(409);

    Http::assertNothingSent();
});
