<?php

use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

// Filament is het enige login-systeem; de korte /login redirect ernaartoe.
Route::redirect('/login', '/admin/login')->name('login');

// SEO/GEO-assets — dynamisch zodat ze de live database + omgeving weerspiegelen.
// Vóór de catch-all geregistreerd.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('llms');

// Design-previews voor pagina's die nog niet via de Filament-builder bestaan.
// Bereikbaar voor ingelogde users als referentie naast de live versie.
// Conventie: resources/views/pages/previews/{slug}.blade.php
Route::middleware('auth')
    ->get('/design/{slug}', function (string $slug) {
        $view = "pages.previews.{$slug}";
        abort_unless(view()->exists($view), 404);

        return response()->view($view);
    })
    ->where('slug', '[a-z0-9-]+')
    ->name('design.preview');

// Cases — vóór de catch-all zodat /cases/(slug) niet door de pagina-router
// opgepakt wordt. Route-namen blijven "case-studies.*" (intern, niet
// zichtbaar) om churn in de rest van de codebase te vermijden.
Route::get('/cases', [CaseStudyController::class, 'index'])->name('case-studies.index');
Route::get('/cases/{slug}', [CaseStudyController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('case-studies.show');

// Blog — vóór de catch-all zodat /blog/(slug) niet door de pagina-router opgepakt wordt.
Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [PostController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.show');

// Catch-all paginarouter (homepage + alle slugs). Sluit admin/livewire/storage uit.
Route::get('/{slug?}', [PublicPageController::class, 'show'])
    ->where('slug', '^(?!admin|login|livewire|storage|_debugbar|design).*$')
    ->name('page.show');
