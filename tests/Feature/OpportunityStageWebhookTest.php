<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * De site zet de deal in GoHighLevel op de juiste stap, in welke pijplijn hij
 * ook staat: stap op naam gezocht, nooit terug naar een vroegere stap.
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

function fakeGhl(string $currentStage): void
{
    Http::fake([
        'ghl.test/opportunities/pipelines*' => Http::response(['pipelines' => [
            ['id' => 'other', 'stages' => [['id' => 'x', 'name' => 'Proposal Sent']]],
            ['id' => 'pipe-1', 'stages' => [
                ['id' => 's-new', 'name' => 'New Lead'],
                ['id' => 's-sent', 'name' => 'Proposal sent & Opportunity Created.'],
                ['id' => 's-dep', 'name' => 'WAITING FOR PAYMENT/DEPOSIT.'],
            ]],
        ]]),
        'ghl.test/opportunities/opp-1' => Http::sequence()
            ->push(['opportunity' => ['id' => 'opp-1', 'pipelineId' => 'pipe-1', 'pipelineStageId' => $currentStage]])
            ->push(['opportunity' => ['id' => 'opp-1']]),
        'api.billit.test/v1/orders' => Http::response('987', 200),
        'api.billit.test/v1/orders/commands/send' => Http::response('', 200),
    ]);
}

it('moves a sent quote to the proposal sent stage of its own pipeline', function () {
    fakeGhl('s-new');

    test()->postJson('/webhooks/ghl/offerte-verstuurd', ['opportunity_id' => 'opp-1'], ['X-Webhook-Secret' => 'geheim'])
        ->assertOk()
        ->assertJson(['stage' => 'Proposal sent & Opportunity Created.']);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
        && $r->url() === 'https://ghl.test/opportunities/opp-1'
        && $r['pipelineId'] === 'pipe-1'
        && $r['pipelineStageId'] === 's-sent');
});

it('never moves a deal back to an earlier stage', function () {
    fakeGhl('s-dep');

    test()->postJson('/webhooks/ghl/offerte-verstuurd', ['opportunity_id' => 'opp-1'], ['X-Webhook-Secret' => 'geheim'])
        ->assertOk()
        ->assertJson(['stage' => null]);

    Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT');
});

it('moves a signed quote to the deposit stage after invoicing', function () {
    fakeGhl('s-sent');

    test()->postJson('/webhooks/ghl/offerte-getekend', [
        'opportunity_id' => 'opp-1',
        'project' => 'Website',
        'amount_excl' => '1000',
        'contact' => ['first_name' => 'Jan', 'email' => 'jan@example.be'],
    ], ['X-Webhook-Secret' => 'geheim'])
        ->assertOk()
        ->assertJson(['status' => 'sent', 'stage' => 'WAITING FOR PAYMENT/DEPOSIT.']);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['pipelineStageId'] === 's-dep');
});

it('rejects the sent webhook without the secret', function () {
    Http::fake();

    test()->postJson('/webhooks/ghl/offerte-verstuurd', ['opportunity_id' => 'opp-1'])->assertUnauthorized();

    Http::assertNothingSent();
});
