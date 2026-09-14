<?php

namespace App\Http\Controllers;

use App\Filament\Pages\SearchConsole;
use App\Services\GoogleAnalyticsService;
use App\Services\GoogleSearchConsoleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * De OAuth-consent-flow voor de gedeelde Google-koppeling (Groei-meetlaag): de
 * beheerder geeft op zijn eigen Google-account toestemming, Google stuurt een
 * code terug, die we inwisselen voor een refresh token in Setting.
 *
 * Eén flow voor twee diensten: dezelfde toestemming dekt Search Console én
 * Analytics, en na afloop wijzen we van allebei meteen de juiste property aan.
 *
 * **De klasse- en routenaam blijven "gsc"** hoewel de flow breder is. De
 * omleidings-URI staat geregistreerd in Google Cloud en moet daar exact mee
 * overeenkomen; hem hernoemen breekt elke bestaande koppeling tot iemand het
 * daar handmatig aanpast. Die kosmetische winst is dat niet waard.
 *
 * Buiten Filament omdat Google naar een gewone GET-URL terugstuurt. De routes
 * staan onder `auth`; de panel-toegang wordt hier expliciet gecheckt.
 */
class SearchConsoleOAuthController extends Controller
{
    public function redirect(Request $request, GoogleSearchConsoleService $gsc): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! $gsc->canStartOAuth()) {
            return $this->back('Vul eerst de client-ID en het client-secret in en sla op.', false);
        }

        // CSRF-bescherming voor de callback: Google stuurt de state ongewijzigd terug.
        $state = Str::random(40);
        $request->session()->put('gsc_oauth_state', $state);

        return redirect()->away($gsc->authorizationUrl(self::redirectUri(), $state));
    }

    public function callback(Request $request, GoogleSearchConsoleService $gsc): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $expected = $request->session()->pull('gsc_oauth_state');

        if ($request->filled('error')) {
            return $this->back('Google gaf geen toestemming: '.$request->input('error'), false);
        }

        if (! $expected || $request->input('state') !== $expected) {
            return $this->back('De koppeling verliep niet correct (ongeldige state). Probeer opnieuw.', false);
        }

        if (! $request->filled('code')) {
            return $this->back('Google stuurde geen code terug. Probeer opnieuw.', false);
        }

        $result = $gsc->exchangeCodeForRefreshToken((string) $request->input('code'), self::redirectUri());

        if (! $result['ok']) {
            return $this->back($result['message'], false);
        }

        return $this->back(
            $result['message'].' '.$this->pickSite($gsc).' '.$this->pickProperty(),
            true
        );
    }

    /** De omleidings-URI die in Google Cloud geregistreerd moet staan. */
    public static function redirectUri(): string
    {
        return route('seo.gsc.oauth.callback');
    }

    /**
     * Meteen na het koppelen de juiste property aanwijzen, zodat de beheerder
     * in het normale geval niets meer hoeft in te vullen. Lukt dat niet
     * ondubbelzinnig, dan kiezen we bewust níets.
     */
    protected function pickSite(GoogleSearchConsoleService $gsc): string
    {
        if ($gsc->siteUrl !== '') {
            return 'De cijfers worden opgehaald voor '.$gsc->siteUrl.'.';
        }

        $domain = GoogleSearchConsoleService::defaultDomain();
        $pick = $gsc->autoSelectSite($domain);

        if ($pick['ok']) {
            return 'We volgen '.$pick['site'].' op.';
        }

        if ($pick['count'] === null) {
            return 'We konden je sites nog niet opvragen — staat de Search Console API aan? Kies op de pagina welke site we opvolgen.';
        }

        if ($pick['count'] === 0) {
            return 'Dit account beheert nog geen enkele site in Search Console — koppelde je met het juiste Google-account?';
        }

        return "Dit account beheert {$pick['count']} sites en geen enkele hoort bij {$domain}. Kies op de pagina welke site we opvolgen.";
    }

    /**
     * Meteen na het koppelen ook de Analytics-property aanwijzen.
     *
     * Faalt hier iets, dan is dat geen reden om de hele koppeling als mislukt
     * te melden: Search Console werkt dan al, en de property kan altijd nog met
     * de hand gekozen worden.
     */
    protected function pickProperty(): string
    {
        $ga = app(GoogleAnalyticsService::class);

        if (! $ga->hasGrantedScope(GoogleAnalyticsService::SCOPE)) {
            return 'Analytics is niet mee gekoppeld — Google gaf dat recht niet. Zet de Analytics Data API aan in Google Cloud en koppel opnieuw.';
        }

        if ($ga->propertyId !== '') {
            return 'Analytics haalt de cijfers op voor property '.$ga->propertyId.'.';
        }

        $pick = $ga->autoSelectProperty(GoogleSearchConsoleService::defaultDomain());

        if ($pick['ok']) {
            return 'Analytics volgt "'.$pick['property']['name'].'" op.';
        }

        if ($pick['count'] === null) {
            return 'De Analytics-properties konden we nog niet opvragen — staat de Analytics Admin API aan? Kies er zelf een op de pagina.';
        }

        if ($pick['count'] === 0) {
            return 'Dit account beheert nog geen enkele Analytics-property.';
        }

        return "Dit account beheert {$pick['count']} Analytics-properties en geen enkele naam matcht je domein. Kies er zelf een op de pagina.";
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        $panel = Filament::getPanel('admin');

        abort_unless($user && $user->canAccessPanel($panel), 403);
    }

    protected function back(string $message, bool $ok): RedirectResponse
    {
        $notification = Notification::make()->title($ok ? 'Google gekoppeld' : 'Koppelen mislukt')->body($message);
        ($ok ? $notification->success() : $notification->danger())->send();

        return redirect(SearchConsole::getUrl());
    }
}
