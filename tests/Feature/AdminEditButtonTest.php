<?php

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * De zwevende bewerk-knop op de publieke site: ingelogde beheerders springen
 * vanaf elke pagina rechtstreeks naar het juiste bewerkscherm (en via de
 * "Bekijken"-actie in de admin weer terug), bezoekers zien niets.
 */
function editButtonPage(): Page
{
    return Page::create([
        'title' => 'Home',
        'slug' => 'home',
        'is_homepage' => true,
        'published' => true,
    ]);
}

function editButtonPost(): Post
{
    return Post::create([
        'title' => 'Slimmer werken met AI',
        'slug' => 'slimmer-werken-met-ai',
        'body' => '<p>Inhoud van het artikel.</p>',
        'published' => true,
        'published_at' => now()->subDay(),
    ]);
}

function editButtonCase(): CaseStudy
{
    return CaseStudy::create([
        'title' => 'Bookingplatform',
        'slug' => 'bookingplatform',
        'client' => 'Voorbeeld',
        'published' => true,
        'content' => [
            'challenge' => ['body' => 'De uitdaging.'],
            'solution' => ['body' => 'De oplossing.'],
        ],
    ]);
}

it('shows an edit button linking to the page edit screen for a logged-in admin', function () {
    $page = editButtonPage();

    actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee("/admin/pages/{$page->id}/edit", escape: false);
});

it('shows an edit button linking to the post edit screen on a blog article', function () {
    $post = editButtonPost();

    actingAs(User::factory()->create())
        ->get("/blog/{$post->slug}")
        ->assertOk()
        ->assertSee("/admin/posts/{$post->id}/edit", escape: false);
});

it('shows an edit button linking to the case edit screen on a case page', function () {
    $case = editButtonCase();

    actingAs(User::factory()->create())
        ->get("/cases/{$case->slug}")
        ->assertOk()
        ->assertSee("/admin/cases/{$case->id}/edit", escape: false);
});

it('hides the edit button for guests', function () {
    editButtonPage();
    $post = editButtonPost();

    get('/')->assertOk()->assertDontSee('/admin/pages/', escape: false);
    get("/blog/{$post->slug}")->assertOk()->assertDontSee('/admin/posts/', escape: false);
});
