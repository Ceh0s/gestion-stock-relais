# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Laravel 13 (PHP 8.4) web app for tracking IT equipment inventory across multiple physical sites (`gestion-stock-relais`): each physical item is a row in `materiels` (not a quantity count), moved between locations, assigned to users, sent to repair, or returned to stock, with every state change logged immutably in `mouvements`. See `README.md` for the full feature/deployment description.

## Environment: no PHP/Composer on the host

This machine has no PHP, Composer, or `sudo` TTY access. Every PHP-related command must run inside the Docker dev container — never try `php artisan ...` or `composer ...` directly on the host, it will fail with "command not found".

```bash
# One-time build / whenever composer.json or the Dockerfile changes
docker compose build

# Everyday PHP commands (artisan, composer, tests) all go through this container
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan test
docker compose run --rm app php artisan test --filter=test_profile_information_can_be_updated
docker compose run --rm app composer require <package>

# Start the app (serves on http://localhost:8000) + MariaDB
docker compose up -d
```

Files created by `docker compose run` are owned by `root` on the host. After any command that creates/modifies files from inside the container, reclaim ownership:

```bash
docker run --rm -v ~/gestion-stock-relais:/app alpine chown -R 1000:1000 /app
```

Node/npm **do** run directly on the host (no container needed) for front-end assets:

```bash
npm install
npm run dev     # Vite dev server / HMR
npm run build   # production build consumed by @vite() in Blade
```

Database is MariaDB via the `db` service (`DB_HOST=db` in `.env`, credentials in `docker-compose.yml`). Tests use in-memory SQLite instead (see `phpunit.xml`), so `docker compose run --rm app php artisan test` doesn't touch the dev database.

Demo accounts (password `password` for all): `admin@gestion-stock.local` (administrateur), `gestionnaire@gestion-stock.local` (gestionnaire_stock), `technicien@gestion-stock.local` (technicien), `consultation@gestion-stock.local` (consultation), `auditeur@gestion-stock.local` (auditeur). Re-seed with `docker compose run --rm app php artisan migrate:fresh --seed`.

## Architecture

**Business logic lives in `app/Services/MaterielService.php`, not in controllers.** All state-changing operations on a `Materiel` (ajouter, deplacer, attribuer, retournerEnStock, mettreEnReparation) go through this service: each wraps its write in `DB::transaction()`, row-locks the `Materiel` with `lockForUpdate()`, bumps its optimistic-locking `version` column, and writes a `Mouvement` record capturing the before/after state. `MaterielController` only validates request input and delegates — new state-changing actions should follow the same service-method + transaction + `Mouvement` pattern rather than mutating `Materiel` directly in a controller.

**Domain model** (see migrations in `database/migrations/` for exact columns):
- `sites` → `emplacements` (site has many emplacements) → `materiels` (belongs to one emplacement, optionally one `utilisateur`).
- `mouvements` is an append-only audit log (no `updated_at`, never deleted) recording every emplacement/statut/utilisateur transition plus who performed it (`user_id`) and why (`motif`, `commentaire`).
- `imports` logs each Excel/CSV import attempt. Import is **all-or-nothing**: `app/Imports/MaterielsImport.php` validates every row first (duplicate reference/numero_serie, unknown site/emplacement, invalid enum values) and only persists via a single transaction if zero errors were collected — never do a partial/row-by-row import.
- `users` doubles as both login accounts and the directory of staff that equipment can be assigned to; `role` (`administrateur`, `gestionnaire_stock`, `technicien`, `consultation`, `auditeur`) drives both UI visibility (`User::peutGerer()`, `User::estAdministrateur()`) and route access.

**Authorization** is route-level, not policy-based: the `role` middleware alias (`App\Http\Middleware\EnsureUserHasRole`, registered in `bootstrap/app.php`) takes a list of allowed roles per route group in `routes/web.php`. When adding a new protected route, add it to the matching `Route::middleware('role:...')` group rather than checking roles inline in the controller.

**Route ordering matters**: static sub-paths under `/materiels` (e.g. `/materiels/creer`) must be declared before the catch-all `/materiels/{materiel}` show route, or Laravel's implicit route-model binding will try to resolve the static segment as a `Materiel` id/key and 404. Keep this ordering in mind when adding new `/materiels/...` routes.

**No self-service registration or email verification** — accounts are created by an administrateur via `UserController`. Breeze's register/verify-email controllers, routes, and views were deliberately removed; don't re-add them without being asked.

**Excel import/export** uses `maatwebsite/excel` (`app/Imports/MaterielsImport.php`, `app/Exports/MaterielsExport.php`), not a hand-rolled CSV parser.

## Deployment

Target is Plesk (Laravel Toolkit extension) deploying via Git from GitHub, with **no SSH access** — see the "Deploiement sur Plesk" section of `README.md` for the exact deploy-action commands and required PHP version (8.4).
