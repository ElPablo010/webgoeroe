<?php

// Module webgoeroe/cases: instellingen van deze site (zie de config in de package).
return [
    'model' => App\Models\CaseStudy::class,

    'prefix' => 'cases',

    'in_language' => 'nl-BE',

    'index' => [
        'title' => 'Cases',
        'description' => 'Ontdek hoe De Webgoeroe bedrijven helpt groeien online.',
    ],

    // Standaard zolang Instellingen → Cases niet bewaard is (de migratie vult hem).
    'cta' => [
        'enabled' => false,
        'title' => null,
        'body' => null,
        'buttons' => [],
    ],
];
