<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Herkomst van een advertentiebezoek (UTM + sector) uit wat de browser meestuurt
 * in `tracking.attribution`. Het meten naar Meta zelf doet de core
 * (Webgoeroe\Core\Integrations\Meta\ConversionsApi); dit bewaart enkel de herkomst
 * bij de inzending.
 */
class AdAttribution
{
    public const RULES = [
        'tracking' => ['nullable', 'array'],
        'tracking.consent' => ['nullable', 'boolean'],
        'tracking.event_id' => ['nullable', 'string', 'max:64'],
        'tracking.fbp' => ['nullable', 'string', 'max:200'],
        'tracking.fbc' => ['nullable', 'string', 'max:300'],
        'tracking.attribution' => ['nullable', 'array', 'max:10'],
        'tracking.attribution.*' => ['nullable', 'string', 'max:150'],
    ];

    private const KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'sector'];

    /** @return array<string, string> */
    public static function from(Request $request): array
    {
        $raw = (array) $request->input('tracking.attribution', []);

        return collect(Arr::only($raw, self::KEYS))
            ->map(fn ($v) => Str::limit(trim((string) $v), 150, ''))
            ->filter()
            ->all();
    }

    /** Eén regel voor in GHL: "bron | campagne | advertentie | sector". */
    public static function line(array $attribution): string
    {
        return collect([
            $attribution['utm_source'] ?? null,
            $attribution['utm_campaign'] ?? null,
            $attribution['utm_content'] ?? null,
            $attribution['sector'] ?? null,
        ])->filter()->implode(' | ');
    }

    /** @return array{attribution: ?array, herkomst: ?string} */
    public static function data(Request $request): array
    {
        $attribution = self::from($request);

        return ['attribution' => $attribution ?: null, 'herkomst' => self::line($attribution) ?: null];
    }
}
