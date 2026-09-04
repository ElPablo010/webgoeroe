<?php

namespace App\Services;

use App\Models\GscDailyMetric;
use App\Models\GscDimensionMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Haalt Search Console-cijfers op en schrijft ze weg.
 *
 * Twee bewuste keuzes:
 *  1. **Rollend venster i.p.v. append.** Google herziet de cijfers van de
 *     voorbije dagen nog. We halen daarom telkens de laatste dagen opnieuw op
 *     en overschrijven per dag (upsert), zodat de historiek klopt.
 *  2. **Backfill bij de eerste run.** Search Console bewaart 16 maanden. Die
 *     halen we in één keer binnen, zodat je meteen een echte trendlijn hebt
 *     in plaats van een vlakke lijn die pas over maanden betekenis krijgt.
 */
class GscCollector
{
    /** Search Console bewaart maximaal 16 maanden historiek. */
    protected const BACKFILL_MONTHS = 16;

    /** Aantal recente dagen dat elke sync opnieuw ophaalt (revisies). */
    protected const REFETCH_DAYS = 7;

    /** Periode waarover zoekterm-/pagina-aggregaten berekend worden. */
    public const DIMENSION_WINDOW_DAYS = 28;

    /** Search Console levert max 25.000 rijen per call. */
    protected const PAGE_SIZE = 25000;

    public function __construct(protected GoogleSearchConsoleService $api)
    {
    }

    /**
     * Gekoppeld én bruikbaar. De tabel-check vangt de volgorde af waarin
     * iemand eerst de credentials invult en pas daarna migreert — zonder
     * die check zou het SEO-dashboard dan een 500 geven.
     */
    public function isConfigured(): bool
    {
        return $this->api->isConfigured() && Schema::hasTable('gsc_daily_metrics');
    }

    /**
     * De normale sync: dagcijfers bijwerken + zoekterm-/pagina-aggregaten.
     *
     * @return array{days: int, queries: int, pages: int, backfilled: bool}
     */
    public function sync(): array
    {
        $siteUrl = $this->api->siteUrl;
        $isFirstRun = !GscDailyMetric::where('site_url', $siteUrl)->exists();

        $days = $isFirstRun ? $this->backfillDaily() : $this->syncRecentDaily();

        return [
            'days' => $days,
            'queries' => $this->syncDimension(GscDimensionMetric::DIMENSION_QUERY),
            'pages' => $this->syncDimension(GscDimensionMetric::DIMENSION_PAGE),
            'backfilled' => $isFirstRun,
        ];
    }

    /** Eerste run: de volledige 16 maanden binnenhalen. */
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
        $rows = $this->fetchAllRows($start, $end, ['date']);
        if ($rows === null) {
            return 0;
        }

        $siteUrl = $this->api->siteUrl;
        $saved = 0;

        foreach ($rows as $row) {
            $date = $row['keys'][0] ?? null;
            if (!$date) {
                continue;
            }

            GscDailyMetric::updateOrCreate(
                ['site_url' => $siteUrl, 'date' => $date],
                [
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ],
            );
            $saved++;
        }

        return $saved;
    }

    /** Zoekterm- of pagina-aggregaten over het voortschrijdende venster. */
    protected function syncDimension(string $dimension): int
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays(self::DIMENSION_WINDOW_DAYS);

        $rows = $this->fetchAllRows($start, $end, [$dimension]);
        if ($rows === null) {
            return 0;
        }

        $siteUrl = $this->api->siteUrl;
        $saved = 0;

        foreach ($rows as $row) {
            $value = $row['keys'][0] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            GscDimensionMetric::updateOrCreate(
                [
                    'site_url' => $siteUrl,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'dimension' => $dimension,
                    'value_hash' => GscDimensionMetric::hashFor($value),
                ],
                [
                    'value' => $value,
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ],
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * Alle rijen van een query ophalen, over de paginering heen.
     *
     * @return array<int,array<string,mixed>>|null  null als de API faalde
     */
    protected function fetchAllRows(Carbon $start, Carbon $end, array $dimensions): ?array
    {
        $all = [];
        $startRow = 0;

        do {
            $rows = $this->api->searchAnalytics(
                $start->toDateString(),
                $end->toDateString(),
                $dimensions,
                self::PAGE_SIZE,
                $startRow,
            );

            if ($rows === null) {
                // Fout op de eerste pagina → niets bruikbaars. Fout halverwege →
                // teruggeven wat we hebben, dan is een deel beter dan niets.
                return $startRow === 0 ? null : $all;
            }

            $all = array_merge($all, $rows);
            $startRow += self::PAGE_SIZE;
        } while (count($rows) === self::PAGE_SIZE);

        return $all;
    }

    /* ---------------------------------------------------------------------
     | Uitlezen voor dashboard en advies
     * ------------------------------------------------------------------- */

    /**
     * Kerncijfers over de laatste 28 dagen, met de 28 dagen daarvóór als
     * vergelijking. Dit is het antwoord op "gaan we vooruit?".
     *
     * @return array<string,mixed>|null  null als er nog geen data is
     */
    public function summary(int $windowDays = self::DIMENSION_WINDOW_DAYS): ?array
    {
        $siteUrl = $this->api->siteUrl;

        $lastDate = GscDailyMetric::where('site_url', $siteUrl)->max('date');
        if (!$lastDate) {
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
                'clicks' => $current['clicks'] - $previous['clicks'],
                'impressions' => $current['impressions'] - $previous['impressions'],
                'ctr' => round($current['ctr'] - $previous['ctr'], 2),
                // Positie: lager is beter, dus draaien we het teken om zodat
                // een positief getal in de UI altijd "vooruitgang" betekent.
                'position' => $previous['position'] && $current['position']
                    ? round($previous['position'] - $current['position'], 1)
                    : null,
            ],
            'has_comparison' => $previous['impressions'] > 0,
        ];
    }

    /** Opgetelde cijfers over een periode. CTR/positie gewogen, niet gemiddeld. */
    protected function aggregate(Carbon $start, Carbon $end): array
    {
        $rows = GscDailyMetric::where('site_url', $this->api->siteUrl)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $clicks = (int) $rows->sum('clicks');
        $impressions = (int) $rows->sum('impressions');

        // Gemiddelde positie wegen op impressies — een dag met 3 impressies
        // mag het maandgemiddelde niet evenveel sturen als een dag met 300.
        $weighted = $rows->sum(fn ($r) => $r->position * $r->impressions);

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions ? round($clicks / $impressions * 100, 2) : 0.0,
            'position' => $impressions ? round($weighted / $impressions, 1) : null,
            'days' => $rows->count(),
        ];
    }

    /**
     * Dagreeks voor de grafiek, opgeteld per week zodat de lijn leesbaar is.
     *
     * @return array<int,array{date:string,clicks:int,impressions:int,position:float|null}>
     */
    public function weeklyTrend(int $weeks = 26): array
    {
        $since = Carbon::today()->subWeeks($weeks)->startOfWeek();

        return GscDailyMetric::where('site_url', $this->api->siteUrl)
            ->where('date', '>=', $since->toDateString())
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($r) => $r->date->copy()->startOfWeek()->toDateString())
            ->map(function ($rows, $weekStart) {
                $impressions = (int) $rows->sum('impressions');
                $weighted = $rows->sum(fn ($r) => $r->position * $r->impressions);

                return [
                    'date' => $weekStart,
                    'clicks' => (int) $rows->sum('clicks'),
                    'impressions' => $impressions,
                    'position' => $impressions ? round($weighted / $impressions, 1) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Top-zoektermen of -pagina's uit de meest recente periode.
     *
     * @return array<int,array<string,mixed>>
     */
    public function top(string $dimension, int $limit = 15): array
    {
        return GscDimensionMetric::latestPeriod($this->api->siteUrl)
            ->where('dimension', $dimension)
            ->orderByDesc('clicks')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'clicks' => $r->clicks,
                'impressions' => $r->impressions,
                'ctr' => round($r->ctr * 100, 1),
                'position' => round($r->position, 1),
            ])
            ->all();
    }

    /**
     * Zoektermen met veel vertoningen maar weinig clicks: je staat er wél,
     * maar net niet hoog genoeg of met een zwakke titel. Dit zijn doorgaans
     * de goedkoopste winsten.
     *
     * @return array<int,array<string,mixed>>
     */
    public function opportunities(int $limit = 15): array
    {
        return GscDimensionMetric::latestPeriod($this->api->siteUrl)
            ->queries()
            ->where('impressions', '>=', 20)
            ->whereBetween('position', [4, 20])
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'query' => $r->value,
                'clicks' => $r->clicks,
                'impressions' => $r->impressions,
                'ctr' => round($r->ctr * 100, 1),
                'position' => round($r->position, 1),
            ])
            ->all();
    }
}
