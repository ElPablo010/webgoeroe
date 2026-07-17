<?php

use App\Enums\UserRole;
use App\Mcp\Servers\CmsServer;
use App\Mcp\Tools\CreateCase;
use App\Mcp\Tools\ListCases;
use App\Mcp\Tools\PublishCase;
use App\Mcp\Tools\UnpublishCase;
use App\Mcp\Tools\UpdateCase;
use App\Mcp\Tools\UploadMediaFromUrl;
use App\Models\CaseStudy;
use App\Models\User;
use App\Models\WebsiteMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

/** Minimale geldige content: enkel de twee verplichte velden. */
function minimalCaseContent(): array
{
    return [
        'challenge' => ['body' => 'De klant had geen online boekingen.'],
        'solution' => ['body' => 'We bouwden een boekingsplatform op maat.'],
    ];
}

it('creates a draft case with the full content structure', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, [
            'title' => 'Bookingplatform voor sauna',
            'client' => 'Sauna Voorbeeld',
            'industry' => 'Wellness',
            'excerpt' => 'Van telefoon naar online boeken.',
            'tags' => ['booking', 'maatwerk'],
            'content' => [
                'challenge' => ['body' => 'Alle boekingen liepen via de telefoon.'],
                'goals' => [['text' => 'Online boeken mogelijk maken']],
                'approach' => ['steps' => [['title' => 'Analyse', 'body' => 'Processen in kaart gebracht.']]],
                'solution' => ['body' => 'Een boekingsplatform op maat.'],
                'results' => [
                    'intro' => 'Binnen drie maanden:',
                    'metrics' => [['label' => 'Online boekingen', 'value' => '+65%']],
                ],
                'testimonial' => ['quote' => 'Top werk.', 'name' => 'Jan', 'role' => 'Zaakvoerder'],
                'reflection' => ['body' => 'Klein beginnen werkte.', 'website_url' => 'https://voorbeeld.be'],
                'cta' => ['title' => 'Ook zoiets?', 'button_label' => 'Plan gesprek', 'button_url' => '/contact'],
            ],
        ])
        ->assertOk();

    $case = CaseStudy::firstWhere('title', 'Bookingplatform voor sauna');

    expect($case)->not->toBeNull()
        ->and($case->published)->toBeFalse()
        ->and($case->slug)->toBe('bookingplatform-voor-sauna')
        ->and($case->client)->toBe('Sauna Voorbeeld')
        ->and($case->tags)->toBe(['booking', 'maatwerk'])
        // content wordt als gestructureerde array bewaard, precies zoals de admin-form 'm leest
        ->and($case->content['challenge']['body'])->toBe('Alle boekingen liepen via de telefoon.')
        ->and($case->content['goals'][0]['text'])->toBe('Online boeken mogelijk maken')
        ->and($case->content['approach']['steps'][0]['title'])->toBe('Analyse')
        ->and($case->content['results']['metrics'][0]['value'])->toBe('+65%')
        ->and($case->content['cta']['button_url'])->toBe('/contact');
});

it('publishes immediately when published is true and returns the public url', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, [
            'title' => 'Live case',
            'content' => minimalCaseContent(),
            'published' => true,
        ])
        ->assertOk()
        ->assertSee('live-case');

    expect(CaseStudy::firstWhere('title', 'Live case')->published)->toBeTrue();
});

it('requires the mandatory content fields', function (array $content) {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, ['title' => 'Onvolledig', 'content' => $content])
        ->assertHasErrors();

    expect(CaseStudy::count())->toBe(0);
})->with([
    'challenge ontbreekt' => [['solution' => ['body' => 'Iets gebouwd.']]],
    'solution ontbreekt' => [['challenge' => ['body' => 'Een probleem.']]],
    'leeg' => [[]],
]);

it('rejects a case without content entirely', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, ['title' => 'Geen content'])
        ->assertHasErrors();
});

it('generates a unique slug when the title collides', function () {
    CaseStudy::create(['title' => 'Dubbel', 'slug' => 'dubbel', 'content' => minimalCaseContent()]);

    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, ['title' => 'Dubbel', 'content' => minimalCaseContent()])
        ->assertOk();

    expect(CaseStudy::where('slug', 'dubbel-2')->exists())->toBeTrue();
});

it('updates fields without touching content when content is omitted', function () {
    $case = CaseStudy::create([
        'title' => 'Origineel',
        'slug' => 'origineel',
        'content' => minimalCaseContent(),
        'excerpt' => 'oude teaser',
    ]);

    CmsServer::actingAs($this->admin)
        ->tool(UpdateCase::class, ['id' => $case->id, 'title' => 'Bijgewerkt'])
        ->assertOk();

    $case->refresh();

    expect($case->title)->toBe('Bijgewerkt')
        ->and($case->excerpt)->toBe('oude teaser')
        ->and($case->content['challenge']['body'])->toBe('De klant had geen online boekingen.');
});

it('replaces the whole content block when content is given', function () {
    $case = CaseStudy::create(['title' => 'Case', 'slug' => 'case', 'content' => minimalCaseContent()]);

    CmsServer::actingAs($this->admin)
        ->tool(UpdateCase::class, [
            'id' => $case->id,
            'content' => [
                'challenge' => ['body' => 'Nieuw probleem.'],
                'solution' => ['body' => 'Nieuwe oplossing.'],
            ],
        ])
        ->assertOk();

    expect($case->fresh()->content['challenge']['body'])->toBe('Nieuw probleem.');
});

it('validates content on update too', function () {
    $case = CaseStudy::create(['title' => 'Case', 'slug' => 'case', 'content' => minimalCaseContent()]);

    CmsServer::actingAs($this->admin)
        ->tool(UpdateCase::class, [
            'id' => $case->id,
            'content' => ['challenge' => ['body' => 'Alleen dit.']], // solution ontbreekt
        ])
        ->assertHasErrors();
});

it('publishes and unpublishes an existing case', function () {
    $case = CaseStudy::create([
        'title' => 'Schakel', 'slug' => 'schakel', 'content' => minimalCaseContent(), 'published' => false,
    ]);

    CmsServer::actingAs($this->admin)->tool(PublishCase::class, ['id' => $case->id])->assertOk();
    expect($case->fresh()->published)->toBeTrue();

    CmsServer::actingAs($this->admin)->tool(UnpublishCase::class, ['id' => $case->id])->assertOk();
    expect($case->fresh()->published)->toBeFalse();
});

it('returns an error for an unknown case id', function () {
    CmsServer::actingAs($this->admin)
        ->tool(PublishCase::class, ['id' => 99999])
        ->assertHasErrors();
});

it('lists cases filtered by status and searchable by client', function () {
    CaseStudy::create(['title' => 'Live', 'slug' => 'live', 'content' => minimalCaseContent(), 'published' => true, 'client' => 'Acme']);
    CaseStudy::create(['title' => 'Concept', 'slug' => 'concept', 'content' => minimalCaseContent(), 'published' => false]);

    CmsServer::actingAs($this->admin)
        ->tool(ListCases::class, ['status' => 'draft'])
        ->assertOk()
        ->assertSee('Concept')
        ->assertDontSee('"title":"Live"');

    CmsServer::actingAs($this->admin)
        ->tool(ListCases::class, ['search' => 'Acme'])
        ->assertOk()
        ->assertSee('Live');
});

it('accepts a library media path as cover_url on a case', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, [
            'title' => 'Met cover',
            'content' => minimalCaseContent(),
            'cover_url' => '/storage/website-media/01ABC.webp',
        ])
        ->assertOk();

    expect(CaseStudy::firstWhere('title', 'Met cover')->cover_url)
        ->toBe('/storage/website-media/01ABC.webp');
});

it('uploads an image and uses it as both cover and solution screenshot', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response(
        file_get_contents(($f = UploadedFile::fake()->image('shot.jpg', 800, 600))->getRealPath()),
        200,
        ['Content-Type' => 'image/jpeg'],
    )]);

    // 1. Afbeelding binnenhalen via de gedeelde tool.
    CmsServer::actingAs($this->admin)
        ->tool(UploadMediaFromUrl::class, ['url' => 'https://93.184.216.34/shot.jpg'])
        ->assertOk();

    $mediaUrl = WebsiteMedia::latest('id')->first()->url;

    // 2. Diezelfde URL gebruiken als cover én binnen content.
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, [
            'title' => 'Case met beeld',
            'cover_url' => $mediaUrl,
            'cover_alt' => 'Screenshot van het platform',
            'content' => [
                'challenge' => ['body' => 'Een probleem.'],
                'solution' => [
                    'body' => 'Een oplossing.',
                    'image_url' => $mediaUrl,
                    'image_alt' => 'Het eindresultaat',
                ],
            ],
        ])
        ->assertOk();

    $case = CaseStudy::firstWhere('title', 'Case met beeld');

    expect($case->cover_url)->toBe($mediaUrl)
        ->and($case->content['solution']['image_url'])->toBe($mediaUrl)
        ->and($mediaUrl)->toStartWith('/storage/website-media/');
});

it('rejects a bogus media url inside case content', function () {
    CmsServer::actingAs($this->admin)
        ->tool(CreateCase::class, [
            'title' => 'Kapot beeld',
            'content' => [
                'challenge' => ['body' => 'Een probleem.'],
                'solution' => ['body' => 'Een oplossing.', 'image_url' => 'javascript:alert(1)'],
            ],
        ])
        ->assertHasErrors();

    expect(CaseStudy::count())->toBe(0);
});
