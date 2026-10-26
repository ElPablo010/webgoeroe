<?php

use App\Models\FormSubmission;
use App\Models\Page;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.meta.pixel_id' => '123456',
        'services.meta.access_token' => 'tok',
        'services.gohighlevel.api_token' => null,
    ]);
});

function leadPayload(array $tracking): array
{
    return [
        'first_name' => 'Jan', 'email' => 'Jan@Example.be', 'sector' => 'Renovatie',
        'visitors' => 1000, 'leads' => 10, 'close' => 30, 'value' => 5000, 'tracking' => $tracking,
    ];
}

it('stuurt een Lead-serverevent met gehashte e-mail en ontdubbel-id na marketingtoestemming', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

    $this->postJson('/omzetcheck', leadPayload([
        'consent' => true, 'event_id' => 'evt-1', 'fbp' => 'fb.1.1.2', 'fbc' => null,
        'attribution' => ['utm_source' => 'meta', 'utm_campaign' => 'websites', 'utm_content' => 'ad-1', 'sector' => 'renovatie'],
    ]))->assertOk();

    Http::assertSent(function ($request) {
        $event = $request['data'][0] ?? [];

        return str_contains($request->url(), '/123456/events')
            && $event['event_name'] === 'Lead'
            && $event['event_id'] === 'evt-1'
            && $event['user_data']['em'][0] === hash('sha256', 'jan@example.be')
            && $event['user_data']['fbp'] === 'fb.1.1.2';
    });

    expect(FormSubmission::where('type', 'omzetcheck')->sole()->data['attribution'])
        ->toMatchArray(['utm_source' => 'meta', 'utm_content' => 'ad-1']);
});

it('stuurt geen serverevent zonder toestemming, maar bewaart de herkomst wel', function () {
    Http::fake();

    $this->postJson('/omzetcheck', leadPayload([
        'consent' => false, 'event_id' => 'evt-2', 'attribution' => ['utm_content' => 'ad-2'],
    ]))->assertOk();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    expect(FormSubmission::where('type', 'omzetcheck')->sole()->data['attribution'])->toMatchArray(['utm_content' => 'ad-2']);
});

it('stuurt niets zonder ingesteld pixel-ID of token', function () {
    config(['services.meta.access_token' => null]);
    Http::fake();

    $this->postJson('/omzetcheck', leadPayload(['consent' => true, 'event_id' => 'evt-3']))->assertOk();

    Http::assertNothingSent();
});

it('laadt de pixel alleen na marketingtoestemming en geeft het event_id door', function () {
    Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);
    $html = $this->get('/over-ons')->assertOk()->getContent();

    expect($html)->toContain('data-analytics="meta-pixel"')
        ->and($html)->toContain('window.wgTracking')
        ->and($html)->toContain('consent && consent.marketing');
});
