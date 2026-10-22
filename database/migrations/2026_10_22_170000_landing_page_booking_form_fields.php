<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Landingspagina /website-die-verkoopt: de formuliervelden van de eigen kalender,
 * zoals in het vroegere GHL-formulier "Bottleneck Scan aanvraag", elk gekoppeld aan
 * het contactveld in GoHighLevel. Daarna beheer je ze in het boeking-blok.
 * Enkel als er nog geen velden ingesteld zijn.
 */
return new class extends Migration
{
    private const SLUG = 'website-die-verkoopt';

    public function up(): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections as $section) {
            $content = $section->content;

            if ($section->section_type !== 'booking' || ($content['provider'] ?? null) !== 'ghl' || ! empty($content['form_fields'])) {
                continue;
            }

            $section->update(['content' => [...$content, 'form_fields' => [
                ['key' => 'first_name', 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'target' => 'first_name', 'placeholder' => null],
                ['key' => 'last_name', 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'target' => 'last_name', 'placeholder' => null],
                ['key' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'target' => 'email', 'placeholder' => null],
                ['key' => 'phone', 'label' => 'Telefoon', 'type' => 'tel', 'required' => false, 'target' => 'phone', 'placeholder' => null],
                ['key' => 'website', 'label' => 'Website', 'type' => 'url', 'required' => false, 'target' => 'website', 'placeholder' => 'www.jouwbedrijf.be'],
                ['key' => 'pain_point', 'label' => 'Wat is op dit moment je grootste uitdaging of frustratie in je business?', 'type' => 'textarea', 'required' => false, 'target' => 'custom:0VFKZFDN2wt5vFLEhhm0', 'placeholder' => null],
                ['key' => 'hope', 'label' => 'Wat hoop je uit dit gesprek te halen?', 'type' => 'textarea', 'required' => false, 'target' => 'custom:tf63W4JsJGeN9P85OXug', 'placeholder' => null],
            ]]]);
        }
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
