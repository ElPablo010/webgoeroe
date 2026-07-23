<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén concrete, geprioriteerde SEO-verbeteractie: het probleem uit het
 * wekelijkse rapport plus een uitgewerkt, uitvoerbaar voorstel. Blijft
 * `pending` tot de beheerder het goedkeurt (→ `published`) of negeert
 * (→ `dismissed`). Content-acties worden toegepast via SeoActionApplier,
 * die pagina's/secties in de ingebouwde page-builder maakt.
 */
class SeoActionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_report_id',
        'action_type',
        'status',
        'priority',
        'title',
        'problem',
        'proposed',
        'page_id',
        'source_keyword',
        'metric',
        'fingerprint',
        'created_page_id',
        'result_url',
        'applied_at',
        'dismissed_at',
    ];

    protected $casts = [
        'proposed' => 'array',
        'metric' => 'array',
        'applied_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SeoReport::class, 'seo_report_id');
    }

    /** Doelpagina bij add_section / optimize_meta. */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /** Pagina die door een create_page-actie is aangemaakt. */
    public function createdPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'created_page_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
