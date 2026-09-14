<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eén dag gemeten gedrag op de site, uit Google Analytics 4 (site-totaal).
 *
 * Ligt structureel lager dan GscDailyMetric: Analytics telt enkel bezoekers
 * die analytische cookies aanvaardden, Search Console telt elke klik.
 */
class Ga4DailyMetric extends Model
{
    protected $fillable = [
        'property_id',
        'date',
        'sessions',
        'active_users',
        'page_views',
        'engaged_sessions',
        'avg_session_seconds',
    ];

    protected $casts = [
        'date' => 'date',
        'sessions' => 'integer',
        'active_users' => 'integer',
        'page_views' => 'integer',
        'engaged_sessions' => 'integer',
        'avg_session_seconds' => 'float',
    ];
}
