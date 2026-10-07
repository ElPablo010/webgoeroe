<?php

/*
 * Site-basis (webgoeroe/core) — enkel wat op De WebGoeroe afwijkt van de
 * package. De rest: vendor/webgoeroe/core/config/core.php.
 */

return [

    // Enkel Nederlands op de root (geen prefix, geen taalschakelaar, geen hreflang).
    'locales' => [
        'nl' => 'NL',
    ],

    // Geen host-redirect vanuit de app: dat deed deze site nooit (www/kale host
    // regelt de hosting). Zet op 'auto' of 'strip_www' als dat ooit moet.
    'middleware' => [
        'canonical_host' => false,
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

];
