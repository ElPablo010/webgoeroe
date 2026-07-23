<?php

namespace App\Services;

use App\Models\SeoGeoCheck;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordResult;
use App\Models\SeoSiteSnapshot;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Verzamelt en bewaart SEO-data via DataForSeoService.
 * Gedeeld door de "ververs nu"-knoppen (admin) en het wekelijkse cron-command,
 * zodat er één bron van waarheid is voor hoe data wordt opgehaald en weggeschreven.
 */
class SeoCollector
{
    /** keyword => search_volume, opgehaald per verzamelcyclus (één Labs-call). */
    protected array $volumeMap = [];

    public function __construct(protected DataForSeoService $api)
    {
    }

    public function isConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    /**
     * Domein-overzicht (organische zichtbaarheid + backlinks) → SeoSiteSnapshot.
     * Goedkoop: enkele Labs-calls. Backlinks is optioneel (apart abonnement).
     */
    public function collectSiteSnapshot(): ?SeoSiteSnapshot
    {
        $overview = $this->api->domainRankOverview();
        if (!$overview) {
            return null;
        }

        $backlinks = $this->api->backlinksSummary(); // null als niet in abonnement

        return SeoSiteSnapshot::create([
            'target' => $this->api->target,
            'location_code' => $this->api->locationCode,
            'language_code' => $this->api->languageCode,
            'captured_at' => Carbon::today(),
            'organic_keywords_count' => $overview['count'] ?? null,
            'organic_etv' => isset($overview['etv']) ? (int) round($overview['etv']) : null,
            'pos_1' => $overview['pos_1'] ?? null,
            'pos_2_3' => $overview['pos_2_3'] ?? null,
            'pos_4_10' => $overview['pos_4_10'] ?? null,
            'pos_11_20' => $overview['pos_11_20'] ?? null,
            'pos_21_100' => $this->sumPositions($overview, ['pos_21_30', 'pos_31_40', 'pos_41_50', 'pos_51_60', 'pos_61_70', 'pos_71_80', 'pos_81_90', 'pos_91_100']),
            'backlinks_count' => $backlinks['backlinks'] ?? null,
            'referring_domains' => $backlinks['referring_domains'] ?? null,
            'domain_rank' => $backlinks['rank'] ?? null,
            'onpage_score' => optional(\App\Models\SeoAuditRun::where('target', $this->api->target)->where('status', 'completed')->latest()->first())->onpage_score,
            'raw' => ['overview' => $overview, 'backlinks' => $backlinks],
        ]);
    }

    /**
     * Volledige verzamelcyclus: domein-overzicht + posities van alle actieve
     * keywords (via de goedkope task-queue, met polling). Bedoeld voor een
     * langlopende context (cron-command of queued job) — niet voor een
     * web-request, want het polt tot de SERP-tasks klaar zijn.
     *
     * @return array{snapshot: ?SeoSiteSnapshot, keywords_tracked: int, spent: float}
     */
    public function collectAll(int $maxWaitSeconds = 180): array
    {
        $snapshot = $this->collectSiteSnapshot();

        $keywords = SeoKeyword::where('is_active', true)->get();
        $tracked = 0;

        if ($keywords->isNotEmpty()) {
            // Zoekvolume in bulk ophalen (één goedkope Labs-call) zodat elke
            // opgeslagen positie meteen zijn volume meekrijgt.
            $this->volumeMap = $this->api->keywordSearchVolumes($keywords->pluck('keyword')->all());

            $map = $this->postKeywordTasks($keywords);

            if (!empty($map)) {
                // Poll tot (vrijwel) alle tasks klaar zijn, of tot de tijdslimiet.
                $deadline = time() + $maxWaitSeconds;
                $wanted = array_values($map);
                do {
                    sleep(10);
                    $ready = $this->api->serpTasksReady();
                    $readyWanted = array_intersect($wanted, $ready);
                } while (count($readyWanted) < count($wanted) && time() < $deadline);

                $tracked = $this->fetchKeywordTaskResults($map);
            }
        }

        return [
            'snapshot' => $snapshot,
            'keywords_tracked' => $tracked,
            'spent' => $this->api->spent,
        ];
    }

    /**
     * Start een asynchrone cyclus: snapshot + zoekvolume + posten van de
     * keyword-tasks. Geeft de context terug die FetchSerpResultsJob nodig
     * heeft om de posities op te halen zodra ze klaar zijn.
     *
     * @return array{map: array<string,string>, volumes: array<string,int|null>}
     */
    public function startCollection(): array
    {
        $this->collectSiteSnapshot();

        $keywords = SeoKeyword::where('is_active', true)->get();
        if ($keywords->isEmpty()) {
            return ['map' => [], 'volumes' => []];
        }

        $volumes = $this->api->keywordSearchVolumes($keywords->pluck('keyword')->all());
        $map = $this->postKeywordTasks($keywords);

        return ['map' => $map, 'volumes' => $volumes];
    }

    /**
     * Haal de reeds-afgeronde SERP-tasks uit $map op en bewaar ze. Geeft de
     * nog niet klare tasks terug zodat de aanroeper later kan herpogen.
     *
     * @param  array<string,string>  $map      keyword => task_id
     * @param  array<string,int|null>  $volumes keyword => search_volume
     * @return array{saved: int, remaining: array<string,string>}
     */
    public function fetchReadyResults(array $map, array $volumes = []): array
    {
        $this->volumeMap = $volumes;

        $keywords = SeoKeyword::whereIn('keyword', array_keys($map))->get()->keyBy('keyword');

        $saved = 0;
        $remaining = [];

        foreach ($map as $keyword => $taskId) {
            $kwModel = $keywords->get($keyword);
            if (!$kwModel) {
                continue; // keyword intussen verwijderd
            }

            // Rechtstreeks per task-id ophalen i.p.v. via de tasks_ready-lijst.
            // Die lijst toont op productie soms afgeronde tasks niet, waardoor
            // een deel van de keywords bleef hangen. serpTaskGet geeft null zolang
            // de task nog niet klaar is → dan proberen we 'm in de volgende poging.
            $data = $this->api->serpTaskGet($taskId);
            if ($data === null) {
                $remaining[$keyword] = $taskId;
                continue;
            }

            $this->saveKeywordResult($kwModel, $data);
            $saved++;
        }

        return ['saved' => $saved, 'remaining' => $remaining];
    }

    /**
     * Bulk-tracking via de goedkope task-queue.
     * Fase 1: post alle keywords → bewaar de keyword=>task_id mapping.
     */
    public function postKeywordTasks(?\Illuminate\Support\Collection $keywords = null): array
    {
        $keywords ??= SeoKeyword::where('is_active', true)->get();
        $map = $this->api->serpTaskPost($keywords->pluck('keyword')->all());

        return $map; // ['keyword' => 'task_id', ...]
    }

    /**
     * Fase 2: haal afgeronde tasks op en bewaar de resultaten.
     * $map = keyword => task_id (zoals teruggegeven door postKeywordTasks()).
     */
    public function fetchKeywordTaskResults(array $map): int
    {
        $saved = 0;
        $keywords = SeoKeyword::whereIn('keyword', array_keys($map))->get()->keyBy('keyword');

        foreach ($map as $keyword => $taskId) {
            $kwModel = $keywords->get($keyword);
            if (!$kwModel) {
                continue;
            }

            $data = $this->api->serpTaskGet($taskId);
            if ($data !== null) {
                $this->saveKeywordResult($kwModel, $data);
                $saved++;
            }
        }

        return $saved;
    }

    /** Persisteer één SERP-resultaat, met delta t.o.v. de vorige meting. */
    protected function saveKeywordResult(SeoKeyword $keyword, array $data): SeoKeywordResult
    {
        $previous = $keyword->results()->latest('checked_at')->first();

        return $keyword->results()->create([
            'checked_at' => Carbon::today(),
            'rank_absolute' => $data['rank_absolute'] ?? null,
            'rank_group' => $data['rank_group'] ?? null,
            'previous_rank' => $previous?->rank_group,
            'url' => $data['url'] ?? null,
            'search_volume' => $this->volumeMap[mb_strtolower($keyword->keyword)] ?? ($data['search_volume'] ?? null),
            'serp_features' => $data['serp_features'] ?? null,
            'in_ai_overview' => $data['in_ai_overview'] ?? false,
            'ai_overview_cited' => $data['ai_overview_cited'] ?? false,
        ]);
    }

    /* ---------------------------------------------------------------------
     | GEO — AI-zichtbaarheid
     * ------------------------------------------------------------------- */

    /**
     * De opgeslagen GEO-prompts.
     *
     * Let op: het Setting-model cast `value` naar array, dus we bewaren hier een
     * echte array — géén json_encode(). De json_decode-tak vangt oudere
     * installaties op waar de waarde nog als JSON-string is weggeschreven.
     */
    public function geoPrompts(): array
    {
        $raw = Setting::get('seo_geo_prompts');

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    public function setGeoPrompts(array $prompts): void
    {
        Setting::set('seo_geo_prompts', array_values(array_filter($prompts)));
    }

    /** Run alle (of meegegeven) GEO-prompts tegen een engine en bewaar de resultaten. */
    public function runGeoChecks(string $engine = 'chat_gpt', ?array $prompts = null): int
    {
        $prompts ??= $this->geoPrompts();
        $brand = Setting::get('seo_brand_name') ?: config('app.name');
        $domain = $this->api->target;
        $count = 0;

        foreach ($prompts as $prompt) {
            $res = $this->api->llmResponse($prompt, $engine);
            if ($res === null) {
                continue;
            }

            $text = $res['text'] ?? '';
            $lower = Str::lower($text);
            $domainCited = str_contains($lower, $domain);
            $brandMentioned = str_contains($lower, Str::lower($brand));

            SeoGeoCheck::create([
                'prompt' => Str::limit($prompt, 1000, ''),
                'engine' => $engine,
                'checked_at' => Carbon::today(),
                'brand_mentioned' => $brandMentioned,
                'domain_cited' => $domainCited,
                'mention_rank' => null,
                'response_excerpt' => Str::limit($text, 1500),
                'raw' => null,
            ]);
            $count++;
        }

        return $count;
    }

    protected function sumPositions(array $overview, array $keys): int
    {
        return (int) collect($keys)->sum(fn ($k) => (int) ($overview[$k] ?? 0));
    }
}
