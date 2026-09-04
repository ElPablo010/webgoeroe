<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legt bij het eerste GET-verzoek van een sessie de herkomst van de bezoeker
 * vast (first touch). Registreren in de `web`-middlewaregroep, ná
 * StartSession (bootstrap/app.php: ->withMiddleware(fn ($m) =>
 * $m->web(append: [CaptureFirstTouch::class]))).
 */
class CaptureFirstTouch
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')
            && $request->hasSession()
            && ! $request->is('livewire/*', 'admin/*', 'build/*', 'storage/*')
            && ! Attribution::isBot($request->userAgent())) {
            Attribution::capture($request);
        }

        return $next($request);
    }
}
