<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_keyword_id',
        'checked_at',
        'rank_absolute',
        'rank_group',
        'previous_rank',
        'url',
        'search_volume',
        'serp_features',
        'in_ai_overview',
        'ai_overview_cited',
    ];

    protected $casts = [
        'checked_at' => 'date',
        'rank_absolute' => 'integer',
        'rank_group' => 'integer',
        'previous_rank' => 'integer',
        'search_volume' => 'integer',
        'serp_features' => 'array',
        'in_ai_overview' => 'boolean',
        'ai_overview_cited' => 'boolean',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'seo_keyword_id');
    }

    /**
     * Positieverschil t.o.v. de vorige meting.
     * Positief = gestegen (lager rangnummer), negatief = gedaald.
     */
    public function getDeltaAttribute(): ?int
    {
        if ($this->previous_rank === null || $this->rank_group === null) {
            return null;
        }

        return $this->previous_rank - $this->rank_group;
    }
}
