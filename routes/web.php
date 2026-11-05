<?php

use App\Http\Controllers\GoHighLevelWebhookController;
use App\Http\Controllers\OmzetcheckController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// GoHighLevel-workflow: offerte getekend → voorschotfactuur in Billit.
Route::post('/webhooks/ghl/offerte-getekend', [GoHighLevelWebhookController::class, 'quoteSigned'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:30,1')
    ->name('webhooks.ghl.quote-signed');

// GoHighLevel-workflow: offerte verstuurd → deal naar "Proposal Sent".
Route::post('/webhooks/ghl/offerte-verstuurd', [GoHighLevelWebhookController::class, 'quoteSent'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:30,1')
    ->name('webhooks.ghl.quote-sent');

// Omzetcheck (website-conversiecalculator): gate met voornaam + e-mail.
// Zonder CSRF-token (publieke leadvorm, pagina kan gecachet zijn); beschermd
// met honeypot en throttle.
Route::post('/omzetcheck', [OmzetcheckController::class, 'store'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:20,1')
    ->name('omzetcheck.store');

// Eigen kalender (GET /boeking/tijdstippen, POST /boeking) komt uit de core.

// Filament is het enige login-systeem; de korte /login redirect ernaartoe.
Route::redirect('/login', '/admin/login')->name('login');

// De homepage-pagina is ook op /home bereikbaar; één URL voor Google.
Route::permanentRedirect('/home', '/');

// /sitemap.xml, /robots.txt, /llms.txt en de catch-all paginarouter komen uit
// de package webgoeroe/core; die registreert ze ná deze routes (catch-all als
// allerlaatste). De modules cases en blog vullen de sitemap en llms.txt aan.

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

// Cases (/cases, /cases/{slug}) en blog (/blog, /blog/{slug}) komen uit de
// modules webgoeroe/cases en webgoeroe/blog.

// Google OAuth-routes (Search Console + Analytics) komen uit de package
// webgoeroe/seo-growth en laden vóór deze routes.
