<?php

use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Filament is het enige login-systeem; de korte /login redirect ernaartoe.
Route::redirect('/login', '/admin/login')->name('login');

// /sitemap.xml, /robots.txt, /llms.txt en de catch-all paginarouter komen uit
// de package webgoeroe/core; die registreert ze ná deze routes (catch-all als
// allerlaatste). Cases en blog vullen de sitemap en llms.txt aan via
// App\Support\ContentSeo.

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

// Google OAuth-routes (Search Console + Analytics) komen uit de package
// webgoeroe/seo-growth en laden vóór deze routes.
