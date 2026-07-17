<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\PublishPost;
use App\Mcp\Tools\UnpublishPost;
use App\Mcp\Tools\UpdatePost;
use App\Mcp\Tools\UploadMediaFromUrl;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Webgoeroe Blog')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Beheer de blog van De Webgoeroe (dewebgoeroe.be).

    Werkwijze:
    - Schrijf de artikeltekst als Markdown; die wordt server-side naar HTML omgezet.
      Gebruik ## voor tussenkopjes (die voeden de inhoudsopgave) en ### voor subkopjes.
    - `create_post` maakt een nieuw artikel. Zet `published` op true om het meteen
      live te zetten; anders blijft het een concept in de admin.
    - Vul waar mogelijk `excerpt` (teaser, ~1-2 zinnen) en 1-4 relevante `tags` in —
      dat verbetert het overzicht en de SEO.
    - Elke actie geeft de publieke `url` terug: kijk een nieuw of gewijzigd artikel
      daar altijd even na.
    - Twijfel je of een titel al bestaat? Gebruik eerst `list_posts`.
    - Een fout online gezet? `unpublish_post` haalt het artikel meteen offline.

    Afbeeldingen:
    - Link **nooit** rechtstreeks naar een externe afbeelding. Haal ze binnen met
      `upload_media_from_url` en gebruik de `url` die je terugkrijgt als `cover_url`.
      Zo staat het bestand op onze eigen server (geconverteerd naar WebP + JPG),
      en breekt de afbeelding niet als de bron offline gaat.
    - De URL moet publiek en rechtstreeks naar het afbeeldingsbestand wijzen.
    - Zet er een beschrijvende `cover_alt` bij — nodig voor toegankelijkheid en SEO.
    TXT)]
class BlogServer extends Server
{
    protected array $tools = [
        ListPosts::class,
        CreatePost::class,
        UpdatePost::class,
        PublishPost::class,
        UnpublishPost::class,
        UploadMediaFromUrl::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
