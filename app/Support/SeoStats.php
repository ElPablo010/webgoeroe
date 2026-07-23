<?php

namespace App\Support;

use App\Models\SeoKeyword;
use App\Models\SeoReport;
use App\Models\SeoSiteSnapshot;
use App\Services\DataForSeoService;
use Illuminate\Support\Collection;

/**
 * Eén bron van waarheid voor de cijfers op het SEO-dashboard.
 *
 * De widgets, de dashboard-pagina en het weekrapport lezen allemaal hier —
 * anders lopen de getallen op het scherm en die in de mail uit elkaar zodra
 * er één query verandert.
 *
 * Alles wordt per request één keer berekend en gememoïseerd: een dashboard met
 * drie widgets zou anders drie keer dezelfde queries draaien.
 */
class SeoStats
{
    /** @var array<string, mixed> */
    protected static array $memo = [];

    /** De laatste twee snapshots (huidig + vorige) voor delta-berekening. */
    public static function snapshots(): Collection
    {
        return static::remember('snapshots', fn () => SeoSiteSnapshot::query()
            ->where('target', app(DataForSeoService::class)->target)
            ->orderBy('captured_at')
            ->get());
    }

    public static function latest(): ?SeoSiteSnapshot
    {
        return static::snapshots()->last();
    }

    public static function previous(): ?SeoSiteSnapshot
    {
        $snapshots = static::snapshots();

        return $snapshots->count() > 1 ? $snapshots[$snapshots->count() - 2] : null;
    }

    /**
     * De meest recente meting per actief keyword.
     *
     * Keywords die nog nooit gemeten zijn vallen weg (`latestResult` is null),
     * zodat de statistieken enkel over echte metingen gaan.
     */
    public static function latestResults(): Collection
    {
        return static::remember('latestResults', fn () => SeoKeyword::query()
            ->where('is_active', true)
            ->with('latestResult')
            ->get()
            ->map(fn ($keyword) => $keyword->latestResult)
            ->filter()
            ->values());
    }

    /**
     * Kerncijfers over de opgevolgde keywords.
     *
     * Let op het onderscheid dat ook in de advies-prompt staat: `total` is wat
     * wij tracken, niet het aantal keywords waarvoor Google het domein kent —
     * dat laatste zit in de snapshot (`organic_keywords_count`).
     *
     * @return array<string, int|float|null>
     */
    public static function keywordStats(): array
    {
        return static::remember('keywordStats', function () {
            $results = static::latestResults();
            $ranked = $results->whereNotNull('rank_group');

            return [
                'total' => SeoKeyword::where('is_active', true)->count(),
                'tracked' => $results->count(),
                'top3' => $ranked->where('rank_group', '<=', 3)->count(),
                'top10' => $ranked->where('rank_group', '<=', 10)->count(),
                'avg_position' => $ranked->count() ? round($ranked->avg('rank_group'), 1) : null,
                'in_ai_overview' => $results->where('in_ai_overview', true)->count(),
                'ai_cited' => $results->where('ai_overview_cited', true)->count(),
            ];
        });
    }

    /**
     * Sterkste stijgers en dalers t.o.v. de vorige meting.
     *
     * `delta` is positief wanneer het rangnummer daalde (= gestegen in Google).
     *
     * @return array{up: Collection, down: Collection}
     */
    public static function topMovers(int $limit = 5): array
    {
        $withDelta = static::latestResults()
            ->filter(fn ($result) => $result->delta !== null && $result->delta !== 0)
            ->map(fn ($result) => [
                'keyword' => $result->keyword->keyword,
                'rank' => $result->rank_group,
                'delta' => $result->delta,
            ]);

        return [
            'up' => $withDelta->where('delta', '>', 0)->sortByDesc('delta')->take($limit)->values(),
            'down' => $withDelta->where('delta', '<', 0)->sortBy('delta')->take($limit)->values(),
        ];
    }

    /** Het meest recente AI-advies, of null zolang er nog geen rapport draaide. */
    public static function latestReport(): ?SeoReport
    {
        return static::remember('latestReport', fn () => SeoReport::latest('captured_at')->first());
    }

    /** Wis de memoïsatie — nodig in tests en na een verse collectie. */
    public static function flush(): void
    {
        static::$memo = [];
    }

    protected static function remember(string $key, callable $callback): mixed
    {
        return static::$memo[$key] ??= $callback();
    }
}
