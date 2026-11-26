<?php

use App\Models\FormSubmission;
use App\Models\Page;
use App\Support\AdAttribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Webgoeroe\Core\Integrations\Meta\ConversionsApi;
use Webgoeroe\Core\Jobs\SendMetaEvent;

function wgMetaOn(bool $on = true): void
{
    ConversionsApi::flushColumnCache();
    config([
        'core.meta.enabled' => $on,
        'core.meta.pixel_id' => '123456',
        'core.meta.access_token' => 'tok',
        'services.gohighlevel.api_token' => null,
    ]);
}

function leadPayload(array $tracking): array
{
    return [
        'first_name' => 'Jan', 'email' => 'Jan@Example.be', 'sector' => 'Renovatie',
        'visitors' => 1000, 'leads' => 10, 'close' => 30, 'value' => 5000, 'tracking' => $tracking,
    ];
}

it('zet een Lead in de wachtrij via de core, met ontdubbel-id, na marketingtoestemming', function () {
    wgMetaOn();
    Queue::fake();

    $this->postJson('/omzetcheck', leadPayload([
        'consent' => true, 'event_id' => 'evt-1', 'fbp' => 'fb.1.1.2',
        'attribution' => ['utm_source' => 'meta', 'utm_campaign' => 'websites', 'utm_content' => 'ad-1', 'sector' => 'renovatie'],
    ]))->assertOk();

    Queue::assertPushed(SendMetaEvent::class);

    $submission = FormSubmission::where('type', 'omzetcheck')->sole();
    expect($submission->data['attribution'])->toMatchArray(['utm_source' => 'meta', 'utm_content' => 'ad-1'])
        ->and($submission->data['herkomst'])->toBe('meta | websites | ad-1 | renovatie')
        ->and($submission->meta_tracking['event_id'])->toBe('evt-1');
});

it('stuurt geen serverevent zonder toestemming, maar bewaart de herkomst wel', function () {
    wgMetaOn();
    Queue::fake();

    $this->postJson('/omzetcheck', leadPayload([
        'consent' => false, 'event_id' => 'evt-2', 'attribution' => ['utm_content' => 'ad-2'],
    ]))->assertOk();

    Queue::assertNotPushed(SendMetaEvent::class);
    expect(FormSubmission::where('type', 'omzetcheck')->sole()->data['attribution'])->toMatchArray(['utm_content' => 'ad-2']);
});

it('stuurt niets naar Meta zolang de core-feature uitstaat', function () {
    wgMetaOn(false);
    Queue::fake();

    $this->postJson('/omzetcheck', leadPayload(['consent' => true, 'event_id' => 'evt-3']))->assertOk();

    Queue::assertNotPushed(SendMetaEvent::class);
});

it('haalt de herkomst uit tracking.attribution en negeert onbekende sleutels', function () {
    $request = Request::create('/', 'POST', ['tracking' => ['attribution' => ['utm_source' => 'meta', 'x' => 'y', 'sector' => 'dak']]]);

    expect(AdAttribution::from($request))->toBe(['utm_source' => 'meta', 'sector' => 'dak'])
        ->and(AdAttribution::line(AdAttribution::from($request)))->toBe('meta | dak');
});

it('laadt de pixel van de core één keer, alleen als de feature aanstaat', function () {
    Page::create(['title' => 'Over ons', 'slug' => 'over-ons', 'published' => true]);

    wgMetaOn(false);
    expect($this->get('/over-ons')->assertOk()->getContent())->not->toContain('data-analytics="meta-pixel"');

    wgMetaOn();
    $html = $this->get('/over-ons')->assertOk()->getContent();

    expect(substr_count($html, 'data-analytics="meta-pixel"'))->toBe(1)
        ->and($html)->toContain('window.wgTracking')
        ->and($html)->toContain('consent && consent.marketing')
        ->and($html)->toContain('data-analytics="tracking-attribution"');
});
