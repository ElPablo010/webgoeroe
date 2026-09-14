<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Google\GoogleApiClient;

/**
 * Thin client voor de Google Search Console API (Search Analytics).
 *
 * Deel van de Groei-meetlaag: dit is het GEMETEN Google-verkeer (clicks,
 * vertoningen, CTR, positie) — in tegenstelling tot de DataForSEO-schatting.
 *
 * Alles wat met inloggen en HTTP te maken heeft staat in
 * {@see GoogleApiClient}; hier blijft enkel wat écht over Search Console gaat:
 * welke property we volgen en de twee endpoints die we bevragen.
 *
 * Authenticatie bij voorkeur via **OAuth** op het Google-account dat de
 * property al beheert (client-ID + secret uit Google Cloud, refresh token na
 * de consent-flow). Terugval: een **service account** (JSON-sleutel), maar
 * Google blokkeert het aanmaken van zulke sleutels standaard in organisaties.
 */
class GoogleSearchConsoleService extends GoogleApiClient
{
    /** De property zoals Search Console ze kent, bv. "sc-domain:bailandolatino.be". */
    public string $siteUrl;

    public function __construct()
    {
        parent::__construct();

        $this->siteUrl = trim((string) Setting::get('gsc_site_url', ''));
    }

    protected function serviceAccountScope(): string
    {
        return 'https://www.googleapis.com/auth/webmasters.readonly';
    }

    protected function apiBase(): string
    {
        return 'https://www.googleapis.com/webmasters/v3';
    }

    protected function label(): string
    {
        return 'Search Console';
    }

    /** Gekoppeld én we weten welke property we moeten bevragen. */
    public function isConfigured(): bool
    {
        return $this->hasCredentials() && $this->siteUrl !== '';
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
     * @return array{ok:bool,site:?string,count:?int} count null als de lijst niet opgehaald raakte
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

    /* ---------------------------------------------------------------------
     | Endpoints
     * ------------------------------------------------------------------- */

    /**
     * De properties waartoe dit account toegang heeft.
     * Gebruikt door de "Test verbinding"-knop: als onze siteUrl hier niet in
     * staat, is het service account nog niet toegevoegd in Search Console.
     *
     * @return array<int,string>|null null bij een fout
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
     * @return array<int,array<string,mixed>>|null null bij een fout
     */
    public function searchAnalytics(
        string $startDate,
        string $endDate,
        array $dimensions = ['date'],
        int $rowLimit = 25000,
        int $startRow = 0,
    ): ?array {
        $path = '/sites/'.rawurlencode($this->siteUrl).'/searchAnalytics/query';

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
}
