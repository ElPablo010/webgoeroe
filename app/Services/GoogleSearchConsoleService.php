<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client voor de Google Search Console API (Search Analytics).
 *
 * Deel van de Groei-meetlaag: dit is het GEMETEN Google-verkeer (clicks,
 * vertoningen, CTR, positie) — in tegenstelling tot de DataForSEO-schatting.
 *
 * Authenticatie bij voorkeur via **OAuth** op het Google-account dat de
 * property al beheert (client-ID + secret uit Google Cloud, refresh token na
 * de consent-flow). Terugval: een **service account** (JSON-sleutel), maar
 * Google blokkeert het aanmaken van zulke sleutels standaard in organisaties.
 *
 * Waarom hier geen google/apiclient: die package sleept tientallen
 * afhankelijkheden mee terwijl we maar twee endpoints nodig hebben. Het
 * JWT-bearer-flow is met openssl_sign() een handvol regels.
 */
class GoogleSearchConsoleService
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const API_BASE = 'https://www.googleapis.com/webmasters/v3';
    protected const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /** Cache-sleutel voor het access token (geldig 1u bij Google). */
    protected const TOKEN_CACHE_KEY = 'gsc_access_token';

    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** De property zoals Search Console ze kent, bv. "sc-domain:bailandolatino.be". */
    public string $siteUrl;

    protected ?array $credentials = null;

    /** Hash van de sleutel: verandert de sleutel, dan vervalt het gecachete token vanzelf. */
    protected string $credentialsHash = '';

    protected ?string $clientId = null;
    protected ?string $clientSecret = null;
    protected ?string $refreshToken = null;

    public function __construct()
    {
        $this->siteUrl = trim((string) Setting::get('gsc_site_url', ''));

        // Voorkeursweg: OAuth met een refresh token op je eigen Google-account.
        $this->clientId = trim((string) Setting::get('gsc_oauth_client_id', '')) ?: null;
        $this->clientSecret = trim((string) Setting::get('gsc_oauth_client_secret', '')) ?: null;
        $this->refreshToken = trim((string) Setting::get('gsc_refresh_token', '')) ?: null;

        // Alternatief: een service account. Werkt alleen als je organisatie het
        // aanmaken van sleutels toelaat — Google blokkeert dat standaard met de
        // policy iam.disableServiceAccountKeyCreation.
        $raw = Setting::get('gsc_service_account_json');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded['client_email']) && !empty($decoded['private_key'])) {
                $this->credentials = $decoded;
                $this->credentialsHash = md5($raw);
            }
        }
    }

    public function isConfigured(): bool
    {
        return ($this->hasOAuth() || $this->credentials !== null) && $this->siteUrl !== '';
    }

    /** Is de OAuth-koppeling helemaal rond (client + toestemming gegeven)? */
    public function hasOAuth(): bool
    {
        return $this->clientId !== null && $this->clientSecret !== null && $this->refreshToken !== null;
    }

    /** Zijn de OAuth-gegevens ingevuld, zodat we de toestemming kunnen vragen? */
    public function canStartOAuth(): bool
    {
        return $this->clientId !== null && $this->clientSecret !== null;
    }

    /** Welke methode wordt gebruikt: 'oauth', 'service_account' of null. */
    public function authMethod(): ?string
    {
        if ($this->hasOAuth()) {
            return 'oauth';
        }

        return $this->credentials !== null ? 'service_account' : null;
    }

    /**
     * Het eigen domein waarvoor we de property zoeken: het SEO-doeldomein als
     * dat ingesteld is, anders de host uit APP_URL. Hoort bij de meetlaag, dus
     * bewust geen afhankelijkheid van DataForSeoService.
     */
    public static function defaultDomain(): string
    {
        $configured = trim((string) Setting::get('seo_target_domain', ''));
        if ($configured !== '') {
            return $configured;
        }

        return (string) preg_replace('#^www\.#', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /** Het e-mailadres dat de gebruiker in Search Console moet toevoegen. */
    public function serviceAccountEmail(): ?string
    {
        return $this->credentials['client_email'] ?? null;
    }

    /* ---------------------------------------------------------------------
     | OAuth — toestemming vragen op je eigen Google-account
     * ------------------------------------------------------------------- */

    /**
     * De URL waar de gebruiker naartoe gestuurd wordt om toestemming te geven.
     *
     * access_type=offline + prompt=consent zijn allebei nodig: zonder die twee
     * geeft Google enkel een access token van een uur en géén refresh token,
     * en dan valt de koppeling na een uur stil.
     */
    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Wissel de code uit de callback in voor een refresh token.
     *
     * @return array{ok:bool,message:string}
     */
    public function exchangeCodeForRefreshToken(string $code, string $redirectUri): array
    {
        if (!$this->canStartOAuth()) {
            return ['ok' => false, 'message' => 'Vul eerst de client-ID en het client-secret in.'];
        }

        try {
            $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (!$response->successful()) {
                Log::warning('Search Console: code inwisselen mislukt', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Google weigerde de koppeling: ' . ($response->json('error_description') ?? $response->json('error') ?? 'onbekende fout')
                        . '. Controleer of de omleidings-URI exact overeenkomt met wat je in Google Cloud invulde.',
                ];
            }

            $refreshToken = $response->json('refresh_token');
            if (!$refreshToken) {
                return [
                    'ok' => false,
                    'message' => 'Google gaf geen refresh token terug. Verbreek de koppeling in je Google-account onder '
                        . '"Apps van derden" en probeer opnieuw.',
                ];
            }

            Setting::set('gsc_refresh_token', $refreshToken);
            $this->refreshToken = $refreshToken;
            Cache::forget($this->tokenCacheKey());

            return ['ok' => true, 'message' => 'Search Console gekoppeld.'];
        } catch (\Throwable $e) {
            Log::error('Search Console: OAuth-fout', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Koppelen mislukt: ' . $e->getMessage()];
        }
    }

    /* ---------------------------------------------------------------------
     | Welke property volgen we op?
     * ------------------------------------------------------------------- */

    /** Leg de property vast waarop we voortaan de cijfers ophalen. */
    public function setSiteUrl(string $site): void
    {
        Setting::set('gsc_site_url', trim($site));
        $this->siteUrl = trim($site);
    }

    /**
     * Kies zelf de property die bij ons eigen domein hoort, zodat de gebruiker
     * na het koppelen niets meer hoeft in te vullen.
     *
     * Lukt dat niet ondubbelzinnig (het account beheert meerdere sites en geen
     * enkele matcht ons domein), dan kiezen we bewust níets: liever de
     * gebruiker laten aanwijzen dan stilzwijgend de cijfers van een andere
     * site binnentrekken.
     *
     * @return array{ok:bool,site:?string,count:?int}  count null als de lijst niet opgehaald raakte
     */
    public function autoSelectSite(string $ourDomain): array
    {
        $sites = $this->listSites();

        if ($sites === null) {
            return ['ok' => false, 'site' => null, 'count' => null];
        }

        if ($sites === []) {
            return ['ok' => false, 'site' => null, 'count' => 0];
        }

        // Eén property? Dan valt er niets te kiezen.
        $site = self::matchSiteForDomain($sites, $ourDomain)
            ?? (count($sites) === 1 ? $sites[0] : null);

        if ($site === null) {
            return ['ok' => false, 'site' => null, 'count' => count($sites)];
        }

        $this->setSiteUrl($site);

        return ['ok' => true, 'site' => $site, 'count' => count($sites)];
    }

    /**
     * Welke van deze properties gaat over $domain?
     *
     * Search Console kent dezelfde site op twee manieren, die naast elkaar
     * kunnen bestaan met verschillende cijfers:
     *   - domein-property  "sc-domain:jouwdomein.be"  → alle subdomeinen, www én
     *     niet-www, http én https
     *   - URL-voorvoegsel  "https://www.jouwdomein.be/" → exact dat voorvoegsel
     *
     * De domein-property krijgt voorrang omdat ze het volledigste beeld geeft.
     * Blijven er alleen voorvoegsels over, dan wint https boven http en www
     * boven niet-www — daar komt het verkeer in de praktijk binnen.
     *
     * @param  array<int,string>  $sites
     */
    public static function matchSiteForDomain(array $sites, string $domain): ?string
    {
        $domain = self::bareDomain($domain);
        if ($domain === '') {
            return null;
        }

        $prefixes = [];

        foreach ($sites as $site) {
            if (str_starts_with($site, 'sc-domain:')) {
                if (self::bareDomain(substr($site, strlen('sc-domain:'))) === $domain) {
                    return $site;
                }

                continue;
            }

            if (self::bareDomain((string) parse_url($site, PHP_URL_HOST)) === $domain) {
                $prefixes[] = $site;
            }
        }

        usort($prefixes, fn ($a, $b) => self::prefixScore($b) <=> self::prefixScore($a));

        return $prefixes[0] ?? null;
    }

    /** Hoger = waarschijnlijker de property waar het verkeer op binnenkomt. */
    protected static function prefixScore(string $site): int
    {
        return (str_starts_with($site, 'https://') ? 2 : 0)
            + (str_contains($site, '://www.') ? 1 : 0);
    }

    /** "https://www.Foo.be/pad" → "foo.be", zodat schrijfwijzen vergelijkbaar worden. */
    protected static function bareDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^https?://#', '', $value);
        $value = (string) preg_replace('#^www\.#', '', $value);

        return rtrim(explode('/', $value)[0], '/');
    }

    /** Koppeling verbreken: het refresh token vergeten. */
    public function disconnect(): void
    {
        Cache::forget($this->tokenCacheKey());
        Setting::set('gsc_refresh_token', '');
        $this->refreshToken = null;
    }

    /* ---------------------------------------------------------------------
     | Endpoints
     * ------------------------------------------------------------------- */

    /**
     * De properties waartoe dit service account toegang heeft.
     * Gebruikt door de "Test verbinding"-knop: als onze siteUrl hier niet in
     * staat, is het service account nog niet toegevoegd in Search Console.
     *
     * @return array<int,string>|null  null bij een fout
     */
    public function listSites(): ?array
    {
        $res = $this->request('get', '/sites');
        if ($res === null) {
            return null;
        }

        return collect($res['siteEntry'] ?? [])
            ->pluck('siteUrl')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Search Analytics-query. Geeft de ruwe rijen terug; de aanroeper bepaalt
     * met $dimensions welke grain hij wil ('date', 'query', 'page', ...).
     *
     * dataState 'final' (de standaard bij Google) sluit de laatste ~2-3 dagen
     * uit die nog niet volledig verwerkt zijn. Dat is bewust: liever 3 dagen
     * vertraging dan een grafiek die elke dag met een nep-dip eindigt.
     *
     * @param  array<int,string>  $dimensions
     * @return array<int,array<string,mixed>>|null  null bij een fout
     */
    public function searchAnalytics(
        string $startDate,
        string $endDate,
        array $dimensions = ['date'],
        int $rowLimit = 25000,
        int $startRow = 0,
    ): ?array {
        $path = '/sites/' . rawurlencode($this->siteUrl) . '/searchAnalytics/query';

        $res = $this->request('post', $path, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => $dimensions,
            'rowLimit' => $rowLimit,
            'startRow' => $startRow,
            'dataState' => 'final',
        ]);

        if ($res === null) {
            return null;
        }

        return $res['rows'] ?? [];
    }

    /* ---------------------------------------------------------------------
     | Auth
     * ------------------------------------------------------------------- */

    /** Cache-sleutel die meebeweegt met de gebruikte credentials. */
    protected function tokenCacheKey(): string
    {
        $fingerprint = $this->hasOAuth()
            ? md5($this->clientId . '|' . $this->refreshToken)
            : $this->credentialsHash;

        return self::TOKEN_CACHE_KEY . ':' . $fingerprint;
    }

    /** Access token ophalen (gecachet tot kort voor het verloopt). */
    protected function accessToken(): ?string
    {
        if (!$this->hasOAuth() && !$this->credentials) {
            return null;
        }

        $cacheKey = $this->tokenCacheKey();

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $payload = $this->hasOAuth()
            ? [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]
            : $this->jwtGrantPayload();

        if ($payload === null) {
            return null;
        }

        try {
            $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, $payload);

            if (!$response->successful()) {
                Log::warning('Search Console: token ophalen mislukt', [
                    'method' => $this->authMethod(),
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // invalid_grant bij OAuth betekent dat de toestemming ingetrokken
                // of verlopen is. Het token weggooien, zodat de UI meteen toont
                // dat er opnieuw gekoppeld moet worden.
                if ($this->hasOAuth() && $response->json('error') === 'invalid_grant') {
                    Log::warning('Search Console: refresh token niet langer geldig, koppeling verbroken.');
                    $this->disconnect();
                }

                return null;
            }

            $token = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);

            if (!$token) {
                return null;
            }

            // 5 minuten marge zodat een lopende sync niet halverwege verloopt.
            Cache::put($cacheKey, $token, max(60, $expiresIn - 300));

            return $token;
        } catch (\Throwable $e) {
            Log::error('Search Console: tokenfout', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string,string>|null */
    protected function jwtGrantPayload(): ?array
    {
        $jwt = $this->buildSignedJwt();
        if ($jwt === null) {
            return null;
        }

        return [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ];
    }

    /** Bouw en onderteken het JWT-bearer-assertion (RS256). */
    protected function buildSignedJwt(): ?string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $key = openssl_pkey_get_private($this->credentials['private_key']);
        if ($key === false) {
            Log::error('Search Console: private key uit de JSON-sleutel is ongeldig.');

            return null;
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::error('Search Console: JWT ondertekenen mislukt.');

            return null;
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /* ---------------------------------------------------------------------
     | HTTP
     * ------------------------------------------------------------------- */

    /** @return array<string,mixed>|null  null bij een fout (altijd gelogd) */
    protected function request(string $method, string $path, array $payload = []): ?array
    {
        if (!$this->hasOAuth() && !$this->credentials) {
            return null;
        }

        $token = $this->accessToken();
        if (!$token) {
            return null;
        }

        try {
            $http = Http::withToken($token)->timeout(120)->acceptJson();

            $response = $method === 'post'
                ? $http->post(self::API_BASE . $path, $payload)
                : $http->get(self::API_BASE . $path);

            if (!$response->successful()) {
                Log::warning('Search Console API-fout', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('Search Console request mislukt', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
