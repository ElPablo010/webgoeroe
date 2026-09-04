<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Geaggregeerde Search Console-cijfers per zoekterm of per pagina,
 * over een periode (standaard: de voorbije 28 dagen).
 */
class GscDimensionMetric extends Model
{
    public const DIMENSION_QUERY = 'query';
    public const DIMENSION_PAGE = 'page';

    protected $fillable = [
        'site_url',
        'period_start',
        'period_end',
        'dimension',
        'value',
        'value_hash',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
    ];

    public static function hashFor(string $value): string
    {
        return md5($value);
    }

    public function scopeQueries(Builder $q): Builder
    {
        return $q->where('dimension', self::DIMENSION_QUERY);
    }

    public function scopePages(Builder $q): Builder
    {
        return $q->where('dimension', self::DIMENSION_PAGE);
    }

    /** Enkel de rijen van de meest recente ingelezen periode. */
    public function scopeLatestPeriod(Builder $q, string $siteUrl): Builder
    {
        $end = static::where('site_url', $siteUrl)->max('period_end');

        return $q->where('site_url', $siteUrl)->where('period_end', $end);
    }
}
