<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Webgoeroe\Core\Models\FormSubmission as CoreModel;

/**
 * Basis uit de package webgoeroe/core (opslaan, typeLabel(), lead-registratie
 * voor seo-growth).
 */
class FormSubmission extends CoreModel
{
    /**
     * Labels per formuliertype (admin + e-mail-onderwerp + leads in
     * seo-growth). Alfabetisch.
     */
    public const TYPE_LABELS = [
        'booking' => 'Boeking (eigen kalender)',
        'contact' => 'Contactformulier',
        'omzetcheck' => 'Omzetcheck',
        'speed_to_lead_start' => 'Speed to lead: starten',
    ];

    /**
     * page_url is een varchar(255); links uit Meta-ads (fbclid, utm) zijn
     * langer en lieten de inzending crashen. Afkappen, de herkomst staat
     * apart in data.attribution.
     */
    protected function pageUrl(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::limit($value, 255, ''));
    }
}
