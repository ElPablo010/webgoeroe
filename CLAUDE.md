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
`seo-analytics`-skill (Overzicht, Keywords, Acties, Instellingen) én het
**Leads**-scherm. Interne namen blijven `Seo*` / `seo_*`; enkel het zichtbare
label heet Groei — dat is wat we verkopen: verkeer → leads, aantoonbaar sinds
de livegang. Geïnstalleerd/bijgewerkt op 04/09/2026 naar de stand van de skill.

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
  Google-verkeer uit Search Console — niet de DataForSEO-schatting. Clicks,
  vertoningen, CTR en positie (28 d. t.o.v. 28 d. ervoor, gewogen op
  vertoningen), weekverloop met livegang-markering, top-zoektermen/-pagina's en
  "kansen" (≥ 20 vertoningen, positie 4-20). Koppeling via **OAuth** op het
  eigen Google-account: client-ID/secret uit Google Cloud (`gsc_oauth_*`),
  consent-flow via `SearchConsoleOAuthController` (`/admin/search-console/oauth/
  redirect|callback`, `auth` + panel-check, state in sessie), refresh token in
  `gsc_refresh_token`, property in `gsc_site_url` (na koppelen automatisch
  gekozen: domein-property > https > www). Sync: `seo:sync-search-console`
  dagelijks 6:00 (`GscCollector`: 16 maanden backfill bij de eerste run, daarna
  rollend 7-dagenvenster met upsert per dag). Valkuilen: `access_type=offline`
  + `prompt=consent` zijn verplicht (anders geen refresh token) en de
  OAuth-app moet in Google Cloud op "In productie" staan (anders vervalt het
  token na 7 dagen). Bij `invalid_grant` wist de service het token zelf.
- **Keyword-onderzoek**: knop "Stel keywords voor" op Keywords dispatcht
  `SuggestKeywordsJob` (queue, rate-limit 10 min); de voorstellen staan in
  Setting `seo_keyword_suggestions` en verschijnen als checkbox-blok
  (`SeoKeywordSuggestions`-widget) boven de tabel. Aangevinkt = opgevolgd, nooit
  automatisch: elke keyword kost wekelijks een SERP-meting.
- **Acties lopen over de queue** (`GenerateSeoActionsJob`), niet meer synchroon
  in de knop — de scheduler-worker (`queue:work --stop-when-empty`, elke minuut)
  moet dus draaien, ook op Combell.
- Datums in deze schermen altijd `dd/mm/jjjj`.

Vastgelegd in `tests/Feature/LeadAttributionTest.php`,
`tests/Feature/SeoLeadsPageTest.php`, `tests/Feature/SearchConsoleTest.php` en
`tests/Feature/SeoKeywordSuggestTest.php`.

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
