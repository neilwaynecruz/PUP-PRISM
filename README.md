# PUP PRISM

A Laravel + Inertia (Vue 3) system for Property and Resource Inventory, Stock Receiving/Issuance, Asset Booking and Handover, with RBAC, queues, scheduled alerts, and PDF receipts.

## Tech Stack
- PHP 8.4+
- Laravel 13, Fortify (auth), Spatie Permission (RBAC)
- Inertia v3, Vue 3, Vite, Tailwind CSS v4
- PostgreSQL
- Wayfinder (typed routes/actions), DomPDF
- Pest PHP tests

## Prerequisites
- PHP 8.4+
- Composer 2.x
- Node 18+ and npm
- PostgreSQL 14+

## Quickstart (Local Development)
1. Install PHP deps:
   - `composer install`
2. Create env and key:
   - `copy .env.example .env` (Windows) or `cp .env.example .env`
   - `php artisan key:generate`
3. Configure DB in `.env` and run:
   - `php artisan migrate --seed`
4. Frontend dependencies and dev server:
   - `npm install`
   - `npm run dev`
5. Optional background workers during dev:
   - Queue: `php artisan queue:listen --tries=1`
   - Scheduler (cron alternative): keep a system cron in production (see Deployment)

## Default UAT Accounts (seeded)
All passwords are `password` (UAT only):
- Admin — `admin@local.test`
- Supply Head — `supply@local.test`
- Chief Property Custodian — `custodian@local.test`
- IT Expert (Admin) — `it.expert@local.test`
- Director (Custodian) — `director@local.test`
- Department Custodians — e.g. `eng.custodian@local.test`, `education.custodian@local.test`, `business.custodian@local.test`, `registrar.custodian@local.test`, `library.custodian@local.test`, `student.affairs.custodian@local.test`, `admin.office.custodian@local.test`, `director.office.custodian@local.test`

## Roles & Permissions
- Admin
- Supply Head
- Property Custodian

Policies and route middleware enforce access for inventory operations, requisition approval/issuance, bookings, and handovers.

## Architecture Overview
- Server: Laravel controllers + services (e.g., `InventoryService`) with Eloquent models
- Client: Inertia + Vue single-page app under `resources/js/pages`
- Auth: Fortify (email/password, email verification, 2FA optional)
- RBAC: Spatie Permission roles and checks in controllers/policies
- PDF receipts: DomPDF for handover verification
- Background: Database queue, scheduled command `app:inventory-generate-alerts`
- Typed routes/actions: Wayfinder plugin generates `@/routes` and `@/actions` at build time

## Testing
- Unit/Feature: `php artisan test --compact`
- Fast checks (lint, prettier, TS): `npm run lint:check && npm run format:check && npm run types:check`
- Testing env: `.env.testing` uses in-memory SQLite, array cache/mailer, sync queue

### Browser E2E Testing
- Playwright specs live in `tests/e2e` and use the dedicated `.env.e2e` SQLite database at `database/e2e.sqlite`.
- Install browsers once if needed: `npx playwright install`
- Reset and seed the E2E database manually: `npm run e2e:setup`
- Build frontend assets before running browser tests: `npm run build`
- Run the suite:
  - `npx playwright test`
  - or `npm run e2e:test`
- Interactive runner and report:
  - `npm run e2e:ui`
  - `npm run e2e:report`
- The Playwright config starts `php artisan serve --env=e2e` automatically. Browser tests use seeded E2E users plus targeted Artisan helpers for email verification and deterministic handover verification tokens.

## Deployment Checklist (Production)
1. Environment
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   - `APP_NAME="PUP PRISM"`, `SESSION_ENCRYPT=true`
   - Configure DB, queue (`QUEUE_CONNECTION=database`), cache (database/redis)
   - Configure Resend mail: `MAIL_MAILER=resend`, `RESEND_API_KEY=...`, and a verified `MAIL_FROM_ADDRESS`
2. Build frontend (Wayfinder generates code here):
   - `npm ci`
   - `npm run build`
3. Laravel optimize caches
   - `php artisan optimize` (or run `config:cache`, `route:cache`, `view:cache`, `event:cache`)
4. Storage symlink
   - `php artisan storage:link`
5. Database migrations
   - `php artisan migrate --force`
6. Queue worker (systemd, supervisor, or equivalent)
   - `php artisan queue:work --queue=default --sleep=1 --tries=3 --max-time=3600`
7. Scheduler (cron)
   - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
   - Includes `app:inventory-generate-alerts`
8. Web server
   - Force HTTPS (at proxy/load balancer) and set `X-Forwarded-Proto` headers

## Operational Notes
- Logs must never be committed; log paths are gitignored (redundant ignore at root and `storage/logs/.gitignore`)
- PostgreSQL-specific SQL should be avoided; code uses portable SQL casts
- Wayfinder-generated files are gitignored; ensure `npm run build` in all environments
- Resend is the preferred production mail transport; do not switch production away from `log` until the API key and verified sender are ready
- For local Resend smoke tests, `onboarding@resend.dev` works only when sending to the email address tied to your Resend account

## Troubleshooting
- Clear caches: `php artisan optimize:clear`
- Permissions: ensure web server user can write to `storage/` and `bootstrap/cache/`
- Queues: confirm `jobs` table exists and worker is running
