<?php

namespace App\Support;

/**
 * Het rekenmodel van het blok "Lekkencheck" (tot core 0.25 in de core, nu in deze site). Eén model voor elke check:
 * de vragen voeden het via hun rol, zodat een campagne nooit een eigen formule
 * nodig heeft.
 *
 * Rollen (veld `role` per vraag):
 * - `value`        opdrachtwaarde in € (W)
 * - `volume`       aanvragen per maand of per week (A)
 * - `close_rate`   hoeveel aanvragen een opdracht worden, in % (C)
 * - `leak_leads`   lek dat aanvragen kost (nooit binnengekomen): % extra aanvragen die je mist (u)
 * - `leak_clients` lek dat klanten kost (aanvraag wel binnen, geen opdracht): % verlies (l)
 * - `info`         telt niet mee (enkel voor GHL of het gesprek)
 *
 * Rekenwijze (per maand):
 *   omzet nu        O     = A × C × W
 *   slotratio zonder lekken  Cpot = min(C / Π(1 − l), max)
 *   verlies proces  Vp    = A × W × (Cpot − C)
 *   gemiste aanvragen M  = A × Σu
 *   verlies site    Vs    = M × Cpot × W
 *   verlies         V     = Vp + Vs
 *   bandbreedte     hoog  = min(V, plafond × O)   laag = min(ondergrens × V, hoog × (1 + ondergrens) / 2)
 *
 * Het plafond (instelling `max_loss`, % van de huidige omzet, standaard 100)
 * voorkomt een verlies dat ongeloofwaardig groot is tegenover de hele omzet. Een
 * lek is groen als de gekozen optie niets kost, rood bij de duurste optie van
 * die vraag, anders oranje.
 */
class LeadCheck
{
    /** Livewire-component van het formulier (tot core 0.25: `core.lead-check`). */
    public const COMPONENT = 'lead-check';

    /** Haakjes rond een ingevulde lekkencheck (vroeger Core::leadChecks()). */
    public static function hooks(): LeadCheckExtensions
    {
        return app(LeadCheckExtensions::class);
    }

    public const ROLES = [
        'value' => 'Opdrachtwaarde (€)',
        'volume' => 'Aantal aanvragen',
        'close_rate' => 'Slotratio (%)',
        'leak_leads' => 'Lek: kost aanvragen',
        'leak_clients' => 'Lek: kost klanten',
        'info' => 'Enkel info (telt niet mee)',
    ];

    public const LEAK_ROLES = ['leak_leads', 'leak_clients'];

    /** Sleutels die de check zelf in de aanvraag zet: een vraag mag ze niet gebruiken. */
    public const RESERVED_KEYS = [
        'name', 'first_name', 'last_name', 'email', 'phone', 'company', 'message',
        'result_month', 'result_year', 'lost_clients', 'missed_requests', 'top_leak', 'top_leak_advice', 'score', 'leaks', 'leak_tags', 'tracking', 'attribution', 'herkomst',
    ];

    /** Weken per maand (52 / 12). */
    private const WEEKS_PER_MONTH = 4.33;

    /**
     * @param  array<string, mixed>  $content  de inhoud van het blok
     * @param  array<string, mixed>  $answers  vraagsleutel → index van de optie (keuze) of getal
     * @return array{
     *     mode: string,
     *     monthly: array{low: float, high: float},
     *     yearly: array{low: float, high: float},
     *     clients: array{low: float, high: float},
     *     missed_requests: float,
     *     revenue: float,
     *     value: float,
     *     leaks: array<int, array{key: string, label: string, status: string, loss: float, advice: ?string, tag: ?string, answer: ?string}>,
     *     top_leak: ?string,
     *     score: array{ok: int, total: int},
     *     answers: array<string, string>,
     *     price: ?float,
     *     price_clients: ?float,
     * }
     */
    public static function calculate(array $content, array $answers): array
    {
        $questions = self::questions($content);

        $value = (float) ($content['default_value'] ?? 0);
        $volume = (float) ($content['default_volume'] ?? 0);
        $close = (float) ($content['default_close_rate'] ?? 30) / 100;
        $maxClose = max(0.01, min(1, (float) ($content['max_close_rate'] ?? 80) / 100));

        $readable = [];
        $leakAnswers = [];

        foreach ($questions as $question) {
            $answer = self::answer($question, $answers[$question['key']] ?? null);

            if ($answer === null) {
                continue;
            }

            $readable[$question['key']] = $answer['label'];

            match ($question['role']) {
                'value' => $value = $answer['value'],
                'volume' => $volume = $answer['value'] * (($question['period'] ?? 'month') === 'week' ? self::WEEKS_PER_MONTH : 1),
                'close_rate' => $close = $answer['value'] / 100,
                default => null,
            };

            if (in_array($question['role'], self::LEAK_ROLES, true)) {
                $leakAnswers[$question['key']] = $answer;
            }
        }

        $value = max(0.0, $value);
        $volume = max(0.0, $volume);
        $close = max(0.0, min($maxClose, $close));

        // Slotratio zonder lekken in de opvolging.
        $kept = 1.0;
        foreach ($questions as $question) {
            if ($question['role'] === 'leak_clients' && isset($leakAnswers[$question['key']])) {
                $kept *= 1 - self::fraction($leakAnswers[$question['key']]['value']);
            }
        }
        $potential = $close <= 0 ? 0.0 : min($close / max($kept, 0.01), $maxClose);
        $potential = max($potential, $close);

        $missedShare = 0.0;
        foreach ($questions as $question) {
            if ($question['role'] === 'leak_leads' && isset($leakAnswers[$question['key']])) {
                $missedShare += self::fraction($leakAnswers[$question['key']]['value'], max: 3);
            }
        }

        $revenue = $volume * $close * $value;
        $processLoss = $volume * $value * ($potential - $close);
        $missed = $volume * $missedShare;
        $siteLoss = $missed * $potential * $value;
        $loss = $processLoss + $siteLoss;

        $lowFactor = max(0.0, min(1, (float) ($content['low_factor'] ?? 50) / 100));
        $maxLoss = max(0.05, min(2, (float) ($content['max_loss'] ?? 100) / 100));
        $high = $revenue > 0 ? min($loss, $revenue * $maxLoss) : $loss;
        // Laag = ondergrens × verlies, maar altijd een echte band onder hoog (ook
        // als het plafond hoog sterk inperkt).
        $low = min($loss * $lowFactor, $high * (1 + $lowFactor) / 2);
        $scale = $loss > 0 ? $high / $loss : 0;
        $lowScale = $loss > 0 ? $low / $loss : 0;

        $lostClients = $missed * $potential + $volume * ($potential - $close);

        // Aandeel van elk lek in het verlies: aanvragen-lekken rechtstreeks, de
        // klanten-lekken volgens hun gewicht in Π(1 − l) (logaritmisch verdeeld).
        $clientWeights = [];
        foreach ($questions as $question) {
            if ($question['role'] === 'leak_clients' && isset($leakAnswers[$question['key']])) {
                $clientWeights[$question['key']] = -log(max(1 - self::fraction($leakAnswers[$question['key']]['value']), 0.01));
            }
        }
        $weightSum = array_sum($clientWeights);

        $leaks = [];
        $ok = 0;

        foreach ($questions as $question) {
            if (! in_array($question['role'], self::LEAK_ROLES, true)) {
                continue;
            }

            $answer = $leakAnswers[$question['key']] ?? null;
            $share = 0.0;

            if ($answer !== null && $question['role'] === 'leak_leads') {
                $share = $volume * self::fraction($answer['value'], max: 3) * $potential * $value;
            } elseif ($answer !== null && $weightSum > 0) {
                $share = $processLoss * ($clientWeights[$question['key']] ?? 0) / $weightSum;
            }

            $status = self::status($question, $answer);
            $ok += $status === 'green' ? 1 : 0;

            $leaks[] = [
                'key' => $question['key'],
                'label' => $question['leak_label'] ?: $question['question'],
                'status' => $status,
                'loss' => round($share * $scale, 2),
                'advice' => $status === 'green' ? null : ($question['advice'] ?: null),
                'tag' => $status === 'green' ? null : ($question['leak_tag'] ?: null),
                'answer' => $answer['label'] ?? null,
            ];
        }

        $top = collect($leaks)->filter(fn (array $leak): bool => $leak['status'] !== 'green')->sortByDesc('loss')->first();
        $price = filled($content['price'] ?? null) ? (float) $content['price'] : null;

        return [
            'mode' => ($content['mode'] ?? 'amount') === 'score' ? 'score' : 'amount',
            'monthly' => ['low' => round($low, 2), 'high' => round($high, 2)],
            'yearly' => ['low' => round($low * 12, 2), 'high' => round($high * 12, 2)],
            'clients' => ['low' => round($lostClients * $lowScale, 2), 'high' => round($lostClients * $scale, 2)],
            'missed_requests' => round($missed, 2),
            'revenue' => round($revenue, 2),
            'value' => $value,
            'leaks' => $leaks,
            'top_leak' => $top['label'] ?? null,
            'score' => ['ok' => $ok, 'total' => count($leaks)],
            'answers' => $readable,
            'price' => $price,
            'price_clients' => $price !== null && $value > 0 ? round($price / $value, 2) : null,
        ];
    }

    /**
     * De vragen van het blok, opgeschoond.
     *
     * @param  array<string, mixed>  $content
     * @return array<int, array{key: string, question: string, info: ?string, role: string, input: string, period: ?string, options: array<int, array{label: string, value: float}>, leak_label: ?string, leak_tag: ?string, advice: ?string, ghl_field: ?string, min: ?float, max: ?float}>
     */
    public static function questions(array $content): array
    {
        $questions = [];

        foreach (array_values((array) ($content['questions'] ?? [])) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            $role = (string) ($row['role'] ?? 'info');

            if ($key === '' || in_array($key, self::RESERVED_KEYS, true) || isset($questions[$key])) {
                continue;
            }

            $input = in_array($role, ['value', 'volume', 'close_rate'], true) && ($row['input'] ?? 'choice') === 'number' ? 'number' : 'choice';

            $questions[$key] = [
                'key' => $key,
                'question' => trim((string) ($row['question'] ?? $key)),
                'info' => filled($row['info'] ?? null) ? trim((string) $row['info']) : null,
                'role' => array_key_exists($role, self::ROLES) ? $role : 'info',
                'input' => $input,
                'period' => $role === 'volume' ? (($row['period'] ?? 'month') === 'week' ? 'week' : 'month') : null,
                'options' => array_values(array_filter(array_map(fn ($option): array => [
                    'label' => trim((string) ($option['label'] ?? '')),
                    'value' => (float) str_replace(',', '.', (string) ($option['value'] ?? 0)),
                ], (array) ($row['options'] ?? [])), fn (array $option): bool => $option['label'] !== '')),
                'leak_label' => filled($row['leak_label'] ?? null) ? trim((string) $row['leak_label']) : null,
                'leak_tag' => filled($row['leak_tag'] ?? null) ? trim((string) $row['leak_tag']) : null,
                'advice' => filled($row['advice'] ?? null) ? trim((string) $row['advice']) : null,
                'ghl_field' => filled($row['ghl_field'] ?? null) ? (string) $row['ghl_field'] : null,
                'min' => is_numeric($row['min'] ?? null) ? (float) $row['min'] : null,
                'max' => is_numeric($row['max'] ?? null) ? (float) $row['max'] : null,
            ];
        }

        return array_values(array_filter($questions, fn (array $q): bool => $q['input'] === 'number' || $q['options'] !== []));
    }

    /**
     * Placeholders voor de resultaatteksten: {laag_jaar}, {hoog_jaar},
     * {laag_maand}, {hoog_maand}, {klanten}, {score}, {totaal}, {grootste_lek},
     * {prijs_klanten}, {voornaam}.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    public static function placeholders(array $result, string $firstName = ''): array
    {
        return [
            '{laag_jaar}' => self::money($result['yearly']['low']),
            '{hoog_jaar}' => self::money($result['yearly']['high']),
            '{laag_maand}' => self::money($result['monthly']['low']),
            '{hoog_maand}' => self::money($result['monthly']['high']),
            '{klanten}' => self::range($result['clients']['low'], $result['clients']['high']),
            '{score}' => (string) $result['score']['ok'],
            '{totaal}' => (string) $result['score']['total'],
            '{lekken}' => (string) ($result['score']['total'] - $result['score']['ok']),
            '{grootste_lek}' => (string) ($result['top_leak'] ?? ''),
            '{prijs_klanten}' => $result['price_clients'] === null ? '' : self::number($result['price_clients']),
            '{voornaam}' => $firstName,
        ];
    }

    /** "€ 39.000", afgerond op honderdtallen (boven € 1.000) of tientallen. */
    public static function money(float $amount): string
    {
        $rounded = $amount >= 1000 ? round($amount, -2) : round($amount, -1);

        return "€\u{00A0}".number_format($rounded, 0, ',', '.');
    }

    /** "1 à 2", "minder dan 1", "3". */
    public static function range(float $low, float $high): string
    {
        $high = (int) round($high);

        if ($high < 1) {
            return 'minder dan 1';
        }

        $low = max(1, (int) round($low));

        return $low >= $high ? (string) $high : "{$low} à {$high}";
    }

    public static function number(float $number): string
    {
        return rtrim(rtrim(number_format($number, 1, ',', '.'), '0'), ',');
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{label: string, value: float}|null
     */
    protected static function answer(array $question, mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($question['input'] === 'number') {
            $number = (float) str_replace(',', '.', (string) $raw);

            if (! is_numeric(str_replace(',', '.', (string) $raw))) {
                return null;
            }

            $number = max($question['min'] ?? 0, $number);
            $number = $question['max'] !== null ? min($question['max'], $number) : $number;
            $label = $question['role'] === 'value' ? self::money($number) : self::number($number).($question['role'] === 'close_rate' ? '%' : '');

            return ['label' => $label, 'value' => $number];
        }

        $option = $question['options'][(int) $raw] ?? null;

        return is_numeric($raw) && $option !== null ? $option : null;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array{label: string, value: float}|null  $answer
     */
    protected static function status(array $question, ?array $answer): string
    {
        if ($answer === null || $answer['value'] <= 0) {
            return 'green';
        }

        $max = max(array_column($question['options'], 'value') ?: [0]);

        return $answer['value'] >= $max ? 'red' : 'orange';
    }

    /** Een percentage als fractie, tussen 0 en $max. */
    protected static function fraction(float $percent, float $max = 0.95): float
    {
        return max(0.0, min($max, $percent / 100));
    }
}
