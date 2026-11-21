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
            'lead_check' => false,
            'speed_to_lead_start' => false,
        ],
    ],

    /*
     * MCP-koppeling met Claude (core 0.36): blog + cases beheren via POST /mcp.
     * Sanctum-token (Claude Code) én OAuth/Passport (claude.ai-connector).
     * De site-eigen tools staan aangemeld in AppServiceProvider::registerMcpTools().
     * Aan/uit ook via Instellingen → Claude (overschrijft `enabled`).
     */
    'mcp' => [
        'enabled' => true,
        'path' => 'mcp',
        'middleware' => ['auth:sanctum,api'],
        'oauth' => true,
        'name' => 'Webgoeroe CMS',
        'instructions' => <<<'TXT'
        Beheer de content van De Webgoeroe (dewebgoeroe.be): blogartikelen en cases.

        Algemeen:
        - Niets gaat standaard live. Zet expliciet `published: true` om te publiceren;
          anders blijft het een concept in de admin.
        - Elke actie geeft de publieke `url` terug: kijk je werk daar altijd even na.
        - Twijfel je of iets al bestaat? Gebruik eerst `list_posts` of `list_cases`.
        - Iets fout online gezet? `unpublish_post` / `unpublish_case` is het vangnet.
        - Verzin nooit feiten: geen klantnamen, cijfers of citaten die je niet gekregen
          hebt. Ontbreekt informatie, vraag ze dan — een case is een publieke claim
          over een echte klant.

        Blogartikelen:
        - Schrijf de artikeltekst als Markdown; die wordt server-side naar HTML omgezet.
          Gebruik ## voor tussenkopjes (die voeden de inhoudsopgave) en ### voor subkopjes.
        - Vul waar mogelijk `excerpt` (teaser, ~1-2 zinnen) en 1-4 relevante `tags` in.

        Cases (klantprojecten):
        - Een case volgt een vast stramien in `content`: challenge (de uitdaging),
          goals (doelen), approach.steps (aanpak), solution (de oplossing),
          results.metrics (meetbare resultaten), testimonial (getuigenis),
          reflection (waarom het werkte) en cta (de afsluiter).
        - `challenge.body` en `solution.body` zijn verplicht; de rest is optioneel.
        - Laat `testimonial` leeg als er geen echte quote van de klant is.
        - `update_case` met `content` vervangt het VOLLEDIGE content-blok — geef het
          dus enkel mee als je de hele inhoud opnieuw aanlevert. Wil je alleen de
          titel of een tag wijzigen, laat `content` dan weg.

        Afbeeldingen:
        - Link **nooit** rechtstreeks naar een externe afbeelding. Haal ze binnen met
          `upload_media_from_url` en gebruik de `url` die je terugkrijgt (als `cover_url`,
          of als `content.solution.image_url` bij een case). Zo staat het bestand op onze
          eigen server (WebP + JPG), en breekt het niet als de bron offline gaat.
        - De URL moet publiek en rechtstreeks naar het afbeeldingsbestand wijzen.
        - Zet er altijd een beschrijvende alt-tekst bij — nodig voor toegankelijkheid en SEO.
        TXT,
    ],

];
