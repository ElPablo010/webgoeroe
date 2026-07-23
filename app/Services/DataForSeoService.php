<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client voor de DataForSEO API (https://api.dataforseo.com/v3).
 *
 * Credentials + standaard-locatie/taal komen uit de Setting-tabel
 * (zoals KitService/Stripe), niet uit git. Elke call logt zijn kost
 * zodat het verbruik opvolgbaar is.
 */
class DataForSeoService
{
    protected string $baseUrl = 'https://api.dataforseo.com/v3';

    protected ?string $login;
    protected ?string $password;

    public string $target;
    public int $locationCode;
    public string $languageCode;

    /** Som van de kost (USD) van alle calls in deze instance-levensduur. */
    public float $spent = 0.0;

    public function __construct()
    {
        $this->login = Setting::get('dataforseo_login');
        $this->password = Setting::get('dataforseo_password');
        $this->target = $this->normalizeDomain(
            Setting::get('seo_target_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST)
        );
        $this->locationCode = (int) Setting::get('seo_location_code', 2056); // België
        $this->languageCode = Setting::get('seo_language_code', 'nl');
    }

    public function isConfigured(): bool
    {
        return !empty($this->login) && !empty($this->password);
    }

    /* ---------------------------------------------------------------------
     | Account
     * ------------------------------------------------------------------- */

    /** Huidig saldo (USD) en limieten. */
    public function accountBalance(): ?array
    {
        $res = $this->get('/appendix/user_data');
        $data = $this->firstResult($res);

        if (!$data) {
            return null;
        }

        return [
            'balance' => $data['money']['balance'] ?? null,
            'currency' => 'USD',
            'timezone' => $data['timezone'] ?? null,
        ];
    }

    /* ---------------------------------------------------------------------
     | Organische zichtbaarheid (DataForSEO Labs)
     * ------------------------------------------------------------------- */

    /**
     * Alle keywords waarvoor het domein organisch rankt, met positie + volume.
     * Eén goedkope Labs-call i.p.v. 100 losse SERP-queries.
     */
    public function rankedKeywords(?string $target = null, int $limit = 1000): ?array
    {
        $res = $this->post('/dataforseo_labs/google/ranked_keywords/live', [[
            'target' => $target ?? $this->target,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
            'limit' => $limit,
            'order_by' => ['ranked_serp_element.serp_item.rank_group,asc'],
        ]]);

        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        $organic = $result['metrics']['organic'] ?? [];
        $items = [];
        foreach ($result['items'] ?? [] as $item) {
            $kw = $item['keyword_data'] ?? [];
            $serp = $item['ranked_serp_element']['serp_item'] ?? [];
            $items[] = [
                'keyword' => $kw['keyword'] ?? null,
                'search_volume' => $kw['keyword_info']['search_volume'] ?? null,
                'rank_group' => $serp['rank_group'] ?? null,
                'rank_absolute' => $serp['rank_absolute'] ?? null,
                'url' => $serp['url'] ?? null,
            ];
        }

        return [
            'total_count' => $result['total_count'] ?? count($items),
            'metrics' => $organic, // pos_1, pos_2_3, pos_4_10, ...
            'etv' => $organic['etv'] ?? null,
            'items' => $items,
        ];
    }

    /** Domein-overzicht: organisch verkeer, keyword-aantallen, domain rank. */
    public function domainRankOverview(?string $target = null): ?array
    {
        $res = $this->post('/dataforseo_labs/google/domain_rank_overview/live', [[
            'target' => $target ?? $this->target,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
        ]]);

        $result = $this->firstResult($res);
        $metrics = $result['items'][0]['metrics']['organic'] ?? ($result['metrics']['organic'] ?? null);

        return $metrics ?: null;
    }

    /**
     * Zoekvolume (Google) voor een lijst keywords in één Labs-call.
     * De SERP-tracking levert geen volume; dit vult die kolom. Onbekende
     * keywords komen als null terug (tonen als "—").
     *
     * @param  string[]  $keywords
     * @return array<string,int|null>  keyword => search_volume
     */
    public function keywordSearchVolumes(array $keywords): array
    {
        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords))));
        if (empty($keywords)) {
            return [];
        }

        $map = [];
        // keyword_overview accepteert max 700 keywords per call.
        foreach (array_chunk($keywords, 700) as $chunk) {
            $res = $this->post('/dataforseo_labs/google/keyword_overview/live', [[
                'keywords' => array_values($chunk),
                'location_code' => $this->locationCode,
                'language_code' => $this->languageCode,
            ]]);

            $result = $this->firstResult($res);
            foreach ($result['items'] ?? [] as $item) {
                $kw = $item['keyword'] ?? null;
                if ($kw !== null) {
                    // DataForSEO geeft keywords in kleine letters terug; normaliseer
                    // zodat de lookup (op mb_strtolower van het keyword) altijd matcht.
                    $map[mb_strtolower($kw)] = $item['keyword_info']['search_volume'] ?? null;
                }
            }
        }

        return $map;
    }

    /* ---------------------------------------------------------------------
     | Positie-tracking + GEO (SERP API, live)
     * ------------------------------------------------------------------- */

    /**
     * Haal de live Google-SERP voor één keyword op en bepaal:
     *  - onze positie (rank_group/rank_absolute) als ons domein voorkomt
     *  - of er een AI Overview verschijnt en of ons domein erin geciteerd wordt (GEO)
     */
    public function serpForKeyword(string $keyword, ?string $target = null): ?array
    {
        $target = $this->normalizeDomain($target ?? $this->target);

        $res = $this->post('/serp/google/organic/live/advanced', [[
            'keyword' => $keyword,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
            'device' => 'desktop',
            'depth' => 100,
            'load_async_ai_overview' => true,
        ]]);

        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        return $this->parseSerpResult($result, $target);
    }

    /**
     * Goedkope, asynchrone bulk-tracking via de standaard task-queue.
     * Post alle keywords, geeft de keyword=>task_id mapping terug.
     * Kost ±10× minder dan live-calls; resultaten worden later met
     * serpTaskGet() opgehaald.
     */
    public function serpTaskPost(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $tasks = array_map(fn ($kw) => [
            'keyword' => $kw,
            'location_code' => $this->locationCode,
            'language_code' => $this->languageCode,
            'device' => 'desktop',
            'depth' => 100,
            // Hoge prioriteit: standaard-tasks blijven vaak 30+ min "in queue"
            // waardoor posities niet vulden; met priority 2 zijn ze in ~15-60s klaar.
            'priority' => 2,
        ], array_values($keywords));

        $res = $this->post('/serp/google/organic/task_post', $tasks);

        $map = [];
        foreach ($res['tasks'] ?? [] as $task) {
            $kw = $task['data']['keyword'] ?? null;
            $id = $task['id'] ?? null;
            if ($kw && $id) {
                $map[$kw] = $id;
            }
        }

        return $map;
    }

    /** Lijst van task-id's waarvan het resultaat klaar is om op te halen. */
    public function serpTasksReady(): array
    {
        $res = $this->get('/serp/google/organic/tasks_ready');
        $ids = [];
        foreach ($res['tasks'][0]['result'] ?? [] as $item) {
            if (!empty($item['id'])) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }

    /** Haal en parse één afgeronde SERP-task. */
    public function serpTaskGet(string $taskId, ?string $target = null): ?array
    {
        $target = $this->normalizeDomain($target ?? $this->target);
        $res = $this->get("/serp/google/organic/task_get/advanced/{$taskId}");
        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        return $this->parseSerpResult($result, $target);
    }

    /** Gedeelde parser: positie van ons domein + AI-overview-detectie. */
    protected function parseSerpResult(array $result, string $target): array
    {
        $rankGroup = null;
        $rankAbsolute = null;
        $url = null;
        $features = [];
        $inAiOverview = false;
        $aiCited = false;

        foreach ($result['items'] ?? [] as $item) {
            $type = $item['type'] ?? null;
            if ($type) {
                $features[$type] = ($features[$type] ?? 0) + 1;
            }

            if ($type === 'organic' && $rankGroup === null && $this->domainMatches($item['domain'] ?? ($item['url'] ?? ''), $target)) {
                $rankGroup = $item['rank_group'] ?? null;
                $rankAbsolute = $item['rank_absolute'] ?? null;
                $url = $item['url'] ?? null;
            }

            if ($type === 'ai_overview') {
                $inAiOverview = true;
                if ($this->aiOverviewCitesDomain($item, $target)) {
                    $aiCited = true;
                }
            }
        }

        return [
            'keyword' => $result['keyword'] ?? null,
            'rank_group' => $rankGroup,
            'rank_absolute' => $rankAbsolute,
            'url' => $url,
            'serp_features' => $features,
            'in_ai_overview' => $inAiOverview,
            'ai_overview_cited' => $aiCited,
        ];
    }

    /* ---------------------------------------------------------------------
     | Backlinks
     * ------------------------------------------------------------------- */

    public function backlinksSummary(?string $target = null): ?array
    {
        $res = $this->post('/backlinks/summary/live', [[
            'target' => $target ?? $this->target,
            'internal_list_limit' => 10,
            'backlinks_status_type' => 'live',
        ]]);

        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        return [
            'backlinks' => $result['backlinks'] ?? null,
            'referring_domains' => $result['referring_domains'] ?? null,
            'rank' => $result['rank'] ?? null,
            'broken_backlinks' => $result['broken_backlinks'] ?? null,
            'referring_main_domains' => $result['referring_main_domains'] ?? null,
        ];
    }

    /* ---------------------------------------------------------------------
     | On-page audit (task-based crawl)
     * ------------------------------------------------------------------- */

    /** Start een crawl. Geeft het DataForSEO task-id terug. */
    public function onPageTaskPost(?string $target = null, int $maxCrawlPages = 100): ?string
    {
        $res = $this->post('/on_page/task_post', [[
            'target' => $target ?? $this->target,
            'max_crawl_pages' => $maxCrawlPages,
            'load_resources' => true,
            'enable_javascript' => false,
        ]]);

        return $res['tasks'][0]['id'] ?? null;
    }

    /**
     * Per-pagina resultaten van een afgeronde crawl: url + de checks-vlaggen
     * (booleans) zodat we per issue kunnen tonen wélke pagina's het betreft.
     *
     * @return array<int,array{url:string,checks:array}>
     */
    public function onPagePages(string $taskId, int $limit = 1000): array
    {
        $res = $this->post('/on_page/pages', [[
            'id' => $taskId,
            'limit' => $limit,
        ]]);

        $result = $this->firstResult($res);
        $pages = [];
        foreach ($result['items'] ?? [] as $item) {
            $url = $item['url'] ?? null;
            if ($url === null) {
                continue;
            }
            $pages[] = [
                'url' => $url,
                'checks' => $item['checks'] ?? [],
            ];
        }

        return $pages;
    }

    /** Samenvatting van een lopende/afgeronde crawl. */
    public function onPageSummary(string $taskId): ?array
    {
        $res = $this->get("/on_page/summary/{$taskId}");
        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        $info = $result['domain_info'] ?? [];
        $page = $result['page_metrics'] ?? [];

        return [
            'crawl_progress' => $result['crawl_progress'] ?? null, // 'in_progress' | 'finished'
            'crawl_status' => $result['crawl_status'] ?? null,
            'onpage_score' => isset($page['onpage_score']) ? (int) round($page['onpage_score']) : null,
            'pages_crawled' => $result['crawl_status']['pages_crawled'] ?? null,
            'checks' => $page['checks'] ?? [],
            'links_external' => $page['links_external'] ?? null,
            'links_internal' => $page['links_internal'] ?? null,
            'broken_links' => $page['broken_links'] ?? null,
            'duplicate_title' => $page['duplicate_title'] ?? null,
            'duplicate_content' => $page['duplicate_content'] ?? null,
        ];
    }

    /* ---------------------------------------------------------------------
     | GEO — LLM-zichtbaarheid (DataForSEO AI Optimization)
     * ------------------------------------------------------------------- */

    /**
     * Stel een prompt aan een AI-engine en parse of merk/domein vermeld wordt.
     * engine: 'chat_gpt' | 'gemini' | 'perplexity'
     */
    public function llmResponse(string $prompt, string $engine = 'chat_gpt'): ?array
    {
        $engines = [
            'chat_gpt' => ['endpoint' => '/ai_optimization/chat_gpt/llm_responses/live', 'model' => 'o4-mini'],
            'gemini' => ['endpoint' => '/ai_optimization/gemini/llm_responses/live', 'model' => 'gemini-2.0-flash'],
            'perplexity' => ['endpoint' => '/ai_optimization/perplexity/llm_responses/live', 'model' => 'sonar'],
        ];
        $cfg = $engines[$engine] ?? $engines['chat_gpt'];

        $res = $this->post($cfg['endpoint'], [[
            'user_prompt' => $prompt,
            'model_name' => $cfg['model'],
            'web_search' => true,
        ]]);

        $result = $this->firstResult($res);
        if (!$result) {
            return null;
        }

        $text = '';
        foreach ($result['items'] ?? [] as $item) {
            foreach ($item['sections'] ?? [] as $section) {
                $text .= ' ' . ($section['text'] ?? '');
            }
        }

        return [
            'text' => trim($text),
            'raw' => $result,
        ];
    }

    /* ---------------------------------------------------------------------
     | HTTP-helpers
     * ------------------------------------------------------------------- */

    protected function post(string $endpoint, array $payload): array
    {
        return $this->request('post', $endpoint, $payload);
    }

    protected function get(string $endpoint): array
    {
        return $this->request('get', $endpoint);
    }

    protected function request(string $method, string $endpoint, array $payload = []): array
    {
        if (!$this->isConfigured()) {
            return ['status_code' => 40100, 'status_message' => 'DataForSEO niet geconfigureerd.'];
        }

        try {
            $http = Http::withBasicAuth($this->login, $this->password)
                ->timeout(120)
                ->acceptJson();

            $response = $method === 'post'
                ? $http->post($this->baseUrl . $endpoint, $payload)
                : $http->get($this->baseUrl . $endpoint);

            $json = $response->json() ?? [];

            $cost = (float) ($json['cost'] ?? 0);
            $this->spent += $cost;

            if (($json['status_code'] ?? 0) !== 20000) {
                Log::warning('DataForSEO API-fout', [
                    'endpoint' => $endpoint,
                    'status' => $json['status_code'] ?? $response->status(),
                    'message' => $json['status_message'] ?? null,
                ]);
            } elseif ($cost > 0) {
                Log::info('DataForSEO call', ['endpoint' => $endpoint, 'cost' => $cost]);
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('DataForSEO request mislukt', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return ['status_code' => 50000, 'status_message' => $e->getMessage()];
        }
    }

    /** Het eerste result-object van de eerste task, of null. */
    protected function firstResult(array $response): ?array
    {
        $task = $response['tasks'][0] ?? null;
        if (!$task || ($task['status_code'] ?? 0) !== 20000) {
            return null;
        }

        return $task['result'][0] ?? null;
    }

    /* ---------------------------------------------------------------------
     | Domein-helpers
     * ------------------------------------------------------------------- */

    /** Strip protocol, www en pad → kale host (bv. "voorbeeld.be"). */
    public function normalizeDomain(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#^www\.#', '', $value);

        return rtrim(explode('/', $value)[0], '/');
    }

    /** Hoort een SERP-resultaat (domein of url) bij ons doeldomein? */
    protected function domainMatches(string $candidate, string $target): bool
    {
        $host = $this->normalizeDomain($candidate);

        return $host === $target || str_ends_with($host, '.' . $target);
    }

    /** Wordt ons domein geciteerd in een AI Overview-element? */
    protected function aiOverviewCitesDomain(array $aiOverview, string $target): bool
    {
        $blob = json_encode($aiOverview);
        if ($blob === false) {
            return false;
        }

        // AI Overviews citeren bronnen via reference-links; een domeinmatch in de
        // (genest gestructureerde) payload is een betrouwbare, goedkope indicator.
        return str_contains(strtolower($blob), $target);
    }
}
