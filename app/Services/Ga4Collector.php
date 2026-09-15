<?php

namespace App\Services;

use App\Models\Ga4DailyMetric;
use App\Models\Ga4DimensionMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Haalt Analytics-cijfers op en schrijft ze weg.
 *
 * Zelfde twee keuzes als {@see GscCollector}:
 *  1. **Rollend venster i.p.v. append.** Google herziet de cijfers van de
 *     voorbije dagen nog. We halen telkens de laatste dagen opnieuw op en
 *     overschrijven per dag (upsert), zodat de historiek klopt.
 *  2. **Backfill bij de eerste run.** Zodat er meteen een trendlijn staat in
 *     plaats van een vlakke lijn die pas over maanden betekenis krijgt.
 *
 * Verschil met Search Console: GA4 kent geen `dataState: final`, dus er is geen
 * manier om onvolledige dagen uit te sluiten. Het rollende venster vangt dat op.
 */
class Ga4Collector
{
    /** Hoe ver we bij de eerste run terugkijken. Vóór de meetcode bestond is er toch niets. */
    protected const BACKFILL_MONTHS = 16;

    /** Aantal recente dagen dat elke sync opnieuw ophaalt (revisies). */
    protected const REFETCH_DAYS = 7;

    /** Periode waarover pagina- en kanaalaggregaten berekend worden. */
    public const DIMENSION_WINDOW_DAYS = 28;

    /** Rijen per call. De Data API staat tot 250.000 toe. */
    protected const PAGE_SIZE = 100000;

    /** Hoeveel pagina's en kanalen we bewaren per periode. */
    protected const DIMENSION_LIMIT = 250;

    /**
     * GA4 bundelt de staart van een dimensie met veel verschillende waarden in
     * één rij. Die is geen pagina en hoort niet in de tabel.
     */
    protected const OTHER_ROW = '(other)';

    /** Volgorde van de metrics in elke dag-rij. */
    protected const DAILY_METRICS = [
        'sessions',
        'activeUsers',
        'screenPageViews',
        'engagedSessions',
        'averageSessionDuration',
    ];

    /** Volgorde van de metrics in elke dimensie-rij. */
    protected const DIMENSION_METRICS = [
        'sessions',
        'screenPageViews',
        'engagedSessions',
        'averageSessionDuration',
    ];

    public function __construct(protected GoogleAnalyticsService $api) {}

    /**
     * Gekoppeld én bruikbaar. De tabel-check vangt de volgorde af waarin iemand
     * eerst koppelt en pas daarna migreert — zonder die check zou het scherm
     * dan een 500 geven.
     */
    public function isConfigured(): bool
    {
        return $this->api->isConfigured() && Schema::hasTable('ga4_daily_metrics');
    }

    /**
     * De normale sync: dagcijfers bijwerken + pagina- en kanaalaggregaten.
     *
     * `error` scheidt de twee manieren waarop je met nul dagen eindigt: Google
     * wees de call af (instelfout), of Google antwoordde netjes maar heeft nog
     * niets te melden (meetcode staat er pas net op). Zonder dat onderscheid
     * krijgt de gebruiker dezelfde melding voor een probleem dat hij moet
     * oplossen als voor iets waar hij alleen op moet wachten.
     *
     * @return array{days:int,pages:int,channels:int,backfilled:bool,error:?string}
     */
    public function sync(): array
    {
        $propertyId = $this->api->propertyId;
        $isFirstRun = ! Ga4DailyMetric::where('property_id', $propertyId)->exists();

        $this->api->forgetLastError();

        $days = $isFirstRun ? $this->backfillDaily() : $this->syncRecentDaily();

        return [
            'days' => $days,
            'pages' => $this->syncDimension(Ga4DimensionMetric::DIMENSION_PAGE),
            'channels' => $this->syncDimension(Ga4DimensionMetric::DIMENSION_CHANNEL),
            'backfilled' => $isFirstRun,
            'error' => $this->api->lastError(),
        ];
    }

    /** Eerste run: zo ver terug als er data is. */
    public function backfillDaily(): int
    {
        return $this->fetchDailyRange(
            Carbon::today()->subMonths(self::BACKFILL_MONTHS),
            Carbon::today(),
        );
    }

    /** Reguliere run: enkel het recente venster opnieuw ophalen. */
    public function syncRecentDaily(): int
    {
        return $this->fetchDailyRange(
            Carbon::today()->subDays(self::REFETCH_DAYS),
            Carbon::today(),
        );
    }

    /* ---------------------------------------------------------------------
     | Ophalen + wegschrijven
     * ------------------------------------------------------------------- */

    protected function fetchDailyRange(Carbon $start, Carbon $end): int
    {
        $rows = $this->api->runReport(
            $start->toDateString(),
            $end->toDateString(),
            ['date'],
            self::DAILY_METRICS,
            self::PAGE_SIZE,
        );

        if ($rows === null) {
            return 0;
        }

        $propertyId = $this->api->propertyId;
        $saved = 0;

        foreach ($rows as $row) {
            $parsed = GoogleAnalyticsService::readRow($row);
            $date = $this->parseDate($parsed['dimensions'][0] ?? '');

            if ($date === null) {
                continue;
            }

            $m = $parsed['metrics'];

            Ga4DailyMetric::updateOrCreate(
                ['property_id' => $propertyId, 'date' => $date],
                [
                    'sessions' => (int) ($m[0] ?? 0),
                    'active_users' => (int) ($m[1] ?? 0),
                    'page_views' => (int) ($m[2] ?? 0),
                    'engaged_sessions' => (int) ($m[3] ?? 0),
                    'avg_session_seconds' => round((float) ($m[4] ?? 0), 2),
                ],
            );
            $saved++;
        }

        return $saved;
    }

    /** Pagina- of kanaalaggregaten over het voortschrijdende venster. */
    protected function syncDimension(string $dimension): int
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays(self::DIMENSION_WINDOW_DAYS);

        $rows = $this->api->runReport(
            $start->toDateString(),
            $end->toDateString(),
            [$this->apiDimension($dimension)],
            self::DIMENSION_METRICS,
            self::DIMENSION_LIMIT,
            0,
            ['sessions', true],
        );

        if ($rows === null) {
            return 0;
        }

        $propertyId = $this->api->propertyId;
        $saved = 0;

        foreach ($rows as $row) {
            $parsed = GoogleAnalyticsService::readRow($row);
            $value = $parsed['dimensions'][0] ?? '';

            if ($value === '' || $value === self::OTHER_ROW) {
                continue;
            }

            $m = $parsed['metrics'];

            Ga4DimensionMetric::updateOrCreate(
                [
                    'property_id' => $propertyId,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'dimension' => $dimension,
                    'value_hash' => Ga4DimensionMetric::hashFor($value),
                ],
                [
                    'value' => $value,
                    'sessions' => (int) ($m[0] ?? 0),
                    'page_views' => (int) ($m[1] ?? 0),
                    'engaged_sessions' => (int) ($m[2] ?? 0),
                    'avg_session_seconds' => round((float) ($m[3] ?? 0), 2),
                ],
            );
            $saved++;
        }

        return $saved;
    }

    /** Onze naam voor een dimensie → die van de Data API. */
    protected function apiDimension(string $dimension): string
    {
        return $dimension === Ga4DimensionMetric::DIMENSION_CHANNEL
            ? 'sessionDefaultChannelGroup'
            : 'pagePath';
    }

    /**
     * GA4 geeft de datum als "20260914", zonder streepjes — anders dan Search
     * Console, dat "2026-09-14" teruggeeft.
     */
    protected function parseDate(string $raw): ?string
    {
        if (! preg_match('/^\d{8}$/', $raw)) {
            return null;
        }

        return Carbon::createFromFormat('Ymd', $raw)->toDateString();
    }

    /* ---------------------------------------------------------------------
     | Uitlezen voor het scherm
     * ------------------------------------------------------------------- */

    /**
     * Kerncijfers over de laatste 28 dagen, met de 28 dagen daarvóór als
     * vergelijking.
     *
     * @return array<string,mixed>|null null als er nog geen data is
     */
    public function summary(int $windowDays = self::DIMENSION_WINDOW_DAYS): ?array
    {
        $propertyId = $this->api->propertyId;

        $lastDate = Ga4DailyMetric::where('property_id', $propertyId)->max('date');
        if (! $lastDate) {
            return null;
        }

        $end = Carbon::parse($lastDate);
        $currentStart = $end->copy()->subDays($windowDays - 1);
        $previousEnd = $currentStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($windowDays - 1);

        $current = $this->aggregate($currentStart, $end);
        $previous = $this->aggregate($previousStart, $previousEnd);

        return [
            'period_start' => $currentStart->toDateString(),
            'period_end' => $end->toDateString(),
            'window_days' => $windowDays,
            'current' => $current,
            'previous' => $previous,
            'delta' => [
                'sessions' => $current['sessions'] - $previous['sessions'],
                'active_users' => $current['active_users'] - $previous['active_users'],
                'page_views' => $current['page_views'] - $previous['page_views'],
                'engagement_rate' => round($current['engagement_rate'] - $previous['engagement_rate'], 2),
            ],
            'has_comparison' => $previous['sessions'] > 0,
        ];
    }

    /**
     * Opgetelde cijfers over een periode.
     *
     * Engagement en sessieduur worden gewogen op sessies, niet plat gemiddeld:
     * een dag met 3 sessies mag het maandcijfer niet evenveel sturen als een
     * dag met 300. Zelfde reden als de gewogen positie bij Search Console.
     *
     * @return array<string,mixed>
     */
    protected function aggregate(Carbon $start, Carbon $end): array
    {
        $rows = Ga4DailyMetric::where('property_id', $this->api->propertyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $sessions = (int) $rows->sum('sessions');
        $engaged = (int) $rows->sum('engaged_sessions');
        $weightedDuration = $rows->sum(fn ($r) => $r->avg_session_seconds * $r->sessions);

        return [
            'sessions' => $sessions,
            'active_users' => (int) $rows->sum('active_users'),
            'page_views' => (int) $rows->sum('page_views'),
            'engaged_sessions' => $engaged,
            'engagement_rate' => $sessions ? round($engaged / $sessions * 100, 1) : 0.0,
            'avg_session_seconds' => $sessions ? round($weightedDuration / $sessions) : 0,
            'days' => $rows->count(),
        ];
    }

    /**
     * Top-pagina's of -kanalen uit de meest recente periode.
     *
     * @return array<int,array<string,mixed>>
     */
    public function top(string $dimension, int $limit = 15): array
    {
        $rows = Ga4DimensionMetric::latestPeriod($this->api->propertyId)
            ->where('dimension', $dimension)
            ->orderByDesc('sessions')
            ->orderByDesc('page_views')
            ->limit($limit)
            ->get();

        $totalSessions = (int) Ga4DimensionMetric::latestPeriod($this->api->propertyId)
            ->where('dimension', $dimension)
            ->sum('sessions');

        return $rows->map(fn ($r) => [
            'value' => $r->value,
            'sessions' => $r->sessions,
            'page_views' => $r->page_views,
            'engagement_rate' => $r->sessions ? round($r->engaged_sessions / $r->sessions * 100, 1) : 0.0,
            'avg_session_seconds' => (int) round($r->avg_session_seconds),
            'share' => $totalSessions ? round($r->sessions / $totalSessions * 100, 1) : 0.0,
        ])->all();
    }
}
