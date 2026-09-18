# Webgoeroe — project CLAUDE.md

Website van De Webgoeroe (webbureau). Opgebouwd met de `new-website`-skill als
backend-fundering; publieke styling volgt via de `design-website`-skill.

---

## Project-keuzes

| Vraag | Keuze |
|-------|-------|
| Admin-UI taal | **Nederlands** |
| Meertalige publieke site | **Nee** — enkel locale `nl` |
| Domeinen in scope | **Webgoeroe / webbureau** (diensten, portfolio, blog, contact) |
| Klant-accounts | **Nee** — Filament is het enige login-systeem |
| Productie-database | **MySQL** (lokaal via Herd op `127.0.0.1`, user `root`, geen wachtwoord) |
| Hosting / deploy | **Combell shared hosting** (gebruik `deploy-combell`-skill bij go-live) |
| Primaire merkkleur | **#7C3AED** (paars) |
| Lettertype | **Inter** (via `@fontsource-variable/inter`) |

---

## Stack

- Laravel 13 + PHP 8.3
- Filament v5 (admin op `/admin`)
- Livewire 4 + Blade + Alpine.js (publieke frontend)
- Tailwind CSS v4 (via Vite)
- Pest (tests)
- MySQL (lokaal + productie)

---

## Welk AI-model waarvoor

Het model staat in [`config/services.php`](config/services.php) onder
`services.anthropic.models`, **nooit hardcoded in een service**. Eén sleutel per
soort klus, niet per klasse:

| Sleutel | Waarvoor | Nu |
|---------|----------|-----|
| `reasoning` | SEO-advies, verbeteracties, landingspagina's uitschrijven | `claude-sonnet-5` |

Te overschrijven met `ANTHROPIC_MODEL_REASONING` in `.env`, zodat je op één
project kunt afwijken zonder code te wijzigen.

**Waarom niet in de klasse:** hardcode je het model waar je het gebruikt, dan
draaien er na drie features drie verschillende modellen zonder dat iemand dat
besloot — het model dat er bij het schrijven toevallig stond, blijft jaren
staan. Dat gebeurde in de andere projecten: de vertaallaag daar defaultte naar
het duurste model dat er is, voor het omzetten van korte UI-teksten.

Een project mét vertaallaag zet er een tweede sleutel bij (`bulk`) voor het
sleurwerk — honderden korte strings, waar niets te bedenken valt en een klein
model volstaat. Deze site is eentalig, dus die sleutel staat er bewust niet:
geen dode config.

---

## Lokale development

```bash
# Server starten
php artisan serve

# Assets (watch mode)
npm run dev

# Tests
./vendor/bin/pest

# Admin
http://localhost:8000/admin
# login: pieter@dewebgoeroe.be / password (wijzigen na go-live)
```

---

## Nieuwe sectietype toevoegen

Drie plekken:

1. `resources/views/components/site/sections/<type-met-streepjes>.blade.php`
2. `app/Filament/Schemas/Sections/<Type>Fields.php` met `static make(): array`
3. `'<Label>' => Block::make('<type_snake_case>')` in `PageSectionsBuilder::blocks()`
   — de array is **gekeyed op het label** en wordt alfabetisch gesorteerd voor ze
   naar de Builder gaat, zodat de "Sectie toevoegen"-lijst voorspelbaar blijft.
   Vergeet je die key, dan krijgt het block een numerieke key en belandt het
   bovenaan de lijst in plaats van op z'n alfabetische plek.

---

## Site-brede call-to-action

De afsluitende CTA-banner onderaan **elk blogartikel** en **elke case** komt uit
één instelling: **Instellingen → Algemeen → Call-to-action** (titel, tekst,
knoptekst en bestemming). Code: [`App\Support\SiteCta`](app/Support/SiteCta.php),
opgeslagen als de `cta`-sleutel in de `settings`-tabel.

- De bestemming bewaar je bij voorkeur als **pagina-koppeling** (`link_type: page`
  + `page_id`), niet als vast pad. `SiteCta::current()` rekent de href dan live
  uit de slug van die pagina — hernoem je de pagina in het CMS, dan volgen alle
  CTA's mee zonder code-wijziging. Zelfde afweging (en dezelfde reden dat
  `PageLinkField` z'n href binnen een `statePath`-group niet betrouwbaar
  wegschrijft) als in [`SiteHeader`](app/Support/SiteHeader.php).
- Een case mag afwijken via z'n eigen `content.cta`-velden — die zijn **puur
  override**: laat je er één leeg, dan erft de case de site-instelling
  (`SiteCta::mergedWith()`). In de praktijk vullen cases alleen `title`/`body`
  in en erven ze de knop.
- Blogartikelen hebben géén eigen CTA-velden; die gebruiken de instelling volledig.

Wijzig de CTA-tekst dus **niet in de blade-views** — die lezen enkel `$cta`, dat
de controllers meegeven.

---

## Database & media: lokaal werken, dan naar live pushen

Een deploy (`deploy.sh`) synct **enkel code** via git. Content (pagina's, secties,
posts, cases, menu's, instellingen, redirects, media) leeft in de database en in
`storage/app/public`, en die twee omgevingen lopen dus uit elkaar zodra je ergens
iets bewerkt. Twee scripts houden ze gelijk:

| Richting | Commando | Wat |
|----------|----------|-----|
| live → lokaal | `scripts/db-pull.sh` | **Volledige** live-DB vervangt de lokale (lokale backup eerst in `storage/db-backups/`), media erbij gehaald. |
| lokaal → live | `scripts/db-push.sh` | Enkel de **content-tabellen** (`PUSH_TABLES` in `scripts/db-common.sh`) vervangen op live; volledige live-backup eerst op de server in `~/db-backups/`. Vraagt om `LIVE` te typen. |

Werkwijze voor een reeks content-aanpassingen:

1. `scripts/db-pull.sh` — vertrek van de actuele live-inhoud.
2. **Bevries live-bewerkingen**: niets meer aanpassen in de live admin of via de
   MCP-connector (die wijst naar `dewebgoeroe.be`) tot de push gebeurd is. Wat
   live tussendoor verandert in de content-tabellen wordt bij de push overschreven.
3. Werk lokaal (admin op `webgoeroe.test/admin`).
4. `scripts/db-push.sh` — en daarna eventueel "klaar en deploy" als er ook code wijzigde.

Nooit meegepusht (live is daarvoor de bron): `form_submissions`, `leads`, `gsc_*`, alle `seo_*`-tabellen,
`users`, `oauth_*`, `personal_access_tokens`, `sessions`, `cache*`, `jobs*`, `migrations`.
Media-sync voegt toe en overschrijft, maar **verwijdert nooit** (in beide richtingen).
`--no-media` slaat de mediastap over.

---

## Admin-chrome (zijbalk en topbalk)

Alles staat in `AdminPanelProvider`:

- **Groepsvolgorde ligt vast**: `navigationGroups(['Website', 'Groei',
  'Instellingen'])`. Zonder die regel sorteert Filament op de volgorde waarin hij
  pagina's ontdekt, en wandelt Instellingen naar boven zodra er een pagina
  bijkomt. Instellingen hoort onderaan — dat open je zelden.
- **Uitlogknop onderaan de zijbalk** via render hook `SIDEBAR_FOOTER` →
  `filament.admin.sidebar-logout`. Uitloggen zat al in het accountmenu
  rechtsboven, maar dat moet je eerst openklappen. De view hergebruikt de
  Filament-klassen van een navigatie-item zodat hij er identiek uitziet; padding
  staat inline, want dit valt buiten `.fi-sidebar-nav` en de app-Tailwind wordt
  niet in de admin geladen.
- **Oogje naar de site** via `GLOBAL_SEARCH_AFTER` →
  `filament.admin.view-site-button`.

Vastgelegd in `tests/Feature/AdminSidebarTest.php`, dat óók de volgorde in de
gerenderde HTML controleert. Let op bij het schrijven van zo'n test: "Uitloggen"
staat twee keer op de pagina, want het accountmenu heeft het ook. De zijbalk-knop
is de láátste.

## Harde regels (overerfd van new-website-skill)

- **Media-velden**: altijd `MediaPickerField`, nooit kaal URL-veld.
- **Tabel-rij-acties**: icon-only (`->button()->hiddenLabel()->tooltip(...)`).
- **Titelkolom**: via [`TitleColumn::make(<Resource>::class)`](app/Filament/Tables/Columns/TitleColumn.php)
  — klikbaar naar het bewerkscherm, met `wrap()` + een inline `max-width` zodat
  één lange titel de volgende kolommen niet wegduwt. Chain er gerust extra's
  achteraan (bv. het homepage-icoontje in `PagesTable`).
- **Geen kolom voor een vlag die maar op één rij staat** (bv. `is_homepage`):
  hang de markering als icoon aan de titelkolom, en geen filter erop.
- **Dropdowns**: alfabetisch ordenen.
- **Buttons**: `cursor-pointer` (+ `disabled:cursor-not-allowed`).
- **Elk conversiepunt is een lead** (Groei-meetlaag, zie hieronder). Formulieren
  lopen via `FormSubmission` en tellen automatisch (hook in het model). Bouw je
  een conversie die géén formulier is — boeking, betaling, aanmelding via een
  externe koppeling — dan roep je `Lead::record()` aan op het punt waar ze
  definitief wordt, bínnen de idempotency-guard als er een webhook in het spel is.
- Code/commits in het Engels; admin-UI + validatie in het Nederlands.

---

## Groei — SEO-module + leads-meetlaag

De sidebar-groep **Groei** (`/admin`) bundelt de SEO-module uit de
`seo-analytics`-skill (Overzicht, Verkeer, Keywords, Acties, SEO-instellingen)
én het **Leads**-scherm. Interne namen blijven `Seo*` / `seo_*`; enkel het
zichtbare label heet Groei — dat is wat we verkopen: verkeer → leads, aantoonbaar
sinds de livegang. Geïnstalleerd/bijgewerkt op 04/09/2026 naar de stand van de skill.

**Instelwerk en cijfers staan strikt gescheiden** (sinds 14/09/2026). Álles wat je
invult staat op **Groei → SEO-instellingen** (`SeoSettings`): de Google-koppeling
met client-ID/secret en omleidings-URI, de knoppen "Verbinden met Google",
"Andere site kiezen", "Andere property kiezen" en "Koppeling verbreken", het
GA4-meet-ID en property-ID, de DataForSEO-credentials, de GEO-prompts en de
rapport-ontvanger. De koppelknoppen hangen als `Section::headerActions()` aan de
sectie waar ze over gaan, niet als losse kopknoppen.

Het item heet bewust **"SEO-instellingen"** en niet "Instellingen": de sidebar
heeft al een gróep met die naam, en twee items "Instellingen" naast elkaar leest
als een fout. Het **Verkeer**-scherm houdt enkel de twee ververs-knoppen plus een
doorverwijzing; z'n lege toestanden linken naar `SeoSettings::getUrl()`. De
OAuth-callback keert ook daarheen terug.

- **Herkomst**: `CaptureFirstTouch` (web-groep, ná `HandleRedirects`) legt bij
  het eerste GET van een sessie kanaal, landingspagina, referrer en utm's vast
  (`App\Support\Attribution`, sessiekey `wg_first_touch`). Sessie-only, geen
  cookie, bots (UA-match) krijgen niets. Bewust geen GA4.
- **Leads**: `FormSubmission::booted()` schrijft bij élke inzending een `Lead`
  (type = formuliertype, morph naar de inzending). `Lead::record()` faalt nooit
  hard — een fout in de meting mag geen formulier blokkeren. Labels: eerst
  `Lead::TYPE_LABELS`, dan `FormSubmission::TYPE_LABELS` (`Lead::typeLabel()`).
- **Leads-scherm** (`SeoLeads`, `/admin/seo-leads`): kop-cijfers t.o.v. het
  maanddoel, leads per maand met doellijn en livegang-markering, verdeling per
  kanaal/type/landingspagina (90 d.), recentste 50, en de nulmeting-velden
  (`seo_live_since`, `seo_goal_leads_month`, `seo_leads_baseline` in
  `Setting`). Alle cijfers uit `App\Support\LeadStats` — de enige bron.
  Zonder `leads`-tabel toont het scherm een migratie-melding i.p.v. te crashen.
- **Verkeer** (`SearchConsole`, `/admin/search-console`): het **gemeten**
  verkeer uit twee bronnen, met de kerncijfers van allebei bóven een tabstrip
  en enkel de detailtabellen erachter. `$tab` is Livewire-state en `tables()`
  haalt enkel op wat het actieve tabblad toont.
  - Tabblad **Uit Google Zoeken** (Search Console, niet de DataForSEO-schatting):
    clicks, vertoningen, CTR en positie (28 d. t.o.v. 28 d. ervoor, gewogen op
    vertoningen), weekverloop met livegang-markering, top-zoektermen/-pagina's
    en "kansen" (≥ 20 vertoningen, positie 4-20). Property in `gsc_site_url`
    (na koppelen automatisch gekozen: domein-property > https > www). Sync:
    `seo:sync-search-console` dagelijks 6:00 (`GscCollector`: 16 maanden
    backfill bij de eerste run, daarna rollend 7-dagenvenster met upsert per dag).
  - Tabblad **Op de site** (Analytics): sessies, bezoekers, weergaven en
    betrokkenheid, de meest bekeken pagina's en de kanalen. Property-ID in
    `ga4_property_id` — het **getal**, niet het `G-XXXX` meet-ID uit de
    meetcode. Sync: `seo:sync-analytics` dagelijks 6:15 (`Ga4Collector`, zelfde
    rollende venster). Zie ook "Analytics op de site" hieronder.
- **Eén Google-koppeling voor beide** (`App\Services\Google\GoogleApiClient`).
  Het volledige inlogwerk — consent-URL, code inwisselen, access token halen en
  cachen, `invalid_grant` afvangen, JWT voor een service account, de HTTP-laag —
  staat in die basisklasse; `GoogleSearchConsoleService` en
  `GoogleAnalyticsService` vullen enkel `serviceAccountScope()`, `apiBase()` en
  `label()` in. Consent-flow via `SearchConsoleOAuthController`
  (`/admin/search-console/oauth/redirect|callback`, `auth` + panel-check, state
  in sessie); **die route- en klassenaam blijven bewust "gsc"** omdat de
  omleidings-URI zo in Google Cloud geregistreerd staat.
  - `CONSENT_SCOPES` vraagt beide rechten in één keer; Google's antwoord landt
    in `google_oauth_scopes`, zodat `hasGrantedScope()` weet of Analytics
    meekwam. Een koppeling van vóór deze uitbreiding heeft dat recht niet — het
    scherm toont dan "Analytics hangt er nog niet aan" en vraagt om opnieuw te
    verbinden. Een lege scope-lijst betekent "enkel Search Console".
  - Inloggegevens staan onder `google_*` (client-ID, secret, refresh token,
    service-account-JSON). Ze heetten vroeger `gsc_*`; de migratie
    `move_google_credentials_to_shared_keys` verplaatst ze en verwijdert de oude
    rijen, zodat er geen tweede kopie van een refresh token blijft staan.
  - Valkuilen: `access_type=offline` + `prompt=consent` zijn verplicht (anders
    geen refresh token), de OAuth-app moet in Google Cloud op "In productie"
    staan (anders vervalt het token na 7 dagen), en voor Analytics moeten daar
    ook de **Data API én de Admin API** aan staan. Bij `invalid_grant` wist de
    service het token zelf.
  - **Een ververs-knop die niets oplevert zegt nu waaróm.** `GoogleApiClient`
    houdt de laatste fout bij (`lastError()`, met Google's eigen
    `error.message` erin), de collectors geven die door als `error` in hun
    `sync()`-resultaat, en het Verkeer-scherm splitst dat in twee meldingen:
    "Google weigerde de opvraging" (+ de reden — instelfout, zelf oplossen) en
    "Nog geen cijfers bij Google" (koppeling werkt, gewoon wachten). Zelfde
    reden als de gescheiden bronnen hierboven: op gedeelde hosting sla je
    `storage/logs/laravel.log` niet even open. `php artisan seo:sync-analytics`
    / `seo:sync-search-console` drukken diezelfde reden af en geven exitcode 1.
    En let op bij het debuggen via het log: op Combell staat `LOG_LEVEL=error`
    in `.env`, dus een mislukte Google-call wordt met opzet als **error**
    gelogd — als warning zou je 'm daar nooit zien, en dan lijkt een lege
    grep ten onrechte op "er ging niets mis".
- **Analytics op de site** (`resources/views/components/site/analytics.blade.php`):
  het meet-ID (`G-XXXX`) staat op Groei → SEO-instellingen (`google_analytics_id`),
  leeg = er gaat **geen enkel** verzoek naar Google. gtag.js wordt pas opgehaald
  nadat de bezoeker analytische cookies aanvaardt; intrekken schakelt GA uit en
  wist de `_ga`-cookies. Het script staat in `<head>` vóór Alpine, zodat de
  listener op `cookie-consent-changed` klaarstaat wanneer de banner een eerder
  bewaarde keuze doorgeeft. **Analytics heeft geen terugwerkende kracht**: het
  toont niets van vóór de dag dat de meetcode draaide — anders dan de 16 maanden
  die Search Console bij het koppelen meegeeft.
- **De cijfers sluiten niet op elkaar aan, en dat hoort zo.** Analytics telt
  enkel wie cookies aanvaardde, Search Console telt elke klik, en de Leads-laag
  telt iedereen. Vergelijk dus verhoudingen binnen één bron, geen absolute
  aantallen tussen bronnen. Om die reden staat er (nog) géén conversiegraad per
  pagina: leads delen door GA4-bezoeken geeft een structureel te hoog percentage.
- **Keyword-onderzoek**: knop "Stel keywords voor" op Keywords dispatcht
  `SuggestKeywordsJob` (queue, rate-limit 10 min); de voorstellen staan in
  Setting `seo_keyword_suggestions` en verschijnen als checkbox-blok
  (`SeoKeywordSuggestions`-widget) boven de tabel. Aangevinkt = opgevolgd, nooit
  automatisch: elke keyword kost wekelijks een SERP-meting.
  - **De stand van dat onderzoek staat in `settings`**, niet in een
    Livewire-property: [`App\Support\JobStatus`](app/Support/JobStatus.php) onder
    de sleutel `seo_keyword_suggestions_status` (`queued` → `running` → `done`
    of `failed`). Bewust persistent — je start het onderzoek, gaat weg en komt
    een kwartier later terug; dán moet het scherm nog kunnen zeggen dat het
    loopt. Het blok ververst zichzelf met `wire:poll.10s` zolang de taak bezig is.
  - **`JobStatus` kantelt zelf naar "vastgelopen"** na 3 minuten in `queued`
    (of 15 in `running`). Dat is geen kosmetiek maar de enige manier om een
    niet-draaiende queue-worker te zíén: een job die nooit opgepikt wordt faalt
    ook nooit, dus zonder die grens blijft de melding eeuwig "bezig" zeggen. Het
    blok wijst dan meteen naar de cron/`queue:work` als oorzaak.
  - **Niets gevonden is een fout, geen stilte.** `suggestKeywords()` zet
    `lastError()` (zelfde patroon als `GoogleApiClient`) met de échte reden —
    ontbrekende Anthropic-key, een Anthropic-status, of DataForSEO's eigen
    `status_message` via de nieuwe `DataForSeoService::lastError()`. Vroeger
    schreef een mislukte run gewoon een lege lijst weg, waarna het widget zich
    verborg en je naar een leeg scherm keek zonder enige aanwijzing.
  - **`php artisan seo:suggest-keywords`** doet hetzelfde onderzoek synchroon,
    drukt de reden af en geeft exitcode 1. Daarmee sluit je op de server in één
    minuut uit of de knop faalt (credentials/saldo) of de wachtrij (worker).
- **Acties lopen over de queue** (`GenerateSeoActionsJob`), niet meer synchroon
  in de knop — de scheduler-worker (`queue:work --stop-when-empty`, elke minuut)
  moet dus draaien, ook op Combell. Loopt die cron niet, dan blijven die jobs
  zonder één foutmelding in de `jobs`-tabel staan. Het Acties-scherm gebruikt
  daarom dezelfde `JobStatus` (sleutel `seo_actions_status`) en dezelfde banner
  als Keywords: [`<x-admin.job-status-banner>`](resources/views/components/admin/job-status-banner.blade.php).
  Voeg je nog zo'n knop toe, hergebruik dan die twee — niet opnieuw een eigen
  melding schrijven.
- **⚠️ Lees een Anthropic-antwoord nooit als `content.0.text`.** Het model heeft
  adaptive thinking aan, dus blok 0 is een *thinking*-blok met lege tekst.
  Gebruik `SeoAdvisorService::firstTextBlock()` (of `firstWhere('type', 'tool_use')`
  bij tool-use). Dit heeft het keyword-onderzoek maandenlang stilzwijgend
  gesloopt: nul seeds → DataForSEO kreeg enkel het kale domein → nul voorstellen
  → een leeg scherm zonder één foutmelding. Vastgelegd in
  `tests/Feature/SeoKeywordSuggestTest.php`.
- **Een gegenereerde landingspagina volgt een sjabloonpagina**, niet een lijst
  in code. Je wijst er één aan op Groei → SEO-instellingen → Landingspagina-sjabloon
  (Setting `seo_landing_template_slug`, standaard `sales-automation`);
  [`LandingPageBlueprint`](app/Services/Seo/LandingPageBlueprint.php) leest die
  pagina en bouwt de nieuwe pagina op haar beeld.
  - **Strikte scheiding skelet/inhoud.** Het sjabloon levert *vorm*: welke
    secties, in welke volgorde, met welke `background`, welke `section_id`, en
    per sectie hoeveel knoppen met welke `variant` en welke **bestemming**.
    Het model levert *alle tekst* — inclusief de knoplabels. Dat laatste is de
    hele reden voor deze splitsing: vroeger werd de knoptekst letterlijk van de
    homepage gekopieerd, waardoor twee keer dezelfde zin op de pagina stond,
    over een onderwerp waar ze niet bij hoorde.
  - **De knopbestemming blijft een paginakoppeling** (`link_type: page` +
    `page_id`), niet een uitgerekend pad. Zelfde reden als bij [`SiteCta`](app/Support/SiteCta.php):
    hernoem je de slug van de bottleneck-scan, dan volgen alle gegenereerde
    knoppen mee. De oude generator platste dit tot een kale `href` en leverde
    dus bróósere pagina's op dan wat je met de hand bouwt.
  - **Onderwerpgebonden velden reizen bewust niet mee**: `filter_tags` en
    `filter_industry` van de cases-grid blijven achter, anders toont een pagina
    over telefonie de cases van het sjabloononderwerp.
  - **Lege secties vallen weg.** Levert het model niets voor een blok, dan komt
    dat blok er niet — een kortere kloppende pagina verslaat een volledige met
    lege blokken. Ankerknoppen (`#aanpak`) verdwijnen automatisch mee met de
    sectie waar ze heen scrollen, anders scrollen ze nergens heen.
  - **Zonder sjabloon** valt de opbouw terug op de generieke hero → `rich_text`
    → faq → cta. Die terugval is er voor een verse installatie en voor andere
    projecten: de sectietypes van dit project (`problem_recognition`,
    `advantages`, `process_steps`, `cases_grid`) bestaan daar niet.
  - **Nieuw sectietype in de blueprint?** Twee plekken: `FILLABLE` + een arm in
    `contentFor()` in de blueprint, en een veldbeschrijving in
    `landingSchemaProperties()` + een regel in `landingPromptInstructions()` in
    [`SeoAdvisorService`](app/Services/SeoAdvisorService.php). De prompt somt
    enkel de secties op die het sjabloon écht heeft.
  - Let op bij het **bewerkformulier** op het Acties-scherm: de velden "Titel" en
    "Introtekst" hangen aan het `rich_text`-blok. Een sjabloonpagina heeft dat
    niet, dus die velden verbergen zich dan (`editForm['has_text']`) — anders
    zou wat je intikt als los tekstblok áchter de afsluitende CTA belanden.
- Datums in deze schermen altijd `dd/mm/jjjj`.

Vastgelegd in `tests/Feature/LeadAttributionTest.php`,
`tests/Feature/SeoLeadsPageTest.php`, `tests/Feature/SearchConsoleTest.php`,
`tests/Feature/SeoKeywordSuggestTest.php`,
`tests/Feature/SeoLandingBlueprintTest.php` (de sjabloon-gestuurde landingspagina), `tests/Feature/GoogleApiClientTest.php`
(de gedeelde inloglaag, op een verzonnen subklasse zodat ze echt losstaat van
één API), `tests/Feature/AnalyticsCollectorTest.php` (de GA4-sync en het tweede
tabblad) en `tests/Feature/AnalyticsSnippetTest.php` (geen meet-ID = geen
verzoek naar Google, en gtag.js nooit vóór toestemming).

---

## Content via MCP — Claude beheert blog + cases

Blog en cases kunnen door elke Claude-client (Code, desktop, claude.ai/Cowork)
beheerd worden via een MCP-server die **in de Laravel-app zelf** draait
(`laravel/mcp`) — geen apart proces, rolt mee met de gewone deploy.

- **Endpoint**: `POST /mcp` (zie [routes/ai.php](routes/ai.php)).
- **Server**: [app/Mcp/Servers/CmsServer.php](app/Mcp/Servers/CmsServer.php).
- **Tools** (in [app/Mcp/Tools/](app/Mcp/Tools/)), 11 in totaal:
  - Blog: `list_posts`, `create_post`, `update_post`, `publish_post`, `unpublish_post`
  - Cases: `list_cases`, `create_case`, `update_case`, `publish_case`, `unpublish_case`
  - Gedeeld: `upload_media_from_url`
- **Veiligheid**: `create_post`/`create_case` publiceren **niet** standaard
  (`published:false`); zet expliciet `published:true` om live te gaan.
  `unpublish_*` is het vangnet. Elke actie geeft de publieke `url` terug.
- **Annotaties**: elke tool declareert MCP-hints (`readOnlyHint`, `destructiveHint`,
  `idempotentHint`, `openWorldHint`) zodat clients weten wat veilig auto-approvebaar
  is. `list_*` is read-only; `update_*` is destructief; `upload_media_from_url` is
  open-world (haalt een externe URL op). **Nooit een schrijvende tool als read-only
  markeren** om een goedkeuringsprompt te omzeilen — die prompt is de bescherming.

### Blog

Body geef je als **Markdown**; wordt server-side via `Str::markdown()` naar HTML
omgezet (h2-id's voor de TOC voegt de blade-view zelf toe). Helpers in
[InteractsWithPosts](app/Mcp/Concerns/InteractsWithPosts.php).

### Cases

Let op: een case werkt **anders dan een post**. `CaseStudy::$content` is een
**gestructureerde JSON-array** (geen HTML-string), met een vast stramien dat
[CaseStudyForm](app/Filament/Resources/CaseStudies/Schemas/CaseStudyForm.php) en de
publieke view verwachten:

```
content: {
  challenge:   { body }                        // verplicht
  goals:       [ { text } ]
  approach:    { steps: [ { title, body } ] }
  solution:    { body, image_url, image_alt }  // body verplicht
  results:     { intro, metrics: [ { label, value } ] }
  testimonial: { quote, name, role, avatar_url }
  reflection:  { body, website_url }
  cta:         { title, body, button_label, button_url }
}
```

Dat contract staat op één plek — [InteractsWithCases](app/Mcp/Concerns/InteractsWithCases.php)
levert zowel `contentRules()` (validatie) als `contentSchema()` (MCP-inputschema),
zodat beide niet uit elkaar kunnen lopen. **Wijzigt de form? Werk de concern bij.**

`update_case` met `content` vervangt het **volledige** content-blok (geen deep merge).

### Naamgeving cases

In de admin heet dit **"Cases"** en staat het op **`/admin/cases`** (via
`$slug = 'cases'` op de resource). Het **model (`CaseStudy`), de klassen en de tabel
(`case_studies`) heten bewust nog steeds "case study"** — hernoemen daarvan vraagt
een migratie en een brede refactor zonder functionele winst. Alleen de weergave en
de URL zijn hernoemd.

### Afbeeldingen via MCP (`upload_media_from_url`)

Claude mag **nooit** rechtstreeks naar een externe afbeelding linken: `upload_media_from_url`
downloadt de afbeelding en zet ze via `WebsiteMediaService::storeFromUrl()` in de
library (WebP + JPG-fallback, max 2400 px). De teruggegeven `/storage/...`-URL gebruik
je als `cover_url`.

Omdat de URL van buitenaf komt (een MCP-client kiest 'm), is de fetch afgeschermd —
zie [WebsiteMediaService](app/Services/Website/WebsiteMediaService.php):

- **SSRF**: enkel `http(s)`, en enkel publieke IP's. Loopback, privé-ranges en
  cloud-metadata (`169.254.169.254`) worden geweigerd — óók per redirect-hop, zodat
  een publieke URL je niet alsnog naar binnen stuurt.
- **Decompression bomb**: naast de 15 MB byte-cap geldt een **pixel-cap van 12 MP**
  (`MAX_PIXELS`). Een klein JPEG kan enorme afmetingen hebben; GD houdt een afbeelding
  onverpakt in het geheugen (b×h×4 bytes), dus zonder deze check blaast een 10000×8000
  bron het PHP-geheugen op de server op. De header wordt via `getimagesize()` gelezen
  vóór GD decodeert.
- Content-Type moet `image/*` zijn, en de bytes moeten écht decodeerbaar zijn.

**Let op bij media-URL's**: de library slaat **relatieve** URL's op (`/storage/...`),
niet absolute. Velden die media aannemen valideren daarom met [`App\Rules\MediaUrl`](app/Rules/MediaUrl.php)
(volledige http(s)-URL **of** een `/storage/`-pad) — de kale `url`-regel keurt een
library-pad af.

De pixel-cap raakt ook gewone admin-uploads (zelfde `storeFromPath`). Beeldverwerking
vraagt geheugen: `phpunit.xml` zet `memory_limit=512M` voor de tests.

### Auth — twee wegen op één route

De `/mcp`-route draait op guard-lijst `auth:sanctum,api` (zie
[routes/ai.php](routes/ai.php)): Sanctum wordt eerst geprobeerd, dan Passport.
Volgorde is bewust — omgekeerd (`api,sanctum`) faalt het Sanctum-token met 401.

**1. Sanctum bearer-token — Claude Code / desktop**

- Token genereren: `php artisan mcp:token "<label>"` — koppelt aan de eerste
  beheerder (of `--email=`). Token wordt éénmalig getoond.
- Client stuurt `Authorization: Bearer <token>`.

**2. OAuth 2.1 (Passport) — claude.ai custom connector**

- `Mcp::oauthRoutes()` publiceert `.well-known/oauth-*` (metadata), `/oauth/register`
  (dynamische client-registratie, RFC 7591) en de Passport authorize/token-endpoints.
  claude.ai registreert zichzelf en doorloopt authorization-code + PKCE.
- Toegestane callback-domeinen staan in [config/mcp.php](config/mcp.php)
  (`redirect_domains`: enkel `claude.ai`/`claude.com` + localhost).
- De `api`-guard (Passport) staat in [config/auth.php](config/auth.php).
- Consentscherm: eigen merk-view [resources/views/oauth/authorize.blade.php](resources/views/oauth/authorize.blade.php),
  geregistreerd via `Passport::authorizationView('oauth.authorize')` in
  `AppServiceProvider`. De gebruiker moet ingelogd zijn (web-guard → Filament-login;
  de `login`-route redirect naar `/admin/login`).

Onbeveiligde requests krijgen 401 + `WWW-Authenticate` (geregeld via
`shouldRenderJsonWhen` op `mcp` in [bootstrap/app.php](bootstrap/app.php) — nodig
zodat MCP-clients geen 302 naar `/login` krijgen).

### Combell-deploy — eenmalige OAuth-stappen

Passport-encryptiesleutels (`storage/oauth-*.key`) zijn **gitignored** en worden
dus **niet** meegedeployed. Op de server, éénmalig na de eerste deploy:

```bash
php artisan migrate --force          # oauth-tabellen
php artisan passport:keys            # genereert storage/oauth-*.key op de server
```

Draai `passport:keys` **niet** opnieuw bij latere deploys — dat maakt bestaande
tokens ongeldig. Daarna in claude.ai: connector toevoegen met URL
`https://dewebgoeroe.be/mcp` — de rest (registratie + inloggen + toestemming) loopt
via de browser.

#### ⚠️ Sleutelrechten vs. `deploy.sh` (dit brak het al een keer)

`league/oauth2-server` **weigert** de sleutels als ze ruimer dan 600/660 staan en
gooit dan een `ErrorException` — resultaat: OAuth valt om, claude.ai krijgt een
serverfout en ziet géén tools (ook de bestaande niet).

De laatste stap van `deploy.sh` is `chmod -R 775 storage bootstrap/cache`, en die
`-R` zet **ook `storage/oauth-*.key` op 775**. Elke deploy breekt de OAuth dus
opnieuw. Zorg dat `~/deploy.sh` op de server dit erachteraan zet:

```bash
chmod -R 775 storage bootstrap/cache
# Passport-sleutels moeten strikter: 775 laat oauth2-server hard falen.
chmod 600 "$APP_DIR"/storage/oauth-*.key 2>/dev/null || true
```

Symptoom in `storage/logs/laravel.log`:
`Key file ".../oauth-private.key" permissions are not correct, recommend changing to 600 or 660`.

De code zelf is hierdoor niet te betrappen: [BlogMcpOAuthFlowTest](tests/Feature/BlogMcpOAuthFlowTest.php)
loopt de volledige authorization-code + PKCE-flow door en slaagt lokaal — dit is
puur een bestandsrechten-kwestie op de server.

### Nieuwe blog-tool toevoegen

`php artisan make:mcp-tool <Naam>`, `use InteractsWithPosts`, registreren in de
`$tools`-array van `CmsServer`. Validatie in `handle()` via `$request->validate()`,
inputschema in `schema()`.
