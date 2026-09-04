<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * First-party herkomstbepaling van een websitebezoeker ("Groei"-meetlaag).
 *
 * Bij het eerste GET-verzoek van een sessie wordt de first touch vastgelegd
 * (landingspagina, referrer, utm's) en geclassificeerd naar een kanaal.
 * Sessie-only, bewust zonder extra cookie: geen consent-vraagstuk, niets te
 * blokkeren door adblockers — dit is de server die registreert wat er op de
 * eigen site gebeurt. Bewust GEEN GA4: consent mode maakt die cijfers
 * structureel onvolledig, en de eigen database kent de conversies exact.
 */
class Attribution
{
    public const SESSION_KEY = 'wg_first_touch';

    public const CHANNEL_ADS = 'ads';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_AI = 'ai';

    public const CHANNEL_ORGANIC = 'organic';

    public const CHANNEL_SOCIAL = 'social';

    public const CHANNEL_REFERRAL = 'referral';

    public const CHANNEL_DIRECT = 'direct';

    /** Nederlandse labels voor de UI — sleutel = kanaal. */
    public const CHANNEL_LABELS = [
        self::CHANNEL_ORGANIC => 'Organisch (Google)',
        self::CHANNEL_AI => 'AI-assistenten',
        self::CHANNEL_SOCIAL => 'Social media',
        self::CHANNEL_ADS => 'Advertenties',
        self::CHANNEL_EMAIL => 'E-mail',
        self::CHANNEL_REFERRAL => 'Verwijzing',
        self::CHANNEL_DIRECT => 'Direct',
    ];

    /** Hosts van zoekmachines (prefix met punt = alle TLD's, bv. google.be/.com/.nl). */
    private const SEARCH_HOSTS = [
        'google.', 'bing.com', 'duckduckgo.com', 'search.yahoo.com',
        'ecosia.org', 'startpage.com', 'qwant.com', 'search.brave.com',
    ];

    /** Hosts van AI-assistenten — de meetlat naast de GEO-checks. */
    private const AI_HOSTS = [
        'chatgpt.com', 'chat.openai.com', 'perplexity.ai', 'gemini.google.com',
        'copilot.microsoft.com', 'claude.ai', 'you.com', 'mistral.ai',
    ];

    private const SOCIAL_HOSTS = [
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 't.co',
        'linkedin.com', 'tiktok.com', 'youtube.com', 'pinterest.com',
        'reddit.com', 'snapchat.com', 'threads.net',
    ];

    /** Leg de first touch vast in de sessie, als die er nog niet is. */
    public static function capture(Request $request): void
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $referrerHost = self::externalReferrerHost($request);

        $request->session()->put(self::SESSION_KEY, [
            'channel' => self::classify($referrerHost, $request->query()),
            'referrer_host' => $referrerHost,
            'landing_path' => '/' . ltrim($request->path(), '/'),
            'utm_source' => self::cleanParam($request->query('utm_source')),
            'utm_medium' => self::cleanParam($request->query('utm_medium')),
            'utm_campaign' => self::cleanParam($request->query('utm_campaign')),
            'captured_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * De vastgelegde first touch van deze sessie, of null (bv. in een
     * context zonder sessie).
     */
    public static function current(): ?array
    {
        try {
            // Niet via request()->session(): in een Livewire-aanroep of job
            // draagt het request-object geen sessie, terwijl de store er wel is.
            $request = request();
            $session = $request->hasSession() ? $request->session() : app('session.store');

            $snapshot = $session->get(self::SESSION_KEY);

            return is_array($snapshot) ? $snapshot : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Classificeer een bezoek naar een kanaal. Volgorde is betekenisvol:
     * betaald wint van organisch (een Google Ads-klik heeft óók google als
     * referrer), e-mail en AI winnen van referral.
     */
    public static function classify(?string $referrerHost, array $query = []): string
    {
        $medium = strtolower((string) ($query['utm_medium'] ?? ''));
        $source = strtolower((string) ($query['utm_source'] ?? ''));

        if (isset($query['gclid']) || isset($query['fbclid']) || isset($query['msclkid'])
            || in_array($medium, ['cpc', 'ppc', 'paid', 'paid_social', 'display'], true)) {
            return self::CHANNEL_ADS;
        }

        if (in_array($medium, ['email', 'e-mail', 'newsletter'], true)
            || in_array($source, ['kit', 'convertkit', 'newsletter', 'mailchimp'], true)) {
            return self::CHANNEL_EMAIL;
        }

        if ($referrerHost !== null) {
            if (self::hostMatches($referrerHost, self::AI_HOSTS)) {
                return self::CHANNEL_AI;
            }

            if (self::hostMatches($referrerHost, self::SEARCH_HOSTS)) {
                return self::CHANNEL_ORGANIC;
            }

            if (self::hostMatches($referrerHost, self::SOCIAL_HOSTS)) {
                return self::CHANNEL_SOCIAL;
            }

            return self::CHANNEL_REFERRAL;
        }

        // utm's zonder referrer (bv. een QR-code of app-opening) — de bron zegt genoeg.
        if ($source !== '') {
            return self::CHANNEL_REFERRAL;
        }

        return self::CHANNEL_DIRECT;
    }

    /** Crawlers krijgen geen first touch: hun sessie converteert nooit. */
    public static function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|headless|lighthouse|pingdom|uptime/i',
            $userAgent
        );
    }

    /** De referrer-host, of null wanneer die ontbreekt of naar de site zelf wijst. */
    private static function externalReferrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        if ($host === '' || $host === strtolower((string) $request->getHost())) {
            return null;
        }

        return $host;
    }

    private static function hostMatches(string $host, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (str_ends_with($candidate, '.')) {
                // 'google.' matcht google.com, google.be, www.google.nl …
                if (str_contains($host, $candidate)) {
                    return true;
                }
            } elseif ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function cleanParam(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, 255);
    }
}
