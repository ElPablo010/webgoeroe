<?php

// Module webgoeroe/blog: instellingen van deze site (zie de config in de package).
return [
    'model' => App\Models\Post::class,

    'prefix' => 'blog',

    'default_author' => 'De Webgoeroe',

    'in_language' => 'nl-BE',

    'index' => [
        'title' => 'Artikels',
        'description' => 'Praktische inzichten over websites, AI-tools en digitale groei — voor ondernemers die slim willen werken.',
    ],

    // Standaard zolang Instellingen → Blog niet bewaard is (de migratie vult hem).
    'cta' => [
        'enabled' => false,
        'title' => null,
        'body' => null,
        'buttons' => [],
    ],
];
