<?php

use Webgoeroe\Core\Mcp\CoreServer;
use App\Mcp\Tools\ListPayments;
use App\Models\User;
use Webgoeroe\Core\Agenda\Agenda;
use Webgoeroe\Core\Enums\UserRole;
use Webgoeroe\Core\Models\AgendaBooking;
use Webgoeroe\Core\Models\Payment;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

function bookingForPayment(array $answers = [], string $name = 'Jan Peeters'): AgendaBooking
{
    return AgendaBooking::create([
        'token' => Agenda::newToken(),
        'status' => 'confirmed',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
        'slot_key' => 'k'.uniqid(),
        'name' => $name,
        'email' => 'jan@example.test',
        'agenda_type' => 'startsessie',
        'answers' => $answers,
        'price_excl_cents' => 25000,
        'vat_cents' => 5250,
    ]);
}

function paymentFor(AgendaBooking $booking, string $status, $paidAt, string $providerId): Payment
{
    return Payment::create([
        'token' => 'tok'.uniqid(),
        'payable_type' => $booking->getMorphClass(),
        'payable_id' => $booking->id,
        'kind' => 'Afspraak',
        'provider' => 'mollie',
        'provider_payment_id' => $providerId,
        'amount_excl_cents' => 25000,
        'vat_cents' => 5250,
        'amount_cents' => 30250,
        'status' => $status,
        'paid_at' => $paidAt,
        'customer_name' => $booking->name,
        'customer_email' => $booking->email,
        'description' => 'Startsessie – donderdag 26 november om 10:00',
    ]);
}

it('geeft enkel betaalde betalingen terug, met factuurgegevens', function () {
    $paid = paymentFor(
        bookingForPayment(['Bedrijfsnaam' => 'Peeters BV', 'Btw-nummer' => 'BE0123456789']),
        'paid', now()->subDay(), 'tr_betaald',
    );
    paymentFor(bookingForPayment(name: 'Open Klant'), 'open', null, 'tr_open');
    paymentFor(bookingForPayment(name: 'Mislukt Klant'), 'failed', null, 'tr_mislukt');

    $response = CoreServer::actingAs($this->admin)->tool(ListPayments::class, []);

    $response->assertOk()
        ->assertSee('tr_betaald')
        ->assertDontSee('tr_open')
        ->assertDontSee('tr_mislukt')
        ->assertSee('Peeters BV')
        ->assertSee('BE0123456789')
        ->assertSee('Startsessie')
        ->assertSee('250.00')
        ->assertSee('52.50')
        ->assertSee('302.50')
        ->assertSee((string) $paid->id);
});

it('laat bedrijfsnaam en btw-nummer leeg als ze niet ingevuld zijn', function () {
    paymentFor(bookingForPayment(), 'paid', now()->subHour(), 'tr_zonder_bedrijf');

    $response = CoreServer::actingAs($this->admin)->tool(ListPayments::class, []);

    $response->assertOk()
        ->assertSee('tr_zonder_bedrijf')
        ->assertSee('"company":null', false)
        ->assertSee('"vat_number":null', false);
});

it('houdt standaard 14 dagen aan en volgt de parameter since', function () {
    paymentFor(bookingForPayment(), 'paid', now()->subDays(3), 'tr_recent');
    paymentFor(bookingForPayment(), 'paid', now()->subDays(20), 'tr_oud');

    CoreServer::actingAs($this->admin)->tool(ListPayments::class, [])
        ->assertSee('tr_recent')
        ->assertDontSee('tr_oud');

    CoreServer::actingAs($this->admin)->tool(ListPayments::class, ['since' => now()->subDays(30)->toDateString()])
        ->assertSee('tr_recent')
        ->assertSee('tr_oud');
});

it('is alleen-lezen gemarkeerd', function () {
    $annotations = (new ListPayments)->annotations();

    expect($annotations['readOnlyHint'] ?? null)->toBeTrue();
});
