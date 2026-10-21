<?php

use App\Livewire\Forms\SpeedToLeadStartForm;
use App\Models\FormSubmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Startformulier van de landingspagina "Speed to lead elektriciens": inzending
 * op de site (lead) en contact met tag in GoHighLevel, zonder dat een GHL-fout
 * de bezoeker blokkeert.
 */
beforeEach(function () {
    config([
        'services.gohighlevel.api_token' => 'test-token',
        'services.gohighlevel.location_id' => 'loc-1',
        'services.gohighlevel.base_url' => 'https://ghl.test',
    ]);
});

function speedToLeadForm(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(SpeedToLeadStartForm::class)
        ->set('name', 'Jan Janssen')
        ->set('company', 'Elektro Janssen')
        ->set('email', 'jan@elektrojanssen.be')
        ->set('phone', '+32 478 00 00 00');
}

it('bewaart de inzending en zet het contact met tag in GoHighLevel', function () {
    Http::fake(['ghl.test/*' => Http::response(['contact' => ['id' => 'c-1']])]);

    speedToLeadForm()->call('submit')->assertSet('sent', true);

    $submission = FormSubmission::where('type', 'speed_to_lead_start')->sole();
    expect($submission->data['company'])->toBe('Elektro Janssen');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/contacts/upsert')
        && $r['firstName'] === 'Jan'
        && $r['lastName'] === 'Janssen'
        && $r['companyName'] === 'Elektro Janssen'
        && $r['phone'] === '+32 478 00 00 00'
        && $r['tags'] === ['lp-elektriciens-start']);
});

it('vraagt naam, bedrijf, e-mail en gsm', function () {
    Http::fake();

    Livewire::test(SpeedToLeadStartForm::class)
        ->call('submit')
        ->assertHasErrors(['name' => 'required', 'company' => 'required', 'email' => 'required', 'phone' => 'required']);

    Http::assertNothingSent();
});

it('toont de bedankboodschap ook als GoHighLevel faalt', function () {
    Http::fake(['ghl.test/*' => Http::response('fout', 500)]);

    speedToLeadForm()->call('submit')->assertSet('sent', true);

    expect(FormSubmission::where('type', 'speed_to_lead_start')->count())->toBe(1);
});
