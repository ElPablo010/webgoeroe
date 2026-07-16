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
3. `Block::make('<type_snake_case>')` in `PageSectionsBuilder::blocks()`

---

## Harde regels (overerfd van new-website-skill)

- **Media-velden**: altijd `MediaPickerField`, nooit kaal URL-veld.
- **Tabel-rij-acties**: icon-only (`->button()->hiddenLabel()->tooltip(...)`).
- **Dropdowns**: alfabetisch ordenen.
- **Buttons**: `cursor-pointer` (+ `disabled:cursor-not-allowed`).
- Code/commits in het Engels; admin-UI + validatie in het Nederlands.

---

## Blog via MCP — Claude schrijft blogposts

De blog kan door elke Claude-client (Code, desktop, claude.ai) beheerd worden via
een MCP-server die **in de Laravel-app zelf** draait (`laravel/mcp`) — geen apart
proces, rolt mee met de gewone deploy.

- **Endpoint**: `POST /mcp` (zie [routes/ai.php](routes/ai.php)).
- **Server**: [app/Mcp/Servers/BlogServer.php](app/Mcp/Servers/BlogServer.php).
- **Tools** (in [app/Mcp/Tools/](app/Mcp/Tools/)): `list_posts`, `create_post`,
  `update_post`, `publish_post`, `unpublish_post`. Body geef je als **Markdown**;
  wordt server-side via `Str::markdown()` naar HTML omgezet (h2-id's voor de TOC
  voegt de blade-view zelf toe). Gedeelde helpers in
  [app/Mcp/Concerns/InteractsWithPosts.php](app/Mcp/Concerns/InteractsWithPosts.php).
- **Veiligheid**: `create_post` publiceert **niet** standaard (`published:false`);
  zet expliciet `published:true` om live te gaan. `unpublish_post` is het vangnet.
  Elke actie geeft de publieke `url` terug ter controle.

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
tokens ongeldig. Voeg dit toe aan de `deploy-combell`-flow (of run het handmatig
bij go-live). Daarna in claude.ai: connector toevoegen met URL
`https://dewebgoeroe.be/mcp` — de rest (registratie + inloggen + toestemming) loopt
via de browser.

### Nieuwe blog-tool toevoegen

`php artisan make:mcp-tool <Naam>`, `use InteractsWithPosts`, registreren in de
`$tools`-array van `BlogServer`. Validatie in `handle()` via `$request->validate()`,
inputschema in `schema()`.
