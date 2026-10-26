<?php

namespace App\Services\Meta;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Meta Conversions API (serverevents) naast de pixel in de browser. Dezelfde
 * `event_id` aan beide kanten laat Meta ontdubbelen. Alleen verstuurd als de
 * bezoeker marketingcookies aanvaardde (de browser geeft dat mee in `tracking`)
 * en pixel-ID + token ingesteld zijn. Faalt nooit hard: een fout komt in de log.
 */
class ConversionsApi
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

    private const ATTRIBUTION_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'sector'];

    public function isConfigured(): bool
    {
        return filled(config('services.meta.pixel_id')) && filled(config('services.meta.access_token'));
    }

    /**
     * Herkomst van de bezoeker (UTM + sector) uit wat de browser meestuurt.
     *
     * @return array<string, string>
     */
    public static function attribution(Request $request): array
    {
        $raw = (array) $request->input('tracking.attribution', []);

        return collect(Arr::only($raw, self::ATTRIBUTION_KEYS))
            ->map(fn ($v) => Str::limit(trim((string) $v), 150, ''))
            ->filter()
            ->all();
    }

    /** Eén regel voor in GHL: "bron | campagne | advertentie | sector". */
    public static function attributionLine(array $attribution): string
    {
        return collect([
            $attribution['utm_source'] ?? null,
            $attribution['utm_campaign'] ?? null,
            $attribution['utm_content'] ?? null,
            $attribution['sector'] ?? null,
        ])->filter()->implode(' | ');
    }

    /**
     * @param  array{email?: string, first_name?: string}  $user
     */
    public function send(string $event, Request $request, array $user, array $custom = []): bool
    {
        if (! $this->isConfigured() || ! $request->boolean('tracking.consent')) {
            return false;
        }

        $userData = array_filter([
            'em' => isset($user['email']) ? [hash('sha256', Str::lower(trim($user['email'])))] : null,
            'fn' => isset($user['first_name']) ? [hash('sha256', Str::lower(trim($user['first_name'])))] : null,
            'fbp' => $request->input('tracking.fbp'),
            'fbc' => $request->input('tracking.fbc'),
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
        ]);

        $payload = array_filter([
            'data' => [array_filter([
                'event_name' => $event,
                'event_time' => time(),
                'event_id' => $request->input('tracking.event_id'),
                'action_source' => 'website',
                'event_source_url' => $request->headers->get('referer'),
                'user_data' => $userData,
                'custom_data' => $custom ?: null,
            ])],
            'test_event_code' => config('services.meta.test_event_code'),
        ]);

        try {
            $response = Http::asJson()->timeout(5)->post(
                sprintf('https://graph.facebook.com/%s/%s/events', config('services.meta.api_version'), config('services.meta.pixel_id')),
                $payload + ['access_token' => config('services.meta.access_token')],
            );

            if (! $response->successful()) {
                Log::warning('Meta serverevent mislukt.', ['event' => $event, 'status' => $response->status(), 'body' => $response->body()]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Meta niet bereikbaar.', ['event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
