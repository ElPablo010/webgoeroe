<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Integrations\GoHighLevel\SubmissionSync;
use Webgoeroe\Core\Support\FollowUp;
use Webgoeroe\Core\Support\Modules;
use App\Livewire\LeadCheckForm;
use App\Support\LeadCheck;
use App\Support\LeadCheckExtensions;

// Helpers van de core (coreAdmin, corePage, ...).
require_once dirname(__DIR__, 2).'/vendor/webgoeroe/core/tests/helpers.php';

/**
 * Blok "Lekkencheck" (tot core 0.25 in de core, nu in deze site): één rekenmodel
 * met rollen per vraag, de aanvraag (type lead_check) met antwoorden en resultaat,
 * en GHL per vraag en per lek.
 */
function leadCheckContent(array $overrides = []): array
{
    return [
        'heading' => 'Hoeveel klanten loop je mis?',
        'phone_required' => true,
        'low_factor' => 50,
        'price' => 297,
        'price_text' => 'Speed to Lead kost €297 per maand: {prijs_klanten} klant.',
        'questions' => [
            ['key' => 'opdrachtwaarde', 'question' => 'Wat is een opdracht waard?', 'role' => 'value', 'input' => 'number', 'ghl_field' => 'F_WAARDE'],
            ['key' => 'aanvragen', 'question' => 'Hoeveel aanvragen per maand?', 'role' => 'volume', 'input' => 'choice', 'period' => 'month', 'options' => [
                ['label' => '0-5', 'value' => 3], ['label' => '5-15', 'value' => 10], ['label' => '15+', 'value' => 22],
            ]],
            ['key' => 'slotratio', 'question' => 'Van 10 offertes, hoeveel worden een opdracht?', 'role' => 'close_rate', 'options' => [
                ['label' => '1-2', 'value' => 15], ['label' => '3-4', 'value' => 35], ['label' => '5+', 'value' => 55],
            ]],
            ['key' => 'vindbaarheid', 'question' => 'Waar sta je in Google?', 'role' => 'leak_leads', 'leak_label' => 'Vindbaarheid', 'leak_tag' => 'lek-vindbaarheid', 'options' => [
                ['label' => 'Top 3', 'value' => 0], ['label' => 'Pagina 1', 'value' => 25], ['label' => 'Pagina 2', 'value' => 50],
            ]],
            ['key' => 'conversie', 'question' => 'Hoe vraagt een bezoeker een offerte?', 'role' => 'leak_leads', 'leak_label' => 'Conversie', 'options' => [
                ['label' => 'Knop op elke pagina', 'value' => 0], ['label' => 'Contactpagina', 'value' => 20], ['label' => 'Enkel telefoon', 'value' => 40],
            ]],
            ['key' => 'reactie', 'question' => 'Hoe snel reageer je?', 'role' => 'leak_clients', 'leak_label' => 'Reactiesnelheid', 'leak_tag' => 'lek-reactie', 'advice' => 'Wie traag reageert, verliest de klant.', 'options' => [
                ['label' => 'Binnen het uur', 'value' => 0], ['label' => 'Dezelfde dag', 'value' => 15], ['label' => 'Later', 'value' => 30],
            ]],
            ['key' => 'offerte', 'question' => 'Hoe snel is je offerte er?', 'role' => 'leak_clients', 'leak_label' => 'Offertesnelheid', 'options' => [
                ['label' => '24u', 'value' => 0], ['label' => '2-5 dagen', 'value' => 10], ['label' => 'Week', 'value' => 25],
            ]],
            ['key' => 'opvolging', 'question' => 'Volg je op?', 'role' => 'leak_clients', 'leak_label' => 'Opvolging', 'leak_tag' => 'lek-opvolging', 'ghl_field' => 'F_OPVOLGING', 'options' => [
                ['label' => '2x', 'value' => 0], ['label' => 'Soms', 'value' => 15], ['label' => 'Nooit', 'value' => 30],
            ]],
        ],
        'ghl_result' => ['result_year' => 'F_JAAR', 'top_leak' => 'F_LEK'],
        ...$overrides,
    ];
}

/** Het rekenvoorbeeld uit het doc "Omzetlek-check". */
function leadCheckAnswers(): array
{
    return ['opdrachtwaarde' => 1500, 'aanvragen' => 1, 'slotratio' => 1, 'vindbaarheid' => 1, 'conversie' => 1, 'reactie' => 1, 'offerte' => 1, 'opvolging' => 1];
}

it('rekent het voorbeeld uit het doc na', function () {
    $result = LeadCheck::calculate(leadCheckContent(), leadCheckAnswers());

    // O = 10 × 35% × 1.500 = 5.250; Cpot = 0,35 / (0,85 × 0,9 × 0,85) ≈ 53,8%;
    // V ≈ 2.820 + 3.630 = 6.450 → plafond op O: hoog 5.250, laag 3.225.
    expect($result['revenue'])->toBe(5250.0)
        ->and(round($result['monthly']['high']))->toBe(5250.0)
        ->and(round($result['monthly']['low']))->toEqualWithDelta(3226, 5)
        ->and(round($result['yearly']['high']))->toBe(63000.0)
        ->and($result['score'])->toBe(['ok' => 0, 'total' => 5])
        ->and($result['top_leak'])->toBe('Vindbaarheid')
        ->and($result['answers']['aanvragen'])->toBe('5-15')
        ->and($result['price_clients'])->toBe(0.2);

    $statuses = collect($result['leaks'])->pluck('status', 'key')->all();
    expect($statuses)->toBe(['vindbaarheid' => 'orange', 'conversie' => 'orange', 'reactie' => 'orange', 'offerte' => 'orange', 'opvolging' => 'orange']);
});

it('geeft groen zonder verlies, rood bij de duurste optie en nooit meer dan de omzet', function () {
    $clean = LeadCheck::calculate(leadCheckContent(), [...leadCheckAnswers(), 'vindbaarheid' => 0, 'conversie' => 0, 'reactie' => 0, 'offerte' => 0, 'opvolging' => 0]);
    expect($clean['monthly'])->toBe(['low' => 0.0, 'high' => 0.0])
        ->and($clean['score'])->toBe(['ok' => 5, 'total' => 5])
        ->and($clean['top_leak'])->toBeNull();

    $worst = LeadCheck::calculate(leadCheckContent(), [...leadCheckAnswers(), 'vindbaarheid' => 2, 'conversie' => 2, 'reactie' => 2, 'offerte' => 2, 'opvolging' => 2]);
    expect(collect($worst['leaks'])->pluck('status')->unique()->all())->toBe(['red'])
        ->and($worst['monthly']['high'])->toBeLessThanOrEqual($worst['revenue'])
        ->and($worst['monthly']['low'])->toBeLessThanOrEqual($worst['monthly']['high']);
});

it('gebruikt vaste waarden voor wat niet gevraagd wordt en rekent per week om', function () {
    $content = [
        'default_value' => 800,
        'default_close_rate' => 40,
        'questions' => [
            ['key' => 'oproepen', 'question' => 'Oproepen per week?', 'role' => 'volume', 'period' => 'week', 'options' => [['label' => '10', 'value' => 10]]],
            ['key' => 'gemist', 'question' => 'Hoeveel neem je niet op?', 'role' => 'leak_leads', 'options' => [['label' => 'Bijna nooit', 'value' => 0], ['label' => '1 op 5', 'value' => 20]]],
        ],
    ];

    $result = LeadCheck::calculate($content, ['oproepen' => 0, 'gemist' => 1]);

    // A = 43,3; gemist 8,66 aanvragen × 40% × 800 = 2.771 per maand.
    expect($result['missed_requests'])->toEqualWithDelta(8.66, 0.01)
        ->and($result['monthly']['high'])->toEqualWithDelta(2771.2, 0.5)
        ->and($result['value'])->toBe(800.0);
});

it('slaat vragen met een gereserveerde sleutel over', function () {
    $questions = LeadCheck::questions(['questions' => [
        ['key' => 'email', 'question' => 'Fout', 'role' => 'info', 'options' => [['label' => 'a', 'value' => 0]]],
        ['key' => 'goed', 'question' => 'Goed', 'role' => 'info', 'options' => [['label' => 'a', 'value' => 0]]],
    ]]);

    expect(array_column($questions, 'key'))->toBe(['goed']);
});

it('bewaart de aanvraag, toont het resultaat en stuurt velden en lek-tags naar GHL', function () {
    config(['services.gohighlevel.api_token' => 'test-token', 'services.gohighlevel.location_id' => 'LOC1', 'services.gohighlevel.base_url' => 'https://ghl.test']);
    Modules::save([Modules::GHL => true]);
    Core::model('setting')::set(SubmissionSync::SETTING, ['lead_check' => ['enabled' => true]]);
    Http::fake([
        'ghl.test/contacts/upsert' => Http::response(['contact' => ['id' => 'C1']]),
        'ghl.test/contacts/C1/tags' => Http::response(['tags' => []]),
    ]);

    $page = Core::model('page')::create(['title' => 'Lekkencheck', 'slug' => 'lekkencheck', 'locale' => 'nl', 'published' => true]);
    $section = $page->sections()->create([
        'section_type' => 'lead_check',
        'sort_order' => 1,
        'content' => leadCheckContent(['ghl' => ['tags' => ['lekkencheck-ingevuld'], 'source' => 'Meta – Lekkencheck']]),
    ]);

    $this->get('/lekkencheck')->assertOk()->assertSee('Hoeveel klanten loop je mis?')->assertSee('Wat is een opdracht waard?');

    $this->travel(-1)->minutes();
    $form = Livewire::test(LeadCheckForm::class, ['sectionId' => $section->id]);
    $this->travelBack();

    $form->set('first_name', 'Jan')->set('email', 'jan@elektro.be')->set('phone', '0470 12 34 56')
        ->call('submit', leadCheckAnswers())
        ->assertHasNoErrors()
        ->assertReturned(true)
        ->assertSee('Jan, je laat naar schatting')
        ->assertSee('Wie traag reageert, verliest de klant.')
        ->assertDispatched('booking-prefill');

    $submission = Core::model('form_submission')::sole();
    expect($submission->type)->toBe('lead_check')
        ->and($submission->page_section_id)->toBe($section->id)
        ->and($submission->data['aanvragen'])->toBe('5-15')
        ->and($submission->data['top_leak'])->toBe('Vindbaarheid')
        ->and($submission->data)->not->toHaveKey('top_leak_advice')
        ->and($submission->data['leak_tags'])->toBe(['lek-vindbaarheid', 'lek-reactie', 'lek-opvolging'])
        ->and($submission->data['result_year'])->toBe('€ 38.700 – € 63.000');

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/contacts/upsert')) {
            return false;
        }

        $fields = collect($request['customFields'])->pluck('field_value', 'id')->sortKeys()->all();

        return $request['source'] === 'Meta – Lekkencheck'
            && $request['phone'] === '0470 12 34 56'
            && $fields === ['F_JAAR' => '€ 38.700 – € 63.000', 'F_LEK' => 'Vindbaarheid', 'F_OPVOLGING' => 'Soms', 'F_WAARDE' => '€ 1.500'];
    });
    Http::assertSent(fn ($r): bool => str_ends_with($r->url(), '/contacts/C1/tags')
        && $r['tags'] === ['lekkencheck-ingevuld', 'lek-vindbaarheid', 'lek-reactie', 'lek-opvolging']);
});

it('vraagt alle antwoorden en de gsm als die verplicht is', function () {
    $page = Core::model('page')::create(['title' => 'Check', 'slug' => 'check', 'locale' => 'nl', 'published' => true]);
    $section = $page->sections()->create(['section_type' => 'lead_check', 'sort_order' => 1, 'content' => leadCheckContent()]);

    $this->travel(-1)->minutes();
    $form = Livewire::test(LeadCheckForm::class, ['sectionId' => $section->id]);
    $this->travelBack();

    $form->set('first_name', 'Jan')->set('email', 'jan@elektro.be')
        ->call('submit', leadCheckAnswers())
        ->assertHasErrors(['phone']);

    $form->set('phone', '0470123456')
        ->call('submit', ['opdrachtwaarde' => 1500])
        ->assertHasErrors(['answers'])
        ->assertReturned(false);

    expect(Core::model('form_submission')::count())->toBe(0);
});

it('toont het blok in de builder en beschrijft het voor de AI', function () {
    expect(Core::blocks()->has('lead_check'))->toBeTrue()
        ->and(Core::blocks()->ghlBlocks())->toContain('lead_check')
        ->and(Core::blocks()->submissionType('lead_check', []))->toBe('lead_check')
        ->and(Core::model('form_submission')::typeLabels()['lead_check'] ?? null)->toBe('Lekkencheck')
        ->and(Core::blocks()->describe('lead_check'))->not->toBeNull();
});

it('registreert de haakjes, de labels en het Livewire-formulier', function () {
    expect(LeadCheck::hooks())->toBeInstanceOf(LeadCheckExtensions::class)
        ->and(LeadCheck::hooks())->toBe(LeadCheck::hooks())
        ->and(Core::model('form_submission')::fieldLabel('result_year'))->toBe('Bedrag per jaar')
        ->and(Core::model('form_submission')::fieldLabel('leak_tags'))->toBe('Lek-tags');

    $section = corePage([], ['lead_check' => leadCheckContent()])->sections()->first();

    Livewire::test(LeadCheck::COMPONENT, ['sectionId' => $section->id])->assertOk()->assertSet('sectionId', $section->id);
});

it('toont het type Lekkencheck bij Opvolging enkel als het blok op de site staat', function () {
    config(['core.form_submissions.model' => null]);

    expect(FollowUp::types())->not->toHaveKey('lead_check');

    corePage([], ['lead_check' => leadCheckContent()]);

    expect(FollowUp::types())->toHaveKey('lead_check');
});

it('houdt het verlies onder het plafond als % van de omzet', function () {
    $result = LeadCheck::calculate(leadCheckContent(['max_loss' => 30]), leadCheckAnswers());

    // hoog = 30% van 5.250; laag = hoog × (1 + 50%) / 2.
    expect($result['monthly']['high'])->toBe(1575.0)
        ->and($result['monthly']['low'])->toBe(1181.25);
});

it('opent en bewaart een pagina met een lekkencheck in de editor', function () {
    $this->actingAs(coreAdmin());

    $page = corePage(['title' => 'Lekkencheck'], ['lead_check' => leadCheckContent()]);

    Livewire::test(\Webgoeroe\Core\Filament\Resources\Pages\Pages\EditPage::class, ['record' => $page->getRouteKey()])
        ->assertOk()
        ->assertSee('Wat is een opdracht waard?')
        ->call('save')
        ->assertHasNoFormErrors();

    $content = $page->fresh()->sections()->first()->content;
    expect(LeadCheck::questions($content))->toHaveCount(8)
        ->and($content['questions'][3]['options'][2]['value'])->toEqual(50);
});
