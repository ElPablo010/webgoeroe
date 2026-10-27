<?php

use App\Models\DepositInvoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Een document in GoHighLevel hangt aan het contact, niet aan een deal. De
 * site zoekt de deal: één open deal = die; meerdere = die met dezelfde naam
 * als het document; anders niets doen en Pieter verwittigen.
 */
beforeEach(function () {
    config([
        'services.gohighlevel.webhook_secret' => 'geheim',
        'services.gohighlevel.api_token' => 'ghl-token',
        'services.gohighlevel.location_id' => 'loc-1',
        'services.gohighlevel.base_url' => 'https://ghl.test',
        'services.billit.api_key' => 'test-key',
        'services.billit.party_id' => '123',
        'services.billit.base_url' => 'https://api.billit.test',
    ]);
});

function fakeOpenDeals(array $deals): void
{
    Http::fake([
        'ghl.test/opportunities/search*' => Http::response(['opportunities' => $deals]),
        'ghl.test/opportunities/pipelines*' => Http::response(['pipelines' => [
            ['id' => 'pipe-1', 'stages' => [
                ['id' => 's-sent', 'name' => 'Proposal Sent'],
                ['id' => 's-dep', 'name' => 'Waiting for Deposit'],
            ]],
        ]]),
        'ghl.test/opportunities/*' => Http::response(['opportunity' => ['id' => 'x', 'pipelineId' => 'pipe-1', 'pipelineStageId' => 's-new']]),
        'api.billit.test/v1/orders' => Http::response('987', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);
}

function deal(string $id, string $name, float $value): array
{
    return ['id' => $id, 'name' => $name, 'monetaryValue' => $value, 'status' => 'open', 'contactId' => 'c-1', 'pipelineId' => 'pipe-1', 'pipelineStageId' => 's-sent'];
}

function signed(array $overrides = [])
{
    return test()->postJson('/webhooks/ghl/offerte-getekend', array_replace_recursive([
        'contact_id' => 'c-1',
        'document_name' => 'Golden Pound Collection – Webshop',
        'document_url' => 'https://link.dewebgoeroe.be/documents/abc',
        'contact' => ['company' => 'Golden Pound BV', 'email' => 'info@goldenpound.be'],
    ], $overrides), ['X-Webhook-Secret' => 'geheim']);
}

it('invoices the deal whose name matches the document when the contact has several', function () {
    fakeOpenDeals([deal('o-photo', 'Golden Pound Collection - Fotografie', 1200), deal('o-shop', 'golden pound collection – webshop', 6000)]);

    signed()->assertOk()->assertJson(['status' => 'sent']);

    expect(DepositInvoice::sole())
        ->opportunity_id->toBe('o-shop')
        ->amount_excl->toBe('3000.00')
        ->project->toBe('golden pound collection – webshop');

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/opportunities/o-shop'));
});

it('uses the only open deal even when the names differ', function () {
    fakeOpenDeals([deal('o-1', 'Website Peeters', 5000)]);

    signed(['document_name' => 'Offerte De WebGoeroe (PDF + akkoord)'])->assertOk();

    expect(DepositInvoice::sole()->opportunity_id)->toBe('o-1');
});

it('never invoices when several deals exist and none matches', function () {
    Mail::fake();
    fakeOpenDeals([deal('o-photo', 'Fotografie', 1200), deal('o-shop', 'Webshop', 6000)]);

    signed(['document_name' => 'Iets anders'])->assertOk()->assertJson(['status' => 'unmatched']);

    expect(DepositInvoice::count())->toBe(0);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'billit'));
    Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT');
});

it('does nothing when the contact has no open deal', function () {
    Mail::fake();
    fakeOpenDeals([]);

    signed()->assertOk()->assertJson(['status' => 'unmatched']);

    expect(DepositInvoice::count())->toBe(0);
});

it('refuses a matched deal without value', function () {
    Mail::fake();
    fakeOpenDeals([deal('o-1', 'Webshop', 0)]);

    signed()->assertStatus(422);

    expect(DepositInvoice::count())->toBe(0);
});

it('moves the matching deal to proposal sent when a quote is sent', function () {
    fakeOpenDeals([deal('o-photo', 'Fotografie', 1200), deal('o-shop', 'Webshop', 6000)]);

    test()->postJson('/webhooks/ghl/offerte-verstuurd', ['contact_id' => 'c-1', 'document_name' => 'Webshop'], ['X-Webhook-Secret' => 'geheim'])
        ->assertOk();

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/opportunities/o-shop'));
    Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/opportunities/o-photo'));
});

it('treats the same signed document twice as one invoice', function () {
    fakeOpenDeals([deal('o-1', 'Webshop', 6000)]);

    signed()->assertOk();
    signed()->assertOk();

    expect(DepositInvoice::count())->toBe(1);
});
