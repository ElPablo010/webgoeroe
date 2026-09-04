<?php

use App\Livewire\Forms\ContactForm;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Support\Attribution;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * Groei-meetlaag: de herkomst van een bezoeker wordt bij zijn eerste bezoek
 * vastgelegd (first touch) en elke formulierinzending wordt automatisch een
 * lead mét die herkomst — zonder dat het formulier daar zelf iets voor moet
 * doen. Bots krijgen geen herkomst, en het contactformulier blijft werken
 * ongeacht wat de lead-registratie doet.
 */
function leadHomepage(): Page
{
    return Page::create([
        'title' => 'Home',
        'slug' => 'home',
        'is_homepage' => true,
        'published' => true,
    ]);
}

it('legt de herkomst van een bezoeker vast bij het eerste bezoek', function () {
    leadHomepage();

    get('/?utm_source=nieuwsbrief&utm_medium=email&utm_campaign=september', ['Referer' => 'https://www.google.be/'])
        ->assertOk()
        ->assertSessionHas(Attribution::SESSION_KEY, fn (array $touch) => $touch['channel'] === Attribution::CHANNEL_EMAIL
            && $touch['landing_path'] === '/'
            && $touch['referrer_host'] === 'www.google.be'
            && $touch['utm_campaign'] === 'september');
});

it('overschrijft de first touch niet bij een volgend bezoek in dezelfde sessie', function () {
    leadHomepage();

    get('/', ['Referer' => 'https://www.google.be/'])->assertOk();
    get('/', ['Referer' => 'https://www.facebook.com/'])->assertOk();

    expect(session(Attribution::SESSION_KEY)['channel'])->toBe(Attribution::CHANNEL_ORGANIC);
});

it('geeft crawlers geen herkomst', function () {
    leadHomepage();

    get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
        ->assertOk()
        ->assertSessionMissing(Attribution::SESSION_KEY);
});

it('classificeert kanalen in de juiste volgorde', function () {
    // Betaald wint van organisch: een Ads-klik heeft óók google als referrer.
    expect(Attribution::classify('www.google.be', ['gclid' => 'x']))->toBe(Attribution::CHANNEL_ADS)
        ->and(Attribution::classify('www.google.be'))->toBe(Attribution::CHANNEL_ORGANIC)
        ->and(Attribution::classify('chatgpt.com'))->toBe(Attribution::CHANNEL_AI)
        ->and(Attribution::classify('l.instagram.com'))->toBe(Attribution::CHANNEL_SOCIAL)
        ->and(Attribution::classify('partner.example.com'))->toBe(Attribution::CHANNEL_REFERRAL)
        ->and(Attribution::classify(null, ['utm_medium' => 'newsletter']))->toBe(Attribution::CHANNEL_EMAIL)
        ->and(Attribution::classify(null))->toBe(Attribution::CHANNEL_DIRECT);
});

it('maakt van elke formulierinzending automatisch een lead met herkomst', function () {
    session([Attribution::SESSION_KEY => [
        'channel' => Attribution::CHANNEL_ORGANIC,
        'referrer_host' => 'www.google.be',
        'landing_path' => '/webdesign',
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
    ]]);

    Livewire::test(ContactForm::class)
        ->set('name', 'An Peeters')
        ->set('email', 'an@example.com')
        ->set('message', 'Ik wil graag een nieuwe website.')
        ->call('submit')
        ->assertSet('sent', true);

    $submission = FormSubmission::sole();
    $lead = Lead::sole();

    expect($lead->lead_type)->toBe('contact')
        ->and($lead->source_type)->toBe($submission->getMorphClass())
        ->and($lead->source_id)->toBe($submission->id)
        ->and($lead->channel)->toBe(Attribution::CHANNEL_ORGANIC)
        ->and($lead->landing_path)->toBe('/webdesign')
        ->and($lead->typeLabel())->toBe('Contactvraag');
});

it('registreert een lead ook zonder herkomst-snapshot', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'Jos')
        ->set('email', 'jos@example.com')
        ->set('message', 'Vraagje.')
        ->call('submit');

    expect(Lead::count())->toBe(1)
        ->and(Lead::sole()->channel)->toBeNull();
});

it('negeert het honeypot-formulier volledig', function () {
    Livewire::test(ContactForm::class)
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'spam')
        ->set('website', 'http://spam.example')
        ->call('submit');

    expect(FormSubmission::count())->toBe(0)
        ->and(Lead::count())->toBe(0);
});
