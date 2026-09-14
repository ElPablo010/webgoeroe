<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Google\GoogleApiClient;

/**
 * Thin client voor Google Analytics 4 (Data API + Admin API).
 *
 * Deel van de Groei-meetlaag en de tegenhanger van
 * {@see GoogleSearchConsoleService}: Search Console vertelt hoe mensen de site
 * vonden, Analytics wat ze er daarna deden.
 *
 * Deelt de koppeling met Search Console — zelfde Google Cloud-app, zelfde
 * account, één toestemming voor beide rechten. Het inlogwerk zit in
 * {@see GoogleApiClient}.
 *
 * Let op het verschil tussen twee nummers die allebei "van Analytics" zijn:
 *  - het **meet-ID** (G-XXXXXXX) hoort in de meetcode op de site, en staat in
 *    Instellingen → Algemeen;
 *  - het **property-ID** (een getal) is wat de Data API wil, en staat hier in
 *    Setting `ga4_property_id`.
 */
class GoogleAnalyticsService extends GoogleApiClient
{
    /** Het recht dat de Data API én de Admin API (lezen) afdekt. */
    public const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    protected const ADMIN_BASE = 'https://analyticsadmin.googleapis.com/v1beta';

    /** Het numerieke property-ID, zonder "properties/"-voorvoegsel. */
    public string $propertyId;

    public function __construct()
    {
        parent::__construct();

        $this->propertyId = trim((string) Setting::get('ga4_property_id', ''));
    }

    protected function serviceAccountScope(): string
    {
        return self::SCOPE;
    }

    protected function apiBase(): string
    {
        return 'https://analyticsdata.googleapis.com/v1beta';
    }

    protected function label(): string
    {
        return 'Analytics';
    }

    /** Gekoppeld, met het Analytics-recht erbij, én we weten welke property. */
    public function isConfigured(): bool
    {
        return $this->hasCredentials()
            && $this->hasGrantedScope(self::SCOPE)
            && $this->propertyId !== '';
    }

    /**
     * Gekoppeld met Google, maar zonder het Analytics-recht.
     *
     * Dat is de toestand van elke installatie die vóór deze uitbreiding al aan
     * Search Console hing: het bestaande refresh token dekt Analytics niet, dus
     * de gebruiker moet één keer opnieuw toestemming geven.
     */
    public function needsReconsent(): bool
    {
        return $this->hasOAuth() && ! $this->hasGrantedScope(self::SCOPE);
    }

    /* ---------------------------------------------------------------------
     | Welke property volgen we op?
     * ------------------------------------------------------------------- */

    public function setPropertyId(string $propertyId): void
    {
        $propertyId = self::barePropertyId($propertyId);

        Setting::set('ga4_property_id', $propertyId);
        $this->propertyId = $propertyId;
    }

    /** "properties/123456789" → "123456789". */
    public static function barePropertyId(string $value): string
    {
        return trim(str_replace('properties/', '', trim($value)));
    }

    /**
     * De properties waartoe dit account toegang heeft.
     *
     * @return array<int,array{id:string,name:string,account:string}>|null null bij een fout
     */
    public function listProperties(): ?array
    {
        $res = $this->request('get', '/accountSummaries', [], self::ADMIN_BASE);
        if ($res === null) {
            return null;
        }

        $out = [];

        foreach ($res['accountSummaries'] ?? [] as $account) {
            foreach ($account['propertySummaries'] ?? [] as $property) {
                $id = self::barePropertyId((string) ($property['property'] ?? ''));
                if ($id === '') {
                    continue;
                }

                $out[] = [
                    'id' => $id,
                    'name' => (string) ($property['displayName'] ?? $id),
                    'account' => (string) ($account['displayName'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Kies zelf de property die bij ons eigen domein hoort.
     *
     * Anders dan bij Search Console draagt een GA4-property geen domein in zich
     * — enkel een vrij te kiezen naam. We matchen dus op naam, en vallen terug
     * op "er is er maar één". Lukt geen van beide, dan kiezen we bewust níets:
     * liever laten aanwijzen dan de cijfers van een andere site binnentrekken.
     *
     * @return array{ok:bool,property:?array<string,string>,count:?int}
     */
    public function autoSelectProperty(string $ourDomain): array
    {
        $properties = $this->listProperties();

        if ($properties === null) {
            return ['ok' => false, 'property' => null, 'count' => null];
        }

        if ($properties === []) {
            return ['ok' => false, 'property' => null, 'count' => 0];
        }

        $match = self::matchPropertyForDomain($properties, $ourDomain)
            ?? (count($properties) === 1 ? $properties[0] : null);

        if ($match === null) {
            return ['ok' => false, 'property' => null, 'count' => count($properties)];
        }

        $this->setPropertyId($match['id']);

        return ['ok' => true, 'property' => $match, 'count' => count($properties)];
    }

    /**
     * Welke property gaat over dit domein? Vergelijkt op naam, waarbij zowel
     * "webgoeroe.be" als "De Webgoeroe" op "webgoeroe" uitkomen.
     *
     * @param  array<int,array{id:string,name:string,account:string}>  $properties
     * @return array{id:string,name:string,account:string}|null
     */
    public static function matchPropertyForDomain(array $properties, string $domain): ?array
    {
        $needle = self::normalise($domain);
        if ($needle === '') {
            return null;
        }

        foreach ($properties as $property) {
            if (self::normalise($property['name']) === $needle) {
                return $property;
            }
        }

        // Geen exacte treffer: een gedeeltelijke telt ook, in beide richtingen.
        // "Webgoeroe - GA4" bevat het domein; omgekeerd bevat een doeldomein als
        // "De Webgoeroe" juist de propertynaam. De minimumlengte houdt tegen dat
        // een property met een korte naam ("BE") zomat op alles matcht.
        foreach ($properties as $property) {
            $name = self::normalise($property['name']);

            if (strlen($name) < 4) {
                continue;
            }

            if (str_contains($name, $needle) || str_contains($needle, $name)) {
                return $property;
            }
        }

        return null;
    }

    /** "www.De-Webgoeroe.be" → "webgoeroe": alles weg wat geen letter of cijfer is. */
    protected static function normalise(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^https?://#', '', $value);
        $value = (string) preg_replace('#^www\.#', '', $value);
        $value = explode('/', $value)[0];
        // Domeinsuffix weg, zodat "webgoeroe.be" en "Webgoeroe" samenvallen.
        $value = (string) preg_replace('#\.(be|com|net|org|eu|nl|fr)$#', '', $value);

        return (string) preg_replace('#[^a-z0-9]#', '', $value);
    }

    /* ---------------------------------------------------------------------
     | Endpoints
     * ------------------------------------------------------------------- */

    /**
     * Een rapport opvragen bij de Data API.
     *
     * Anders dan Search Console geeft GA4 zijn rijen positioneel terug: de
     * waarden staan in dezelfde volgorde als de dimensies en metrics die je
     * opvroeg, en allemaal als string. De aanroeper leest ze dus op index.
     *
     * @param  array<int,string>  $dimensions
     * @param  array<int,string>  $metrics
     * @return array<int,array<string,mixed>>|null null bij een fout
     */
    public function runReport(
        string $startDate,
        string $endDate,
        array $dimensions,
        array $metrics,
        int $limit = 100000,
        int $offset = 0,
        ?array $orderByMetric = null,
    ): ?array {
        $payload = [
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => array_map(fn ($d) => ['name' => $d], $dimensions),
            'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
            'limit' => $limit,
            'offset' => $offset,
            // Standaard telt GA4 ook een "(other)"-emmer wanneer er te veel
            // verschillende waarden zijn; die filteren we bij het wegschrijven.
            'keepEmptyRows' => false,
        ];

        if ($orderByMetric !== null) {
            $payload['orderBys'] = [[
                'metric' => ['metricName' => $orderByMetric[0]],
                'desc' => $orderByMetric[1] ?? true,
            ]];
        }

        $res = $this->request('post', '/properties/'.rawurlencode($this->propertyId).':runReport', $payload);

        if ($res === null) {
            return null;
        }

        return $res['rows'] ?? [];
    }

    /**
     * De waarden van één rij uitlezen: dimensies en metrics samen, op volgorde.
     *
     * @param  array<string,mixed>  $row
     * @return array{dimensions:array<int,string>,metrics:array<int,string>}
     */
    public static function readRow(array $row): array
    {
        return [
            'dimensions' => array_map(
                fn ($v) => (string) ($v['value'] ?? ''),
                $row['dimensionValues'] ?? []
            ),
            'metrics' => array_map(
                fn ($v) => (string) ($v['value'] ?? ''),
                $row['metricValues'] ?? []
            ),
        ];
    }
}
