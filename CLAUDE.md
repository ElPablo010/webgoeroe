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
