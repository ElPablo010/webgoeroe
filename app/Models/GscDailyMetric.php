<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén dag gemeten Google-verkeer uit Search Console (site-totaal).
 * In tegenstelling tot SeoSiteSnapshot is dit géén schatting maar
 * wat Google zelf registreerde.
 */
class GscDailyMetric extends Model
{

    protected $fillable = [
        'site_url',
        'date',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
    ];
}
