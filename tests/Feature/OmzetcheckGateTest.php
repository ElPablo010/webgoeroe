<?php

use App\Http\Controllers\OmzetcheckController;
use App\Models\FormSubmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;

/**
 * Gate op de omzetcheck: voornaam + e-mail → resultaat, inzending (lead) en
 * contact in GoHighLevel (velden eerst, daarna de tag die de workflow start).
 */
beforeEach(function () {
    config([
        'services.gohighlevel.api_token' => 'test-token',
        'services.gohighlevel.location_id' => 'loc-1',
        'services.gohighlevel.base_url' => 'https://ghl.test',
    ]);
});

function omzetcheckPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jan',
        'email' => 'jan@voorbeeld.be',
        'sector' => 'Bouw & renovatie',
        'visitors' => 500,
        'leads' => 5,
        'close' => 30,
        'value' => 2000,
        'target' => 3,
    ], $overrides);
}

it('rekent zoals de calculator in de browser', function () {
    $r = OmzetcheckController::calculate(500, 5, 30, 2000, 3);

    expect(round($r['current'], 2))->toBe(1.0)
        ->and(round($r['goal'], 2))->toBe(3.0)
        ->and(round($r['extra_leads'], 2))->toBe(10.0)
        ->and(round($r['yearly']))->toBe(72000.0);
});

it('verdubbelt de conversie als die al boven het doel ligt', function () {
    $r = OmzetcheckController::calculate(1000, 50, 25, 8000, 3);

    expect(round($r['goal'], 2))->toBe(10.0)
        ->and(round($r['extra_leads'], 2))->toBe(50.0);
});

it('bewaart de inzending en zet het contact met velden en daarna de starttag in GHL', function () {
    Http::fake([
        'ghl.test/contacts/upsert' => Http::response(['contact' => ['id' => 'c-1']]),
        'ghl.test/contacts/c-1/tags' => Http::response(['tags' => ['omzetcheck-ingevuld']]),
    ]);

    $this->postJson('/omzetcheck', omzetcheckPayload())->assertOk()->assertJson(['ok' => true]);

    $submission = FormSubmission::where('type', 'omzetcheck')->sole();
    expect($submission->data['email'])->toBe('jan@voorbeeld.be')
        ->and($submission->data['yearly'])->toBe(72000);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0])->values();
    expect($sent)->toHaveCount(2);

    /** @var Request $upsert */
    $upsert = $sent[0];
    expect($upsert->url())->toBe('https://ghl.test/contacts/upsert')
        ->and($upsert->hasHeader('Authorization', 'Bearer test-token'))->toBeTrue()
        ->and($upsert['locationId'])->toBe('loc-1')
        ->and($upsert['email'])->toBe('jan@voorbeeld.be')
        ->and($upsert['tags'])->toBe(['campagne-websites'])
        ->and(collect($upsert['customFields'])->pluck('field_value')->all())
        ->toBe(['€ 72.000', '10,0', 'Bouw & renovatie']);

    // De starttag komt pas ná de velden, zodat de eerste mail het bedrag kent.
    expect($sent[1]->url())->toBe('https://ghl.test/contacts/c-1/tags')
        ->and($sent[1]['tags'])->toBe(['omzetcheck-ingevuld']);
});

it('toont het resultaat ook als GHL niet bereikbaar is', function () {
    Http::fake(['ghl.test/*' => Http::response('boom', 500)]);

    $this->postJson('/omzetcheck', omzetcheckPayload())->assertOk();

    expect(FormSubmission::where('type', 'omzetcheck')->count())->toBe(1);
});

it('werkt zonder GHL-token en slaat de inzending toch op', function () {
    config(['services.gohighlevel.api_token' => null]);
    Http::fake();

    $this->postJson('/omzetcheck', omzetcheckPayload())->assertOk();

    Http::assertNothingSent();
    expect(FormSubmission::where('type', 'omzetcheck')->count())->toBe(1);
});

it('negeert een bot die het verborgen veld invult', function () {
    Http::fake();

    $this->postJson('/omzetcheck', omzetcheckPayload(['website' => 'http://spam.test']))->assertOk();

    Http::assertNothingSent();
    expect(FormSubmission::count())->toBe(0);
});

it('vraagt een voornaam en een geldig e-mailadres', function () {
    Http::fake();

    $this->postJson('/omzetcheck', omzetcheckPayload(['first_name' => '', 'email' => 'geen-mail']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'email']);

    Http::assertNothingSent();
});

it('toont eerst het formulier en pas na invullen het resultaat', function () {
    $html = Blade::render('<x-site.sections.conversion-calculator :content="$content" />', [
        'content' => ['heading' => 'Wat kost je website je eigenlijk?', 'sectors' => []],
    ]);

    expect($html)->toContain('Toon mijn resultaat')
        ->toContain('x-show="unlocked"')
        ->toContain('omzetcheck');
});

it('slaat de gate over als hij in de admin uit staat', function () {
    $html = Blade::render('<x-site.sections.conversion-calculator :content="$content" />', [
        'content' => ['heading' => 'Wat kost je website je eigenlijk?', 'sectors' => [], 'require_contact' => false],
    ]);

    expect($html)->not->toContain('Toon mijn resultaat');
});

it('vult de GHL-agenda voor met de query-parameters van de pagina', function () {
    request()->query->add(['first_name' => 'Jan', 'email' => 'jan@voorbeeld.be']);

    $html = Blade::render('<x-site.sections.booking :content="$content" />', [
        'content' => ['provider' => 'ghl', 'url' => 'https://api.leadconnectorhq.com/widget/booking/abc', 'height' => 700],
    ]);

    expect($html)->toContain('widget/booking/abc?first_name=Jan&amp;email=jan%40voorbeeld.be');
});

it('laat de publieke leadvorm en de GHL-webhook buiten de CSRF-controle', function () {
    // Laravel 13 zet PreventRequestForgery in de web-groep; ValidateCsrfToken
    // uitsluiten werkt niet (live gaf 419). Tests slaan CSRF over, dus check de route.
    foreach (['omzetcheck.store', 'webhooks.ghl.quote-signed'] as $name) {
        expect(Illuminate\Support\Facades\Route::getRoutes()->getByName($name)->excludedMiddleware())
            ->toContain(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }
});
