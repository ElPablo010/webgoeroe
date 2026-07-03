<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Een inzending van een publiek formulier (form-sectie). Het type onderscheidt
 * de verschillende formulieren (contact, offerteaanvraag, …); de ingezonden
 * velden zitten als bag in `data` zodat elk formuliertype z'n eigen velden mag
 * hebben zonder schema-wijziging.
 */
class FormSubmission extends Model
{
    protected $fillable = ['type', 'data', 'page_url', 'ip', 'read_at'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Menselijke labels per formuliertype (admin + e-mail-onderwerp).
     * Voeg hier per project nieuwe types toe — alfabetisch.
     */
    public const TYPE_LABELS = [
        'contact' => 'Contactformulier',
        // 'quote' => 'Offerteaanvraag',
    ];

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
    }
}
