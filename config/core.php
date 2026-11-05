<?php

/*
 * Site-basis (webgoeroe/core) — enkel wat op De WebGoeroe afwijkt van de
 * package. De rest: vendor/webgoeroe/core/config/core.php.
 */

return [

    // Routes zoals in de package, maar zonder de boekingsroutes van de core
    // (core 0.15.1): De WebGoeroe gebruikt nog zijn eigen BookingController
    // (routes/web.php). Weg te halen zodra de site op de core-kalender overstapt
    // (branch ghl-core).
    'routes' => [
        'seo' => true,
        'pages' => true,
        'exclude' => ['admin', 'login', 'livewire', 'storage', '_debugbar', 'design'],
        'booking' => false,
    ],

    // Enkel Nederlands op de root (geen prefix, geen taalschakelaar, geen hreflang).
    'locales' => [
        'nl' => 'NL',
    ],

    // Dark-mode design: 'dark' is de standaard. 'light' is een iets lichtere
    // donkere tint (kaarten), 'white' de uitzondering. Alles behalve wit is donker.
    'backgrounds' => [
        'default' => 'dark',
        'options' => [
            'dark' => ['label' => 'Donker (standaard)', 'classes' => 'bg-[#050507] text-white'],
            'light' => ['label' => 'Donker licht (kaarten)', 'classes' => 'bg-[#0c0c10] text-white'],
            'primary' => ['label' => 'Primair (merkkleur)', 'classes' => 'bg-[#050507] text-white'],   // donker, consistentie over de pagina
            'white' => ['label' => 'Wit (uitzonderlijk)', 'classes' => 'bg-white text-slate-900'],
            'transparent' => ['label' => 'Transparant', 'classes' => 'bg-transparent text-white'],
        ],
        'dark' => ['dark', 'light', 'primary', 'transparent'],
    ],

    // Blokken: calculator, case_results en cases_grid (eigen aan deze site) en de
    // contactgegevens op het formulierblok staan in AppServiceProvider.
    'blocks' => [
        'options' => [
            // Beeld naast de tekst of als achtergrond (keuze per hero).
            'hero' => [
                'image_layout' => true,
            ],
            'reviews' => [
                'columns' => false,
                'intro' => false,
            ],
            'cards' => [
                'badge' => false,
                'journey' => true,
            ],
            'problem_recognition' => [
                'journey' => true,
            ],
            'text_media' => [
                'media_shape' => false,
            ],
            // Juridische teksten (cookie-, privacybeleid, voorwaarden) met tabellen.
            'text' => [
                'toolbar' => [
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'blockquote'],
                    ['bulletList', 'orderedList'],
                    ['table'],
                    ['undo', 'redo'],
                ],
            ],
        ],
    ],

    // Eigen favicon op de Header-pagina (leeg = het logo); geen LinkedIn in de footer.
    'header' => [
        'favicon' => true,
    ],

    'footer' => [
        'linkedin' => false,
    ],

    // Pixelbudget voor uploads én de MCP-tool upload_media_from_url (~12 MP,
    // bv. 4000×3000): Combell shared hosting heeft weinig PHP-geheugen.
    'media' => [
        'max_pixels' => 12_000_000,
    ],

    /*
     * Opvolging van aanvragen (core 0.14): deze formulieren volgt GoHighLevel
     * al op (mails, pipeline), dus geen bevestigingsmail, herinnering of
     * "Te beantwoorden". Aan te passen op Leads → Opvolging.
     */
    'follow_up' => [
        'types' => [
            'booking' => false,
            'omzetcheck' => false,
            'speed_to_lead_start' => false,
        ],
    ],

];
