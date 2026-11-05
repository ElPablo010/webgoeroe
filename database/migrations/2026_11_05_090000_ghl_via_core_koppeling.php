<?php

use Illuminate\Database\Migrations\Migration;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Integrations\GoHighLevel\SubmissionSync;
use Webgoeroe\Core\Support\Modules;

/**
 * Omzetcheck en speed to lead gaan voortaan via de GHL-koppeling van de core
 * (Instellingen → Integraties) in plaats van eigen code. Zet de module aan en
 * neemt de tags, velden en bron over die vroeger in config/services.php
 * stonden. Een type dat al ingesteld is, blijft zoals het is. De boeking
 * (eigen kalender) maakt zelf contact en afspraak in GHL en blijft hier uit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $attribution = config('services.gohighlevel.attribution_field');
        $stored = (array) Core::model('setting')::get(SubmissionSync::SETTING, []);

        $stored['omzetcheck'] ??= [
            'enabled' => true,
            'tags' => ['campagne-websites', 'omzetcheck-ingevuld'],
            'source' => 'Omzetcheck website',
            'fields' => array_values(array_filter([
                ['form' => 'amount', 'ghl' => 'pTNAaeGjLM6rvWfEsPgb'],
                ['form' => 'extra_leads', 'ghl' => 'HaBaw5mnNLpjVKmOCa3f'],
                ['form' => 'sector', 'ghl' => 'erEowVlDBPvqgg0wKrBR'],
                filled($attribution) ? ['form' => 'herkomst', 'ghl' => $attribution] : null,
            ])),
        ];

        $stored['speed_to_lead_start'] ??= [
            'enabled' => true,
            'tags' => ['lp-elektriciens-start'],
            'source' => 'Landingspagina speed to lead elektriciens',
            'fields' => [],
        ];

        Core::model('setting')::set(SubmissionSync::SETTING, $stored);
        Modules::save([Modules::GHL => true]);
    }
};
