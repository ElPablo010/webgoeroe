<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoSiteSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'target',
        'location_code',
        'language_code',
        'captured_at',
        'organic_keywords_count',
        'organic_etv',
        'pos_1',
        'pos_2_3',
        'pos_4_10',
        'pos_11_20',
        'pos_21_100',
        'backlinks_count',
        'referring_domains',
        'domain_rank',
        'onpage_score',
        'raw',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'location_code' => 'integer',
        'organic_keywords_count' => 'integer',
        'organic_etv' => 'integer',
        'pos_1' => 'integer',
        'pos_2_3' => 'integer',
        'pos_4_10' => 'integer',
        'pos_11_20' => 'integer',
        'pos_21_100' => 'integer',
        'backlinks_count' => 'integer',
        'referring_domains' => 'integer',
        'domain_rank' => 'integer',
        'onpage_score' => 'integer',
        'raw' => 'array',
    ];

    /** Keywords in de top 10 (pos 1 + 2-3 + 4-10). */
    public function getTop10Attribute(): int
    {
        return (int) $this->pos_1 + (int) $this->pos_2_3 + (int) $this->pos_4_10;
    }
}
