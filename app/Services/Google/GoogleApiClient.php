<?php

namespace App\Services\Google;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gedeelde basis voor de Google-API's in de Groei-meetlaag.
 *
 * Alles wat niet over één specifieke API gaat staat hier: toestemming vragen,
 * de code inwisselen voor een refresh token, een access token ophalen en
 * cachen, het JWT-bearer-flow voor een service account, en de HTTP-laag met
 * foutlogging. Search Console en (straks) Analytics erven ervan en vullen
 * alleen hun eigen scope, basis-URL, setting-voorvoegsel en label in.
 *
 * Waarom hier geen google/apiclient: die package sleept tientallen
 * afhankelijkheden mee terwijl we maar een handvol endpoints nodig hebben. Het
 * JWT-bearer-flow is met openssl_sign() een handvol regels.
 *
 * De setting-sleutels worden afgeleid uit één voorvoegsel, zodat een tweede
 * API geen eigen lijstje namen hoeft mee te dragen:
 *
 *   <prefix>_oauth_client_id
 *   <prefix>_oauth_client_secret
 *   <prefix>_refresh_token
 *   <prefix>_service_account_json
 */
abstract class GoogleApiClient
{
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * De rechten die de gedeelde koppeling vraagt.
     *
     * Eén toestemming voor beide diensten: Search Console en Analytics draaien
     * op dezelfde Google Cloud-app en hetzelfde account, dus het heeft geen zin
     * om de gebruiker twee keer door dezelfde flow te sturen. Google geeft dan
     * één refresh token dat voor allebei geldt.
     */
    public const CONSENT_SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    /**
     * Het recht dat een service account vraagt. Enkel voor het JWT-flow, dat
     * per API werkt — de OAuth-consent gebruikt CONSENT_SCOPES.
     */
    abstract protected function serviceAccountScope(): string;

    /** Basis-URL van de API, zonder slash op het einde. */
    abstract protected function apiBase(): string;

    /** Naam in logregels en meldingen, bv. 'Search Console'. */
    abstract protected function label(): string;

    /**
     * Voorvoegsel van de setting-sleutels. Beide diensten delen één koppeling
     * en dus één voorvoegsel; overschrijfbaar zodat de laag bruikbaar blijft
     * voor een dienst die wél een eigen account nodig heeft.
     */
    protected function settingPrefix(): string
    {
        return 'google';
    }

    protected ?array $credentials = null;

    /**
     * Waarom de laatste call mislukte, in mensentaal.
     *
     * Zonder dit blijft "geen data" dubbelzinnig: een geweigerde call en een
     * antwoord zónder rijen zien er voor de aanroeper identiek uit (null resp.
     * een lege lijst), terwijl het eerste een instelfout is en het tweede
     * gewoon betekent dat Google nog niets heeft. De reden staat wel in het
     * log, maar op gedeelde hosting sla je dat niet even open.
     */
    protected ?string $lastError = null;

    /** Hash van de sleutel: verandert de sleutel, dan vervalt het gecachete token vanzelf. */
    protected string $credentialsHash = '';

    protected ?string $clientId = null;

    protected ?string $clientSecret = null;

    protected ?string $refreshToken = null;

    public function __construct()
    {
        // Voorkeursweg: OAuth met een refresh token op je eigen Google-account.
        $this->clientId = trim((string) Setting::get($this->settingKey('oauth_client_id'), '')) ?: null;
        $this->clientSecret = trim((string) Setting::get($this->settingKey('oauth_client_secret'), '')) ?: null;
        $this->refreshToken = trim((string) Setting::get($this->settingKey('refresh_token'), '')) ?: null;

        // Alternatief: een service account. Werkt alleen als je organisatie het
        // aanmaken van sleutels toelaat — Google blokkeert dat standaard met de
        // policy iam.disableServiceAccountKeyCreation.
        $raw = Setting::get($this->settingKey('service_account_json'));
        if (! empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && ! empty($decoded['client_email']) && ! empty($decoded['private_key'])) {
                $this->credentials = $decoded;
                $this->credentialsHash = md5($raw);
            }
        }
    }

    /** Volledige setting-sleutel voor dit voorvoegsel. */
    protected function settingKey(string $suffix): string
    {
        return $this->settingPrefix().'_'.$suffix;
    }

    /* ---------------------------------------------------------------------
     | Staat van de koppeling
     * ------------------------------------------------------------------- */

    /** Is er überhaupt een manier om in te loggen? */
    public function hasCredentials(): bool
    {
        return $this->hasOAuth() || $this->credentials !== null;
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

    /** Waarom de laatste call mislukte, of null als er niets misging. */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** Vergeet de vorige fout, zodat een volgende ronde schoon begint. */
    public function forgetLastError(): void
    {
        $this->lastError = null;
    }

    /** Het e-mailadres dat de gebruiker bij Google moet toevoegen. */
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
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::CONSENT_SCOPES),
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
        if (! $this->canStartOAuth()) {
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

            if (! $response->successful()) {
                Log::error($this->label().': code inwisselen mislukt', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Google weigerde de koppeling: '.($response->json('error_description') ?? $response->json('error') ?? 'onbekende fout')
                        .'. Controleer of de omleidings-URI exact overeenkomt met wat je in Google Cloud invulde.',
                ];
            }

            $refreshToken = $response->json('refresh_token');
            if (! $refreshToken) {
                return [
                    'ok' => false,
                    'message' => 'Google gaf geen refresh token terug. Verbreek de koppeling in je Google-account onder '
                        .'"Apps van derden" en probeer opnieuw.',
                ];
            }

            Setting::set($this->settingKey('refresh_token'), $refreshToken);
            $this->refreshToken = $refreshToken;
            Cache::forget($this->tokenCacheKey());

            // Google zegt in het antwoord welke rechten je écht kreeg. Dat
            // bewaren we, zodat de UI kan tonen dat Analytics nog ontbreekt
            // zonder eerst een call te moeten doen die dan faalt.
            Setting::set($this->settingKey('oauth_scopes'), (string) $response->json('scope', ''));

            return ['ok' => true, 'message' => $this->label().' gekoppeld.'];
        } catch (\Throwable $e) {
            Log::error($this->label().': OAuth-fout', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Koppelen mislukt: '.$e->getMessage()];
        }
    }

    /** Koppeling verbreken: het refresh token vergeten. */
    public function disconnect(): void
    {
        Cache::forget($this->tokenCacheKey());
        Setting::set($this->settingKey('refresh_token'), '');
        Setting::set($this->settingKey('oauth_scopes'), '');
        $this->refreshToken = null;
    }

    /**
     * De rechten die Google bij de laatste koppeling toekende.
     *
     * @return array<int,string>
     */
    public function grantedScopes(): array
    {
        $raw = trim((string) Setting::get($this->settingKey('oauth_scopes'), ''));

        return $raw === '' ? [] : preg_split('/\s+/', $raw);
    }

    /**
     * Is dit recht mee toegekend?
     *
     * Een koppeling van vóór de Analytics-uitbreiding heeft het niet, en een
     * lege lijst betekent "we weten het niet" — dan gaan we uit van enkel het
     * oudste recht, Search Console, zodat we niets beloven wat niet werkt.
     */
    public function hasGrantedScope(string $scope): bool
    {
        $granted = $this->grantedScopes();

        if ($granted === []) {
            return $scope === self::CONSENT_SCOPES[0];
        }

        return in_array($scope, $granted, true);
    }

    /* ---------------------------------------------------------------------
     | Auth
     * ------------------------------------------------------------------- */

    /** Cache-sleutel die meebeweegt met de gebruikte credentials. */
    protected function tokenCacheKey(): string
    {
        $fingerprint = $this->hasOAuth()
            ? md5($this->clientId.'|'.$this->refreshToken)
            : $this->credentialsHash;

        return $this->settingKey('access_token').':'.$fingerprint;
    }

    /** Access token ophalen (gecachet tot kort voor het verloopt). */
    protected function accessToken(): ?string
    {
        if (! $this->hasCredentials()) {
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

            if (! $response->successful()) {
                Log::error($this->label().': token ophalen mislukt', [
                    'method' => $this->authMethod(),
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // invalid_grant bij OAuth betekent dat de toestemming ingetrokken
                // of verlopen is. Het token weggooien, zodat de UI meteen toont
                // dat er opnieuw gekoppeld moet worden.
                if ($this->hasOAuth() && $response->json('error') === 'invalid_grant') {
                    Log::error($this->label().': refresh token niet langer geldig, koppeling verbroken.');
                    $this->disconnect();

                    $this->lastError = 'De toestemming bij Google is vervallen. Verbind opnieuw op SEO-instellingen.';

                    return null;
                }

                $this->lastError = self::describeApiError($response->status(), $response->json());

                return null;
            }

            $token = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);

            if (! $token) {
                return null;
            }

            // 5 minuten marge zodat een lopende sync niet halverwege verloopt.
            Cache::put($cacheKey, $token, max(60, $expiresIn - 300));

            return $token;
        } catch (\Throwable $e) {
            Log::error($this->label().': tokenfout', ['error' => $e->getMessage()]);

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
            'scope' => $this->serviceAccountScope(),
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
            Log::error($this->label().': private key uit de JSON-sleutel is ongeldig.');

            return null;
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::error($this->label().': JWT ondertekenen mislukt.');

            return null;
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /* ---------------------------------------------------------------------
     | HTTP
     * ------------------------------------------------------------------- */

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|null null bij een fout (altijd gelogd)
     */
    protected function request(string $method, string $path, array $payload = [], ?string $baseUrl = null): ?array
    {
        if (! $this->hasCredentials()) {
            $this->lastError = 'Er is nog geen koppeling met Google.';

            return null;
        }

        $token = $this->accessToken();
        if (! $token) {
            $this->lastError ??= 'Inloggen bij Google lukte niet. Koppel opnieuw op SEO-instellingen.';

            return null;
        }

        $base = $baseUrl ?? $this->apiBase();

        try {
            $http = Http::withToken($token)->timeout(120)->acceptJson();

            $response = $method === 'post'
                ? $http->post($base.$path, $payload)
                : $http->get($base.$path);

            if (! $response->successful()) {
                // Bewust error en geen warning: op gedeelde hosting staat
                // LOG_LEVEL=error, en dan verdwijnt net de regel die uitlegt
                // waarom een koppeling niets oplevert.
                Log::error($this->label().' API-fout', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                $this->lastError = self::describeApiError($response->status(), $response->json());

                return null;
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error($this->label().' request mislukt', ['path' => $path, 'error' => $e->getMessage()]);

            $this->lastError = $e->getMessage();

            return null;
        }
    }

    /**
     * Google's foutantwoord terugbrengen tot de zin die ertoe doet.
     *
     * Een mislukte call draagt het echte verhaal in `error.message` — "API has
     * not been used in project …", "User does not have sufficient permissions
     * for this property" — en dát is precies wat je in de admin wil zien in
     * plaats van "geen data". De statuscode blijft ervoor staan omdat 403 en
     * 400 naar verschillende oplossingen wijzen (recht vs. verkeerd ID).
     *
     * @param  array<string,mixed>|null  $body
     */
    protected static function describeApiError(int $status, ?array $body): string
    {
        $message = $body['error']['message'] ?? $body['error_description'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            $message = is_string($body['error'] ?? null) ? $body['error'] : 'Google gaf geen uitleg.';
        }

        return "Google antwoordde met {$status}: ".trim($message);
    }
}
