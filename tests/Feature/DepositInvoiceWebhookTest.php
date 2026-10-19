<?php

use App\Models\DepositInvoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Getekende offerte in GoHighLevel → voorschotfactuur (50%) in Billit.
 */
beforeEach(function () {
    config([
        'services.gohighlevel.webhook_secret' => 'geheim',
        'services.billit.api_key' => 'test-key',
        'services.billit.party_id' => '123',
        'services.billit.base_url' => 'https://api.billit.test',
    ]);
});

function quoteSignedPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'document_id' => 'doc-1',
        'opportunity_id' => 'opp-1',
        'project' => 'Website Elektro Peeters',
        'amount_excl' => '5000',
        'contact' => [
            'company' => 'Elektro Peeters BV',
            'first_name' => 'Jan',
            'last_name' => 'Peeters',
            'email' => 'jan@elektropeeters.be',
            'vat_number' => 'be 0123.456.789',
            'street' => 'Kerkstraat 12A',
            'postal_code' => '2500',
            'city' => 'Lier',
            'country' => 'België',
        ],
    ], $overrides);
}

function postQuoteSigned(array $payload, string $secret = 'geheim')
{
    return test()->postJson('/webhooks/ghl/offerte-getekend', $payload, ['X-Webhook-Secret' => $secret]);
}

it('creates and sends a 50% deposit invoice in Billit', function () {
    Http::fake([
        'api.billit.test/v1/orders' => Http::response('987', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);

    postQuoteSigned(quoteSignedPayload())
        ->assertOk()
        ->assertJson(['status' => 'sent', 'billit_order_id' => 987]);

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://api.billit.test/v1/orders') {
            return false;
        }

        $line = $request['OrderLines'][0];

        return $request->header('ApiKey')[0] === 'test-key'
            && $request->header('PartyID')[0] === '123'
            && $request['OrderType'] === 'Invoice'
            && $request['OrderDirection'] === 'Income'
            && ! isset($request['OrderNumber'])
            && $line['UnitPriceExcl'] === 2500.0
            && $line['VATPercentage'] === 21.0
            && $request['Customer']['VATNumber'] === 'BE0123456789'
            && $request['Customer']['Name'] === 'Elektro Peeters BV'
            && $request['Customer']['Addresses'][0]['Street'] === 'Kerkstraat'
            && $request['Customer']['Addresses'][0]['StreetNumber'] === '12A'
            && $request['Customer']['Addresses'][0]['CountryCode'] === 'BE';
    });

    // Zonder TransportType: Billit kiest Peppol, anders e-mail.
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/commands/send')
        && $request->data() === ['OrderIDs' => [987]]);

    expect(DepositInvoice::sole())
        ->status->toBe('sent')
        ->amount_excl->toBe('2500.00');
});

it('never creates a second invoice for the same signed document', function () {
    Http::fake([
        'api.billit.test/v1/orders' => Http::response('987', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);

    postQuoteSigned(quoteSignedPayload())->assertOk();
    postQuoteSigned(quoteSignedPayload())->assertOk();

    Http::assertSentCount(2);
    expect(DepositInvoice::count())->toBe(1);
});

it('only retries sending when the invoice already exists in Billit', function () {
    Mail::fake();

    Http::fakeSequence('api.billit.test/v1/orders/commands/send')
        ->push(['errors' => [['Code' => 'TheCustomerIsNotActiveOnPeppol', 'Description' => 'Not on Peppol']]], 400)
        ->push('', 200);
    Http::fake(['api.billit.test/v1/orders' => Http::response('987', 200)]);

    postQuoteSigned(quoteSignedPayload())
        ->assertStatus(502)
        ->assertJson(['status' => 'failed']);

    expect(DepositInvoice::sole()->error)->toContain('TheCustomerIsNotActiveOnPeppol');

    postQuoteSigned(quoteSignedPayload())->assertOk()->assertJson(['status' => 'sent']);

    Http::assertSentCount(3); // één keer aanmaken, twee keer versturen
});

it('marks the invoice failed when Billit refuses it', function () {
    Mail::fake();
    Http::fake(['api.billit.test/*' => Http::response(['errors' => [['Code' => 'X', 'Description' => 'Ongeldig']]], 400)]);

    postQuoteSigned(quoteSignedPayload())->assertStatus(502);

    expect(DepositInvoice::sole())
        ->status->toBe('failed')
        ->billit_order_id->toBeNull();
});

it('invoices a private customer without VAT number', function () {
    Http::fake([
        'api.billit.test/v1/orders' => Http::response('55', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);

    postQuoteSigned(quoteSignedPayload(['contact' => ['company' => '', 'vat_number' => '']]))->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.billit.test/v1/orders'
        && ! isset($request['Customer']['VATNumber'])
        && $request['Customer']['Name'] === 'Jan Peeters');
});

it('parses Belgian formatted amounts', function () {
    Http::fake([
        'api.billit.test/v1/orders' => Http::response('1', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);

    postQuoteSigned(quoteSignedPayload(['amount_excl' => '€ 3.250,50']))->assertOk();

    expect(DepositInvoice::sole()->amount_excl)->toBe('1625.25');
});

it('rejects a wrong secret', function () {
    Http::fake();

    postQuoteSigned(quoteSignedPayload(), 'fout')->assertUnauthorized();

    Http::assertNothingSent();
});

it('rejects a missing deal value', function () {
    Http::fake();

    postQuoteSigned(quoteSignedPayload(['amount_excl' => '0']))->assertStatus(422);

    Http::assertNothingSent();
});
