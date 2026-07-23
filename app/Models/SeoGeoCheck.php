<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoGeoCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt',
        'engine',
        'checked_at',
        'brand_mentioned',
        'domain_cited',
        'mention_rank',
        'response_excerpt',
        'raw',
    ];

    protected $casts = [
        'checked_at' => 'date',
        'brand_mentioned' => 'boolean',
        'domain_cited' => 'boolean',
        'mention_rank' => 'integer',
        'raw' => 'array',
    ];
}
