<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoAuditRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'target',
        'status',
        'task_id',
        'onpage_score',
        'pages_crawled',
        'pages_with_issues',
        'critical_count',
        'warning_count',
        'checks',
        'top_issues',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'onpage_score' => 'integer',
        'pages_crawled' => 'integer',
        'pages_with_issues' => 'integer',
        'critical_count' => 'integer',
        'warning_count' => 'integer',
        'checks' => 'array',
        'top_issues' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
