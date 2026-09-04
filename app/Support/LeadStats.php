<?php

namespace App\Support;

use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Eén bron van waarheid voor de leads-cijfers ("Groei"-meetlaag), zoals
 * SeoStats dat is voor de SEO-cijfers. Alles wat het Leads-scherm toont komt
 * hier vandaan, zodat kop-cijfers, grafiek en tabellen nooit uiteenlopen.
 *
 * Lage volumes, dus groepering in PHP — dat werkt identiek op elke database.
 */
class LeadStats
{
    /** Zonder tabel (migratie nog niet gedraaid) toont het scherm een melding. */
    public static function available(): bool
    {
        return Schema::hasTable('leads');
    }

    /**
     * @return array{total:int,last28:int,previous28:int,thisMonth:int,goal:?int,baseline:?int,liveSince:?Carbon}
     */
    public static function summary(): array
    {
        $now = Carbon::now();
        $goal = Setting::get('seo_goal_leads_month');
        $baseline = Setting::get('seo_leads_baseline');

        return [
            'total' => Lead::count(),
            'last28' => Lead::where('created_at', '>=', $now->copy()->subDays(28))->count(),
            'previous28' => Lead::whereBetween('created_at', [$now->copy()->subDays(56), $now->copy()->subDays(28)])->count(),
            'thisMonth' => Lead::where('created_at', '>=', $now->copy()->startOfMonth())->count(),
            'goal' => filled($goal) ? (int) $goal : null,
            'baseline' => filled($baseline) ? (int) $baseline : null,
            'liveSince' => self::liveSince(),
        ];
    }

    public static function liveSince(): ?Carbon
    {
        $value = Setting::get('seo_live_since');

        try {
            return filled($value) ? Carbon::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Leads per maand, de laatste $months maanden inclusief de huidige.
     *
     * @return array<int, array{key:string,label:string,count:int,live:bool}>
     */
    public static function monthly(int $months = 12): array
    {
        $now = Carbon::now();
        $start = $now->copy()->subMonths($months - 1)->startOfMonth();
        $liveSince = self::liveSince();

        $buckets = [];
        $cursor = $start->copy();
        while ($cursor <= $now) {
            $buckets[$cursor->format('Y-m')] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('m/Y'),
                'count' => 0,
                'live' => $liveSince !== null && $liveSince->format('Y-m') === $cursor->format('Y-m'),
            ];
            $cursor->addMonth();
        }

        Lead::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->each(function (Lead $lead) use (&$buckets) {
                $key = $lead->created_at->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['count']++;
                }
            });

        return array_values($buckets);
    }

    /**
     * @return array<int, array{key:string,label:string,count:int}>
     */
    public static function byChannel(int $days = 90): array
    {
        return self::distribution('channel', $days, fn (?string $channel) => Attribution::CHANNEL_LABELS[$channel] ?? ($channel ?: 'Onbekend'));
    }

    /**
     * @return array<int, array{key:string,label:string,count:int}>
     */
    public static function byType(int $days = 90): array
    {
        return self::distribution('lead_type', $days, fn (?string $type) => Lead::TYPE_LABELS[$type]
            ?? FormSubmission::TYPE_LABELS[$type]
            ?? ucfirst((string) $type));
    }

    /**
     * @return array<int, array{key:string,label:string,count:int}>
     */
    public static function byLandingPath(int $days = 90, int $limit = 10): array
    {
        return array_slice(self::distribution('landing_path', $days, fn (?string $path) => $path ?: 'Onbekend', skipNull: true), 0, $limit);
    }

    /** @return Collection<int, Lead> */
    public static function recent(int $limit = 50): Collection
    {
        return Lead::latest()->limit($limit)->get();
    }

    /**
     * @return array<int, array{key:string,label:string,count:int}>
     */
    protected static function distribution(string $column, int $days, \Closure $label, bool $skipNull = false): array
    {
        $query = Lead::where('created_at', '>=', Carbon::now()->subDays($days));
        if ($skipNull) {
            $query->whereNotNull($column);
        }

        return $query->get([$column])
            ->groupBy(fn (Lead $lead) => (string) $lead->{$column})
            ->map->count()
            ->sortDesc()
            ->map(fn (int $count, string $key) => ['key' => $key, 'label' => $label($key ?: null), 'count' => $count])
            ->values()
            ->all();
    }
}
