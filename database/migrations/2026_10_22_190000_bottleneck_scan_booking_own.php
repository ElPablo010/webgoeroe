<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Pagina /bottleneck-scan (agenda "Bottleneck Scan Algemeen"): eigen kalender in plaats
 * van de GHL-widget, met dezelfde 7 vragen als de landingspagina /website-die-verkoopt.
 * Bestaande formuliervelden blijven staan.
 */
return new class extends Migration
{
    private const SLUG = 'bottleneck-scan';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections as $section) {
            $content = $section->content;

            if ($section->section_type !== 'booking' || ($content['provider'] ?? null) !== 'ghl') {
                continue;
            }

            $section->update(['content' => [
                ...$content,
                'ghl_display' => 'own',
                'form_fields' => ! empty($content['form_fields']) ? $content['form_fields'] : [
                    ['key' => 'first_name', 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'target' => 'first_name', 'placeholder' => null],
                    ['key' => 'last_name', 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'target' => 'last_name', 'placeholder' => null],
                    ['key' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'target' => 'email', 'placeholder' => null],
                    ['key' => 'phone', 'label' => 'Telefoon', 'type' => 'tel', 'required' => false, 'target' => 'phone', 'placeholder' => null],
                    ['key' => 'website', 'label' => 'Website', 'type' => 'url', 'required' => false, 'target' => 'website', 'placeholder' => 'www.jouwbedrijf.be'],
                    ['key' => 'pain_point', 'label' => 'Wat is op dit moment je grootste uitdaging of frustratie in je business?', 'type' => 'textarea', 'required' => false, 'target' => 'custom:0VFKZFDN2wt5vFLEhhm0', 'placeholder' => null],
                    ['key' => 'hope', 'label' => 'Wat hoop je uit dit gesprek te halen?', 'type' => 'textarea', 'required' => false, 'target' => 'custom:tf63W4JsJGeN9P85OXug', 'placeholder' => null],
                ],
            ]]);
        }
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
