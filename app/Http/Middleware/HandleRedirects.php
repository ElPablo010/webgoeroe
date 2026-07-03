<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    /**
     * Cache-key voor de volledige redirect-map. Wordt geleegd bij elke
     * wijziging vanuit de Filament-pagina (zie Redirects::flushCache()).
     */
    public const CACHE_KEY = 'website_redirects';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = '/'.trim($request->path(), '/');

        // Cache een plain array (geen Eloquent-collectie): een geserialiseerd
        // Eloquent-model faalt bij unserialize() in een vers PHP-proces
        // ("incomplete object") en zou élke request — ook niet-redirects — 500'en.
        $redirects = Cache::remember(self::CACHE_KEY, 300, function () {
            return Redirect::all()
                ->mapWithKeys(fn (Redirect $r) => [
                    '/'.trim($r->from, '/') => ['to' => $r->to, 'status' => $r->status_code],
                ])
                ->all();
        });

        if (isset($redirects[$path])) {
            return redirect($redirects[$path]['to'], $redirects[$path]['status']);
        }

        return $next($request);
    }
}
