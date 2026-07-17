<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCase;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\ListCases;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\PublishCase;
use App\Mcp\Tools\PublishPost;
use App\Mcp\Tools\UnpublishCase;
use App\Mcp\Tools\UnpublishPost;
use App\Mcp\Tools\UpdateCase;
use App\Mcp\Tools\UpdatePost;
use App\Mcp\Tools\UploadMediaFromUrl;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Webgoeroe CMS')]
#[Version('2.0.0')]
#[Instructions(<<<'TXT'
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
    TXT)]
class CmsServer extends Server
{
    protected array $tools = [
        // Blog
        ListPosts::class,
        CreatePost::class,
        UpdatePost::class,
        PublishPost::class,
        UnpublishPost::class,

        // Cases
        ListCases::class,
        CreateCase::class,
        UpdateCase::class,
        PublishCase::class,
        UnpublishCase::class,

        // Gedeeld
        UploadMediaFromUrl::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
