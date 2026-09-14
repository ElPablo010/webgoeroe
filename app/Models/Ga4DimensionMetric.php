<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Geaggregeerde Analytics-cijfers per pagina of per kanaal, over een periode
 * (standaard: de voorbije 28 dagen).
 */
class Ga4DimensionMetric extends Model
{
    public const DIMENSION_PAGE = 'page';

    public const DIMENSION_CHANNEL = 'channel';

    protected $fillable = [
        'property_id',
        'period_start',
        'period_end',
        'dimension',
        'value',
        'value_hash',
        'sessions',
        'page_views',
        'engaged_sessions',
        'avg_session_seconds',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sessions' => 'integer',
        'page_views' => 'integer',
        'engaged_sessions' => 'integer',
        'avg_session_seconds' => 'float',
    ];

    public static function hashFor(string $value): string
    {
        return md5($value);
    }

    public function scopePages(Builder $q): Builder
    {
        return $q->where('dimension', self::DIMENSION_PAGE);
    }

    public function scopeChannels(Builder $q): Builder
    {
        return $q->where('dimension', self::DIMENSION_CHANNEL);
    }

    /** Enkel de rijen van de meest recente ingelezen periode. */
    public function scopeLatestPeriod(Builder $q, string $propertyId): Builder
    {
        $end = static::where('property_id', $propertyId)->max('period_end');

        return $q->where('property_id', $propertyId)->where('period_end', $end);
    }
}
