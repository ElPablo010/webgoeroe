<?php

namespace App\Models;

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
        'contact' => 'Contactformulier',
    ];
}
