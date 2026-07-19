<?php

use App\Models\CaseStudy;
use App\Models\User;

/** Een gepubliceerde case met volledig ingevulde content. */
function publishedCase(array $overrides = []): CaseStudy
{
    return CaseStudy::create(array_merge([
        'title' => 'Bookingplatform voor sauna',
        'slug' => 'bookingplatform-voor-sauna',
        'client' => 'Sauna Voorbeeld',
        'industry' => 'Wellness',
        'excerpt' => 'Van telefoon naar online boeken.',
        'published' => true,
        'content' => [
            'challenge' => ['body' => "Alle boekingen liepen via de telefoon.\nDat kostte tijd."],
            'goals' => [['text' => 'Online boeken mogelijk maken']],
            'approach' => ['steps' => [['title' => 'Analyse', 'body' => 'Processen in kaart gebracht.']]],
            'solution' => ['body' => 'Een boekingsplatform op maat.'],
            'results' => ['intro' => 'Binnen drie maanden:', 'metrics' => [['label' => 'Boekingen', 'value' => '+65%']]],
            'testimonial' => ['quote' => 'Top werk.', 'name' => 'Jan', 'role' => 'Zaakvoerder'],
            'reflection' => ['body' => 'Klein beginnen werkte.', 'website_url' => 'https://voorbeeld.be'],
            'cta' => ['title' => 'Ook zoiets?', 'button_label' => 'Plan gesprek', 'button_url' => '/contact'],
        ],
    ], $overrides));
}

it('renders a case detail page for a guest with 200', function () {
    $case = publishedCase();

    $this->get("/cases/{$case->slug}")
        ->assertOk()
        ->assertSee($case->title)
        ->assertSee('Van telefoon naar online boeken');
});

it('renders a case detail page for a logged-in admin (edit link resolves)', function () {
    // Regressietest: bij de rename naar /admin/cases veranderde de Filament-
    // routenaam mee. De @auth-edit-link verwees nog naar de oude naam en gaf 500.
    $case = publishedCase();

    $this->actingAs(User::factory()->create())
        ->get("/cases/{$case->slug}")
        ->assertOk()
        ->assertSee(route('filament.admin.resources.cases.edit', ['record' => $case, 'tab' => 'sections']));
});

it('renders a case without a cover image', function () {
    $case = publishedCase(['cover_url' => null]);

    $this->actingAs(User::factory()->create())
        ->get("/cases/{$case->slug}")
        ->assertOk();
});

it('renders a case with only the required content fields', function () {
    $case = publishedCase([
        'slug' => 'minimale-case',
        'content' => [
            'challenge' => ['body' => 'Een probleem.'],
            'solution' => ['body' => 'Een oplossing.'],
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->get("/cases/{$case->slug}")
        ->assertOk()
        ->assertSee('Een oplossing');
});

it('404s an unpublished case for a guest', function () {
    $case = publishedCase(['slug' => 'concept-case', 'published' => false]);

    $this->get("/cases/{$case->slug}")->assertNotFound();
});
