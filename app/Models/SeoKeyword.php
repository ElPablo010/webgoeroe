<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeoKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword',
        'location_code',
        'language_code',
        'tag',
        'is_active',
    ];

    protected $casts = [
        'location_code' => 'integer',
        'is_active' => 'boolean',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(SeoKeywordResult::class);
    }

    /**
     * De meest recente meting, als echte HasOne — zo kan een Filament-kolom
     * er rechtstreeks op sorteren en filteren via `latestResult.rank_group`.
     *
     * Ook `id` in de sleutel, anders is de winnaar willekeurig wanneer twee
     * metingen dezelfde `checked_at` hebben (bv. na een handmatige verversing
     * op dezelfde dag als de cron).
     */
    public function latestResult(): HasOne
    {
        return $this->hasOne(SeoKeywordResult::class)->ofMany([
            'checked_at' => 'max',
            'id' => 'max',
        ]);
    }
}
