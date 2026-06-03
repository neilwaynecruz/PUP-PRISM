# PUP PRISM — Production Readiness Improvement Plan

> **Purpose:** A comprehensive, prioritized roadmap to take this project from its current state to production-grade quality.
> **Classification:** Each item is tagged with a priority level, risk severity, and estimated effort.
> **Total items:** 47 improvements across 5 priority tiers.
> **Last verified against codebase:** 2026-06-04

---

## How to Use This Document

- **P0 (Critical)** — Address immediately. Security, data integrity, or portability risks.
- **P1 (Required)** — Complete before any production deployment.
- **P2 (Important)** — Complete within the first production sprint after launch.
- **P3 (Quality)** — Address as time permits; significantly improves maintainability.
- **Future (Enhancement)** — Valuable additions for the next major iteration.

Each item includes:
- **Risk:** The business/technical impact if left unaddressed.
- **Effort:** Estimated time for a developer familiar with the codebase.
- **Verified:** Whether the claim has been checked against the actual source code.

---

## Completed / Implemented Improvements

These enhancements were previously listed under **Future** and have been fully implemented and verified against the codebase.

### Future.1 — Browser-Based E2E Testing

**Status:** Implemented

**Description:** Added Playwright-based E2E testing covering authentication, product management, stock receiving, requisitions, asset handovers, and bookings. Tests use stable selectors and run against the local development server.

**Implemented In:**
- `playwright.config.ts`
- `tests/e2e/auth.spec.ts`
- `tests/e2e/products.spec.ts`
- `tests/e2e/receiving.spec.ts`
- `tests/e2e/requisitions.spec.ts`
- `tests/e2e/handover.spec.ts`
- `tests/e2e/bookings.spec.ts`
- `tests/e2e/helpers/db.ts`
- `tests/e2e/fixtures/auth.ts`
- `tests/e2e/fixtures/test.ts`

**Notes:** All 7 Playwright test suites pass. Run with `npx playwright test`.

---

### Future.2 — Export Functionality (CSV/PDF Reports)

**Status:** Implemented

**Description:** Added comprehensive report exports for product inventory, stock movement audits, asset conditions, booking schedules, and requisition histories. Supports both CSV and PDF formats with role-based access control.

**Implemented In:**
- `app/Http/Controllers/Inventory/InventoryReportController.php`
- `app/Services/Reports/ProductInventoryReportService.php`
- `app/Services/Reports/StockMovementAuditReportService.php`
- `app/Services/Reports/AssetConditionReportService.php`
- `app/Services/Reports/BookingScheduleReportService.php`
- `app/Services/Reports/RequisitionHistoryReportService.php`
- `app/Services/Reports/CsvTableExporter.php`
- `app/Services/Reports/PdfTableExporter.php`
- `tests/Feature/Inventory/ReportExportTest.php`

**Notes:** Exports include filtering parity with their respective index pages (type, search, status, date range).

---

### Future.3 — Activity Log / Audit Trail Viewer

**Status:** Implemented

**Description:** The stock movements index already provides a robust audit viewer with date range filters, user filters, action type (movement_type) filters, search, sorting, and CSV/PDF export.

**Implemented In:**
- `app/Http/Controllers/Inventory/StockMovementController.php`
- `resources/js/pages/inventory/movements/Index.vue`
- `app/Http/Resources/StockMovementResource.php`

**Notes:** The `StockMovement` table serves as the canonical audit trail. The UI supports filtering by type, performed_by user, date range, and search terms.

---

### Future.4 — Email Notifications for Key Workflow Events

**Status:** Implemented

**Description:** Added email notifications for all major workflow events. `NotificationService` dispatches notifications through Laravel's notification channel, and all notification classes include mail delivery support.

**Implemented In:**
- `app/Services/NotificationService.php`
- `app/Notifications/RequisitionSubmittedNotification.php`
- `app/Notifications/RequisitionStatusChangedNotification.php`
- `app/Notifications/BookingSubmittedNotification.php`
- `app/Notifications/BookingStatusChangedNotification.php`
- `app/Notifications/LowStockAlertNotification.php`
- `app/Notifications/HandoverVerificationNotification.php`
- `app/Http/Controllers/Inventory/RequisitionController.php`
- `app/Http/Controllers/Inventory/BookingController.php`
- `app/Http/Controllers/Inventory/HandoverController.php`
- `app/Services/Inventory/InventoryService.php`
- `tests/Feature/Notifications/WorkflowNotificationsTest.php`

**Notes:** All events listed in the original plan are now covered: requisition submit/approve/issue/reject, booking request/approve/reject, low-stock alerts, and handover verification.

---

### Future.5 — Soft Deletes with Trash / Restore UI

**Status:** Implemented

**Description:** Added soft deletes with `deleted_by` tracking, optional deletion reason capture, per-model Trash pages, and a unified cross-model trash viewer with search and type filtering. Migrations, models, controllers, policies, routes, Vue pages, and comprehensive tests are all in place.

**Implemented In:**
- `database/migrations/2026_06_03_153000_add_soft_deletes_to_products_table.php`
- `database/migrations/2026_06_03_153100_add_soft_deletes_to_bookings_table.php`
- `database/migrations/2026_06_03_153200_add_soft_deletes_to_requisitions_table.php`
- `database/migrations/2026_06_04_004356_add_deleted_by_and_reason_to_products_table.php`
- `database/migrations/2026_06_04_004412_add_deleted_by_and_reason_to_bookings_table.php`
- `database/migrations/2026_06_04_004413_add_deleted_by_and_reason_to_requisitions_table.php`
- `app/Models/Product.php`
- `app/Models/Booking.php`
- `app/Models/Requisition.php`
- `app/Http/Controllers/Inventory/ProductController.php`
- `app/Http/Controllers/Inventory/BookingController.php`
- `app/Http/Controllers/Inventory/RequisitionController.php`
- `app/Http/Controllers/Inventory/TrashController.php`
- `resources/js/pages/inventory/products/Trash.vue`
- `resources/js/pages/inventory/bookings/Trash.vue`
- `resources/js/pages/inventory/requisitions/Trash.vue`
- `resources/js/pages/inventory/Trash.vue`
- `resources/js/pages/inventory/products/Edit.vue`
- `tests/Feature/Inventory/SoftDeletesTest.php`

**Notes:** Deletion reason is captured via a Dialog on the product Edit page. The unified trash viewer at `/inventory/trash` shows deleted items from all three models with search and type filtering.

---

### Future.6 — Batch Operations for Stock Receiving

**Status:** Implemented

**Description:** Added batch receiving for both consumables and assets via a multi-line form. Supports mixed product types in a single submission with full validation.

**Implemented In:**
- `app/Http/Controllers/Inventory/ReceivingController.php` (`storeBatch`)
- `app/Http/Requests/Inventory/BatchReceiveStockRequest.php`
- `app/Services/Inventory/InventoryService.php` (`batchReceive`)
- `resources/js/pages/inventory/receiving/Index.vue`
- `tests/Feature/Inventory/BatchReceivingTest.php`

**Notes:** Batch receiving validates each line independently and rolls back on validation failures.

---

### Future.7 — Webhook / API Endpoints for Integration

**Status:** Implemented

**Description:** Added secure REST API endpoints for external system integration using Laravel Sanctum token authentication, authorization policies, rate limiting, request validation, API Resources, pagination, and filtering.

**Implemented In:**
- `routes/api.php`
- `app/Http/Controllers/Api/ProductController.php`
- `app/Http/Controllers/Api/AssetController.php`
- `app/Http/Controllers/Api/StockMovementController.php`
- `app/Http/Controllers/Api/RequisitionController.php`
- `app/Http/Requests/Api/StoreRequisitionRequest.php`
- `app/Http/Resources/ProductResource.php`
- `app/Http/Resources/AssetResource.php`
- `app/Http/Resources/StockMovementResource.php`
- `app/Http/Resources/RequisitionResource.php`
- `app/Policies/AssetPolicy.php`
- `app/Policies/StockMovementPolicy.php`
- `app/Models/User.php` (`HasApiTokens`)
- `config/auth.php` (sanctum guard)
- `app/Providers/AppServiceProvider.php` (rate limiting)
- `app/Providers/AuthServiceProvider.php`
- `bootstrap/app.php`
- `tests/Feature/Api/ApiIntegrationTest.php`

**Notes:** Rate limit is 60 requests/minute. All endpoints return consistent JSON with proper HTTP status codes.

---

### Future.8 — Dashboard Widgets with Date-Range Filtering

**Status:** Implemented

**Description:** Added date-range filtering to the admin dashboard with receiving/issuing trend charts, requisition/booking/asset-condition summary widgets, and a cached query service. Supports preset ranges (today, this week, this month) and custom date pickers.

**Implemented In:**
- `app/Services/DashboardStatsService.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/js/pages/Dashboard.vue`
- `tests/Feature/DashboardTest.php`

**Notes:** Stats are cached for 60 seconds per date range to reduce database load. Trend charts use Chart.js line charts. Summary widgets show counts by status.

---

## P0 — Critical (Must Fix Immediately)

These are non-negotiable fixes. Deploying to production without addressing these introduces unacceptable risk.

Completed and removed from the active backlog during this validation:
- P0.1 — portable `CAST()` is already in `DashboardController`
- P0.2 — `reserved_qty` has already been removed from the active schema and code path
- P0.3 — `.env.example` already defaults to encrypted sessions with production notes
- P0.4 — the appearance cookie is already validated in middleware and serialized safely in Blade
- P0.5 — Product routes now rely on `ProductPolicy` as the single source of truth; the redundant route-level product middleware has been removed and the policy path is covered by tests

---

## P1 — Required (Before Production Deployment)

These are necessary for a stable, secure, and operable production system.

Completed and removed from the active backlog during this validation:
- P1.1 — `README.md` now exists and already covers setup, deployment, and roles
- P1.2 — the dead sale/request/pnpm artifacts are already gone
- P1.3 — `.gitattributes` already excludes development-only files
- P1.4 — root `.gitignore` already protects log files
- P1.5 — `.env.testing` already exists
- P1.10 — admin dashboard coverage is already in `tests/Feature/DashboardTest.php`
- P1.12 — `composer.json` already uses `pup/prism` and PHP `^8.4`

### P1.6 — Configure a production mailer

**Location:** `composer.json`, `config/mail.php`, `config/services.php`, `.env.example`, production `.env`

**Risk:** Users cannot reset passwords, verify emails, or receive handover notifications — all email-dependent workflows are silently broken.

**Problem:** The app still defaults to `MAIL_MAILER=log`, so production users will not receive real email unless the production environment is switched to an actual provider. That breaks:
- Users cannot reset their passwords via email
- Handover verification notifications are never sent to recipients
- Email verification links must be retrieved manually from logs

**Recommended provider:** Resend, using Laravel's standard `resend` driver.

**Fix:** Keep `log` as the normal default in shared examples, but configure production to use Resend:
```ini
MAIL_MAILER=resend
RESEND_API_KEY=your-resend-api-key
MAIL_FROM_ADDRESS=noreply@your-verified-domain
MAIL_FROM_NAME="PUP PRISM"
```

For local smoke testing only, you may temporarily use:
```ini
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
```
This only works when the recipient is the email address associated with your Resend account.

Repo-side validation:
- `resend/resend-php` is now a direct Composer dependency
- `config/mail.php` already defines the `resend` transport
- `config/services.php` already reads `RESEND_API_KEY`
- `.env.example` now documents Resend as the intended production mailer

> **Important:** The API key alone is not enough. Resend also requires a verified sender address/domain for `MAIL_FROM_ADDRESS`.

**Effort:** 15–30 minutes  
**Verified:** Partially complete — Laravel is now sending successfully through Resend locally using `onboarding@resend.dev` to the account email allowed by Resend. Production env values and a real verified sender domain are still deployment tasks.

---

### P1.7 — Configure a production queue worker

**Location:** `QUEUE_CONNECTION=database` in `.env`

**Risk:** HTTP request blocking, missed alerts, and unretried failed jobs.

**Problem:** Currently, the queue runs via `php artisan queue:listen --tries=1` as part of the `composer dev` script. In production, there is no persistent queue worker, which means:
- The `InventoryGenerateAlerts` scheduled command runs synchronously
- Any future queued jobs (emails, exports) will block HTTP requests
- Failed jobs are not retried

**Fix:** Configure Supervisor (Linux) to manage a persistent queue worker:
```ini
[program:pup-prism-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
```

Alternatively, use Laravel Cloud or Laravel Forge which handle this automatically.

> **Note:** With `QUEUE_CONNECTION=database`, ensure the `jobs` table exists (this repo already includes `0001_01_01_000002_create_jobs_table.php`). Because `config/queue.php` uses `QUEUE_FAILED_DRIVER=database-uuids`, also create and migrate a failed-jobs table if it does not exist yet via `php artisan make:queue-failed-table --no-interaction` and `php artisan migrate --force`.

**Effort:** 30 minutes (initial setup)  
**Verified:** Yes — `routes/console.php:11-13` confirms scheduled command exists.

---

### P1.8 — Set up the scheduled command cron

**Location:** `routes/console.php` — `app:inventory-generate-alerts`

**Risk:** The inventory alert system will never generate or refresh alerts, leading to missed low-stock and asset-status notifications.

**Problem:** The `app:inventory-generate-alerts` command is defined to run `daily()` but there is no cron entry to execute `php artisan schedule:run` every minute. Without the cron entry, Laravel's scheduler never fires.

**Fix:** Add to server crontab (run `crontab -e` as the web server user):
```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

> **Note:** On Windows (e.g., Laragon), use Task Scheduler to run `php artisan schedule:run` every minute instead.

**Effort:** 2 minutes  
**Verified:** Yes — `routes/console.php:11-13` confirms command scheduled daily.

---

### P1.9 — Create the `public/storage` symlink

**Location:** `public/storage` (symlink target)

**Risk:** Uploaded files (avatars, documents, signatures) will be inaccessible via HTTP, causing broken images and failed downloads.

**Problem:** The symlink exists in this local workspace now, but every production host or fresh release still needs it created. If deployment automation skips this step, public files will break even though the repo itself looks correct.

**Fix:**
```bash
php artisan storage:link
```

On Windows with Laragon, ensure the command is run with appropriate privileges (symlink creation may require Administrator privileges).

**Effort:** 1 minute  
**Verified:** Yes — `public/storage` exists locally now; keep this as a deployment/provisioning step, not a repo-side gap.

---

### P1.11 — Set `APP_NAME` and application identity

**Location:** production `.env`, `.env.example`, `README.md`

**Risk:** Unprofessional branding, confusing email subjects, and potential cookie-name collisions if multiple Laravel apps share a domain.

**Problem:** The repo defaults are already corrected, but production must still be checked so the deployed app does not keep an outdated or machine-specific value.

**Fix:** Update `.env` and `.env.example`:
```ini
APP_NAME="PUP PRISM"
```

> **Warning:** Do not use an excessively long name (e.g., "PUP Property & Resource Inventory System Management"). Laravel hashes `APP_NAME` into cookie and cache key prefixes; very long names can cause cookie truncation issues and clutter cache keys. Keep it concise.

**Effort:** 1 minute  
**Verified:** Partially complete — `.env.example` and `README.md` are correct; confirm the production host uses the same value.

---

### P1.13 — Force HTTPS and update `APP_URL` in production

**Location:** `app/Providers/AppServiceProvider.php:24` + `.env.example:5`

**Risk:** Mixed-content warnings, insecure cookie transmission, and session hijacking on shared networks.

**Problem:** The code-side fix is already in place, but production can still generate bad links if `APP_URL` is not set to the real HTTPS domain or if the reverse proxy headers are wrong.

**Fix:** Keep the existing code fix, then set the production environment correctly:
```ini
APP_URL=https://your-real-domain
```

Also ensure the reverse proxy forwards `X-Forwarded-Proto` and related HTTPS headers correctly.

**Effort:** 3 minutes  
**Verified:** Partially complete — `URL::forceScheme('https')` is already in `AppServiceProvider` and `.env.example` already warns about HTTPS, but production `APP_URL` and proxy behavior still need deployment validation.

---

### P1.14 — Add production optimization commands to deployment

**Location:** Deployment checklist (P1.1 README) + `composer.json` scripts

**Risk:** Slower response times, higher memory usage, and unnecessary file system reads in production.

**Problem:** The README deployment checklist already mentions `php artisan optimize`, but the actual deployment workflow is still easy to skip because it is not enforced in server automation or explicitly validated in staging. Without these caches, every request re-reads config files, route definitions, and compiled views from disk.

**Fix:** Keep these steps in the deployment checklist and add them to the actual server automation if that is not done yet:
```bash
# After composer install and migrations, but BEFORE marking the deploy live:
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
# Or simply:
php artisan optimize
```

Also ensure the server has **OPcache** enabled with recommended settings:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0   # in production; use 1 only during development
```

> **Warning:** Running `php artisan config:cache` will fail if the `.env` file contains non-ASCII characters or syntax errors. Always test `php artisan config:cache` in staging first.

**Effort:** 10 minutes  
**Verified:** Yes — `README.md` already mentions `php artisan optimize`, but `composer.json` and server automation do not enforce or validate it.

---

## Part 1 And Part 2 Delivery Summary

### Executive Summary

The purpose of this production-readiness plan is to move PUP PRISM from a working development/UAT setup into a deployment that is secure, operable, and predictable under real user traffic. Part 1 focuses on critical code, security, and data-safety corrections, while Part 2 focuses on the production services and runtime behaviors that make the application function correctly after deployment.

### Priority-Based Roadmap

**Before first production deployment**
- All previously identified P0 repo-side items are now implemented; keep them validated in CI and code review.
- Complete Part 2 server/runtime essentials: real mailer, persistent queue worker, scheduler trigger, `public/storage` link, HTTPS configuration, and deployment optimization commands.
- Keep admin dashboard coverage in place so database-specific regressions are caught before release.

**During final staging rehearsal**
- Verify production-like `.env` values, queue processing, scheduled commands, signed URLs, public files, and cache optimization in a staging environment.
- Confirm failed job storage and worker restart behavior before cutover.

**After production launch**
- Continue with the remaining P3 and Future items such as enums, API Resources, frontend/build quality improvements, and broader operational polish.

### Detailed Implementation Plan

#### Part 2.1 — Configure a production mailer
- Problem: `MAIL_MAILER=log` only writes messages to logs, so real users never receive password reset, email verification, or handover verification notifications.
- Risk if not fixed: account recovery fails, email verification stalls onboarding, and handover recipients miss action-required notifications.
- Recommended solution: keep `log` locally, but set production to `resend`, with `resend/resend-php`, `RESEND_API_KEY`, and a verified sender domain.
- Files or server configuration to update: `.env`, `.env.example`, DNS records for the chosen provider, and provider secrets in the deployment platform.
- Step-by-step implementation:
  1. Choose one production provider.
  2. Add the provider credentials to production `.env`.
  3. Set `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`.
  4. Send a staging test email for reset-password, verification, and handover flows.
- Testing or verification steps: run the related feature tests, then manually trigger forgot-password, verification resend, and handover initiation in staging and confirm real inbox delivery.
- Deployment notes: Resend requires both the API key and a verified sender identity; do not treat the API key as sufficient by itself.
- Possible side effects: switching providers can expose SPF/DKIM/DMARC misconfiguration, throttling, or spam-folder delivery issues.
- Rollback plan: revert `MAIL_MAILER` to the previous working provider, clear config cache, and temporarily use `log` only if production mail must be disabled during incident response.

#### Part 2.2 — Configure a production queue worker
- Problem: `queue:listen` in `composer dev` is for development feedback loops, not for resilient production job execution.
- Risk if not fixed: queued mail and background jobs may never run, may block web requests, or may fail without retry visibility.
- Recommended solution: use `queue:work` under Supervisor, Forge, Laravel Cloud, systemd, or another persistent process manager.
- Files or server configuration to update: production `.env`, process-manager config, and database migrations for `jobs` plus `failed_jobs`.
- Step-by-step implementation:
  1. Keep `QUEUE_CONNECTION=database` unless a different driver is intentionally chosen.
  2. Confirm the `jobs` table is migrated.
  3. Create a failed-jobs migration with `php artisan make:queue-failed-table --no-interaction` if the table is missing, then migrate.
  4. Run `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`.
  5. Register the worker in Supervisor, Forge, Cloud, or systemd with autorestart enabled.
  6. Run `php artisan queue:restart` during each deploy so daemonized workers reload new code.
- Testing or verification steps: dispatch a known queued job in staging, stop and restart the worker once, and confirm failed jobs land in `failed_jobs` and can be retried.
- Deployment notes: worker concurrency and retry settings should match the server size and the job mix; long-running jobs may need custom `timeout`, `tries`, or `backoff`.
- Possible side effects: more retries can duplicate side effects if jobs are not idempotent; too many workers can overload a small database-backed queue.
- Rollback plan: stop the worker, revert to the prior queue config, clear cached config, and restart the old worker definition.

#### Part 2.3 — Set up Laravel scheduler cron
- Problem: Laravel schedules are definitions only; nothing runs until the host triggers `php artisan schedule:run` every minute.
- Risk if not fixed: daily inventory alert generation and any future scheduled tasks simply never execute.
- Recommended solution: configure a single system scheduler entry that runs every minute.
- Files or server configuration to update: Linux crontab or Windows Task Scheduler / Laragon task configuration.
- Step-by-step implementation:
  1. On Linux, add `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.
  2. On Windows or Laragon, create a Task Scheduler job that runs the same command every minute.
  3. Confirm the job runs under a user that can access the project and PHP binary.
- Testing or verification steps: run `php artisan schedule:run` manually once, then watch logs or database changes after the scheduled minute window.
- Deployment notes: only one scheduler trigger is needed per deployed app instance unless a clustered scheduler strategy is intentionally designed.
- Possible side effects: duplicate scheduler triggers across multiple servers can run the same command more than once unless overlap protections are used.
- Rollback plan: disable the cron or scheduled task entry and run the affected Artisan commands manually until the scheduler is repaired.

#### Part 2.4 — Create the public storage symlink
- Problem: files stored on the `public` disk resolve through `/storage`, which depends on the `public/storage` link on every deployed host.
- Risk if not fixed: uploaded avatars, documents, QR assets, signatures, and other public files return 404s or broken images.
- Recommended solution: run `php artisan storage:link` during provisioning or deployment.
- Files or server configuration to update: filesystem on the target host plus any deployment script that provisions a new server.
- Step-by-step implementation:
  1. Verify `public/storage` is absent or incorrect.
  2. Run `php artisan storage:link`.
  3. Confirm the target points to `storage/app/public`.
- Testing or verification steps: upload or expose a known public file and request it over HTTP.
- Deployment notes: on Windows, creating symlinks may require elevated privileges or developer mode; some hosts use junctions instead.
- Possible side effects: if `public/storage` already exists as a real directory, the command may fail until the incorrect path is removed.
- Rollback plan: remove the created link and restore the previous filesystem state if it was pointing to the wrong target.

#### Part 2.5 — Keep admin dashboard coverage in CI
- Problem: the critical admin-only dashboard queries are now covered, but that coverage only helps if it stays in the deployment pipeline.
- Risk if not fixed: future regressions in admin-only dashboard queries can re-enter if the targeted tests stop running in CI.
- Recommended solution: keep the focused dashboard test in the normal pre-deploy test suite.
- Files or server configuration to update: CI pipeline / deployment verification steps.
- Step-by-step implementation:
  1. Keep `tests/Feature/DashboardTest.php` in the regular test run.
  2. Fail the deployment pipeline if the dashboard feature test fails.
- Testing or verification steps: run `php artisan test --compact tests/Feature/DashboardTest.php`.
- Deployment notes: this is already implemented in the repo; the remaining task is operational discipline.
- Possible side effects: none beyond catching real regressions earlier.
- Rollback plan: do not remove the test unless it is proven incorrect; fix the regression instead.

#### Part 2.6 — Force HTTPS and update `APP_URL` in production
- Problem: without HTTPS-aware URL generation, Laravel can emit `http://` redirects or signed links in HTTPS environments.
- Risk if not fixed: mixed-content issues, broken signed URLs, incorrect password-reset links, insecure cookies, and confusing redirect behavior behind proxies.
- Recommended solution: set production `APP_URL` to the real HTTPS domain and call `URL::forceScheme('https')` in production.
- Files or server configuration to update: `app/Providers/AppServiceProvider.php`, `.env.example`, production `.env`, and reverse-proxy headers on the web server or load balancer.
- Step-by-step implementation:
  1. Set `APP_URL=https://your-real-domain`.
  2. Force the HTTPS scheme in production code.
  3. Ensure the reverse proxy forwards HTTPS headers correctly.
  4. Keep `SESSION_SECURE_COOKIE=true` in HTTPS production.
- Testing or verification steps: confirm login redirects, password reset links, verification links, storage URLs, and signed handover URLs all use HTTPS in staging.
- Deployment notes: reverse proxy deployments must preserve `X-Forwarded-Proto` and related headers so request security is detected correctly.
- Possible side effects: forcing HTTPS in an HTTP-only environment breaks local or temporary non-TLS access, so keep the behavior production-only.
- Rollback plan: remove or disable the forced scheme change, restore the prior `APP_URL`, clear config cache, and retest URL generation.

#### Part 2.7 — Add production optimization commands to deployment
- Problem: production can run without config, route, view, and event caches, but it will pay unnecessary filesystem and bootstrap overhead.
- Risk if not fixed: slower requests, more disk reads, and avoidable runtime overhead.
- Recommended solution: add `php artisan optimize` or the discrete cache commands to the deployment flow, and keep PHP OPcache enabled.
- Files or server configuration to update: deployment scripts, release checklist, process reload steps, and PHP runtime configuration for OPcache.
- Step-by-step implementation:
  1. Deploy code and environment variables.
  2. Run migrations.
  3. Run `php artisan optimize` or the discrete cache commands.
  4. Reload PHP-FPM or the relevant PHP runtime if OPcache settings require it.
  5. Restart queue workers so they boot with the new cached configuration.
- Testing or verification steps: run the optimization commands in staging, confirm they succeed, browse key routes, and roll a worker restart.
- Deployment notes: test optimization in staging first because config syntax issues or route serialization problems will fail the deploy.
- Possible side effects: stale caches can keep old config or route behavior alive if workers or PHP processes are not reloaded.
- Rollback plan: run `php artisan optimize:clear`, reload the PHP runtime, and redeploy with the last known-good build.

### Testing And Verification Checklist

- Automated tests:
  - `php artisan test --compact tests/Feature/DashboardTest.php`
  - Auth mail-flow feature tests already in `tests/Feature/Auth/*`
- Manual checks:
  - Forgot password email arrives in a real inbox.
  - Verification email arrives and opens an HTTPS link.
  - Handover initiation sends an email to the target user.
  - A queued job is processed by the persistent worker.
  - The daily inventory alert command can be triggered through the scheduler path.
  - A known `public` disk file loads through `/storage/...`.
- Deployment verification:
  - `php artisan optimize` succeeds.
  - `php artisan config:cache` succeeds with the production `.env`.
  - `php artisan queue:restart` completes during deployment.
  - Generated URLs and redirects stay on HTTPS.

### Deployment Checklist

- Environment variables:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://your-real-domain`
  - `MAIL_MAILER` plus provider credentials
  - `QUEUE_CONNECTION=database`
  - `SESSION_ENCRYPT=true`
  - `SESSION_SECURE_COOKIE=true`
- Services:
  - Real mail provider configured
  - Persistent queue worker configured
  - Scheduler cron or task configured
  - `public/storage` link created
- Runtime:
  - Config, route, view, and event caches built
  - OPcache enabled
  - Queue workers restarted after deploy
- Documentation:
  - `.env.example` reflects production-safe defaults and notes
  - Deployment steps reference mail, queue, scheduler, storage, HTTPS, and optimization

### Rollback Strategy

- Keep infrastructure rollbacks separate from application-code rollbacks so mail, queue, and HTTPS changes can be reversed independently.
- For config mistakes, restore the last known-good `.env`, run `php artisan optimize:clear`, then rebuild caches.
- For queue issues, stop workers first, restore the previous worker definition, and restart with the old command.
- For HTTPS regressions, revert the forced scheme change and proxy config together, then test redirects and signed URLs again.
- For deployment-cache issues, clear optimized caches before assuming the code itself is broken.

### Final Recommendation

The safest order is: keep the resolved P0 fixes locked in with tests, then configure Resend, the queue worker, the scheduler, the per-server storage link step, HTTPS deployment values, and deployment optimization in staging before touching production. The quickest remaining wins are the Resend production env values, `APP_URL` / proxy verification, and deployment automation for `storage:link`; the higher-risk items are the server-managed queue worker, reverse-proxy HTTPS behavior, and mail-provider cutover because they depend on infrastructure outside the repo.

---

## P2 — Important (First Production Sprint)

These improvements are critical for long-term stability, security, and developer productivity.

Completed and removed from the active backlog during this validation:
- P2.1 — removed the default `inspire` command from `routes/console.php`
- P2.2 — moved the misplaced inventory tests into `tests/Feature/Inventory/` and removed the orphaned nested directory
- P2.3 — replaced the hard booking record cap with paginated booking records; booking asset and handover recipient selectors are now bounded and searchable while the clearly labeled recent handovers section intentionally remains limited
- P2.4 — implemented requisition rejection end-to-end with route, controller, policy, validation, and Inertia UI support
- P2.5 — cached product category/origin reference options with `Cache::remember()` and added cache invalidation on `Category`/`Origin` save and delete
- P2.6 — added feature coverage for product show/edit, booking index/rejection, handover index/verify/receipt, requisition store/index/show/reject, stock movements index, receiving index, product label output, and related regressions
- P2.7 — orphaned factories are now directly used in tests, including `BookingFactory`, `RequisitionFactory`, `HandoverLogFactory`, `StockMovementFactory`, and `DepartmentFactory`
- P2.8 — added dedicated `InventoryService` tests covering consumable receiving, asset receiving, duplicate tags, expiry-first issuance, insufficient-stock rollback, and invalid-status guards
- P2.10 — added `max:300000` server-side validation for `signature_png` plus a client-side signature-size guard in the handover verification page

---

## P3 — Quality (Improve Maintainability)

These improvements make the codebase cleaner, safer, and easier to maintain.

Completed and removed from the active backlog during this validation:
- P3.1 — domain `type`/`status` fields now use backed enums in `app/Enums/`, model casts are active on `Product`, `Asset`, `Booking`, and `Requisition`, string-based policy/service/controller logic has been normalized to enum references, Inertia props still emit the existing user-facing string labels, and the current local database contains `0` invalid `products.type`, `assets.status`, `bookings.status`, or `requisitions.status` values.
- P3.2 — inventory prop transformations now live in dedicated resource classes and paginator-aware resource collections under `app/Http/Resources`; the inventory controllers are simpler, existing Inertia prop names and paginator keys were preserved, and focused serialization coverage now lives in `tests/Feature/Inventory/InventoryResourceTest.php`. The Settings controllers were reviewed and did not need resource extraction because they do not contain inline model collection mapping.
- P3.3 — ESLint now warns on `vue/multi-word-component-names` and `@typescript-eslint/no-explicit-any`; the loose `usePage()` auth cast and signature-pad `any` usage were replaced with concrete types, `npx vue-tsc --noEmit` now passes, and the current ESLint output is down to the expected 21 single-word component warnings.
- P3.4 — the remaining cast gap is resolved: `Product` now casts `reorder_threshold` to `integer`, while the existing enum, boolean, and datetime casts across the inventory models were re-verified against the current code paths and tests.
- P3.5 — added a dedicated follow-up migration for inventory query indexes on `stock_movements.movement_type`, `stock_movements.performed_by`, `products.type`, and the composite `products(is_active, type)` path. The migration safely skips a redundant standalone `products.sku` index because the existing `products_sku_unique` index already covers SKU lookups, and migrate/rollback were both verified locally.
- P3.6 — `Dashboard.vue` no longer imports `chart.js/auto`; it now registers only the bar-chart modules the page actually uses, preserving the existing chart behavior while trimming unnecessary Chart.js registration work from the bundle.
- P3.7 — short-lived `Cache-Control: private, max-age=30` response headers are now applied only to a safe whitelist of non-sensitive Inertia GET pages via `HandleInertiaRequests`, while sensitive settings pages remain excluded. Focused feature coverage now checks both the cached and excluded paths without relying on header token order.
- P3.8 — inventory routes in `routes/web.php` are now consolidated into shared middleware groups without changing route names, URIs, controller actions, or effective access levels. `php artisan route:list -v --path=inventory --except-vendor` confirmed the grouped routes still resolve to the same middleware combinations, and focused authorization coverage now lives in `tests/Feature/Authorization/InventoryRouteAccessTest.php`.
- P3.10 — the application timezone now resolves from `APP_TIMEZONE` with an `Asia/Manila` default in `config/app.php`, `APP_TIMEZONE=Asia/Manila` is documented in `.env.example`, and the local `.env` was updated accordingly. `php artisan config:clear`, `php artisan config:show app.timezone`, and `tests/Feature/ApplicationConfigurationTest.php` verified the active config now resolves to `Asia/Manila`, while the production rollout note remains to evaluate any existing UTC data before deployment.

---

## Remaining Planned Improvements

No remaining Future-tier enhancements are pending. All previously planned Future items have been fully implemented.

The only remaining production-readiness work is in **P1 — Required**, which covers server/runtime configuration (mailer, queue worker, scheduler, storage link, HTTPS, optimization) rather than code changes.

---

## Summary: Priority Matrix

| Tier | Count | Items | Total Est. Effort |
|---|---|---|---|
| **P0 — Critical** | 0 | Resolved and retained only as validated notes | complete |
| **P1 — Required** | 7 | Resend mailer, queue worker, cron, storage link deployment step, app name verification, HTTPS deployment values, production optimization | ~2–4 hours plus server provisioning |
| **P2 — Important** | 0 | Resolved and retained only as validated notes | complete |
| **P3 — Quality** | 0 | Resolved and retained only as validated notes | complete |
| **Future** | 0 | All Future-tier enhancements fully implemented | complete |

**Total active items:** 7 improvements across 5 tiers (all P1 deployment tasks)  
**Total estimated effort for remaining production readiness:** ~2–4 hours plus server provisioning and DNS / sender verification

---

## Quick-Win Checklist (Do These First)

If you only have 2 hours, do these in order:

- [ ] **P1.6** — Configure Resend production env values and verified sender (15–30 min + DNS/provider validation)
- [ ] **P1.9** — Add `php artisan storage:link` to production provisioning / deploy automation (1 min)
- [ ] **P1.11** — Set `APP_NAME` to "PUP PRISM" (1 min)
- [ ] **P1.13** — Set the real production `APP_URL` and validate proxy HTTPS headers (3 min)
- [ ] **P1.7** — Configure a persistent queue worker (30 min)
- [ ] **P1.8** — Configure the scheduler trigger (2 min)

**Total for quick wins:** ~1–2 hours plus any mail-provider verification time — addresses the highest-signal remaining deployment gaps first.

---

## Changelog

| Date | Changes |
|---|---|
| 2026-06-03 | **Verified** all items against actual codebase. Added `Risk` and `Verified` fields to every item. Corrected inaccuracies: P1.4 (logs not tracked by git), P3.1 (PHP 8.4 not 8.1), P3.4 (Booking already has casts; Product missing casts), Future.5 (no soft deletes exist), P1.11 (overly long APP_NAME warning). Added P0.5 (`authorizeResource` redundancy). Enhanced P2.3 (unlimited asset loading), P2.4 (booking rejection exists), P2.6 (nuanced test gap table), P2.7 (clarified used vs unused factories). Updated totals from 40 to 41 items. |
| 2026-06-03 (2nd pass) | **Re-evaluated** against additional files (`AppServiceProvider`, `config/fortify.php`, `vite.config.ts`, `composer.json`, `HandoverController`). Added 6 new items: P1.12 (`composer.json` identity), P1.13 (HTTPS + timezone), P1.14 (production optimization), P2.10 (`signature_png` DoS), P3.9 (Wayfinder build requirement), P3.10 (timezone). Updated Summary counts and Quick-Win checklist. Total items: 47. |
| 2026-06-03 (3rd pass) | **Validated** the repo again after the Resend integration pass. Installed `resend/resend-php`, aligned mail docs/config around Resend, removed stale completed backlog items (README, dead code, `.gitattributes`, log isolation, `.env.testing`, admin dashboard coverage, composer identity, `.env.example` and Wayfinder doc gaps), and recalculated the remaining active counts to 34. |
| 2026-06-03 (4th pass) | **Resolved** P0.5 by removing redundant product route middleware and relying on `ProductPolicy` as the single authorization source for `ProductController`. Added a regression test for verified users without inventory roles and recalculated the remaining active counts to 33. |
| 2026-06-03 (5th pass) | **Implemented** the remaining P2 sprint items: removed `inspire`, fixed nested inventory tests, added requisition rejection, bounded and paginated booking/handover data loading, cached product reference data with invalidation, expanded inventory feature coverage, added `InventoryService` tests, added `signature_png` size guards, updated the stale stock movement factory column, and reduced the remaining active counts to 24. |
| 2026-06-03 (6th pass) | **Implemented** P3.1 by completing the backed-enum migration for product types and inventory statuses across models, controllers, policies, services, factories, seeders, and tests. Preserved existing UI labels by serializing enum values back to strings for Inertia props, verified focused Pest coverage passes, confirmed the current local database has zero invalid enum-domain records, and reduced the remaining active counts to 23. |
| 2026-06-03 (7th pass) | **Implemented** P3.2, P3.3, and P3.4 by extracting inventory prop shaping into dedicated API Resources and paginator-aware resource collections, re-enabling the stricter ESLint rules as warnings, replacing the remaining frontend `any` usage with concrete shared/auth and signature-pad types, adding the missing `Product::reorder_threshold` cast, and adding focused resource serialization tests. Verified with `php artisan test --compact` on the affected inventory/dashboard suites, `npx vue-tsc --noEmit`, and `npx eslint eslint.config.js resources/js` (warnings only for 21 existing single-word component names). Remaining active counts reduced to 20. |
| 2026-06-03 (8th pass) | **Implemented** P3.5, P3.6, and P3.7 by adding a safe follow-up index migration for inventory query hotspots, replacing `chart.js/auto` with selective bar-chart registration on the dashboard, and applying short-lived private cache headers only to a safe whitelist of Inertia listing/dashboard routes. Verified with local migrate/rollback runs for the new migration, `npm run build`, `vendor/bin/pint --dirty --format agent`, and focused feature coverage for dashboard, product index, and cache-header exclusions. Remaining active counts reduced to 17. |
| 2026-06-03 (9th pass) | **Implemented** P3.8 and P3.10 by consolidating repeated inventory route middleware into shared groups without changing the effective role guards, adding focused authorization regression coverage, and switching the application timezone to `APP_TIMEZONE` with an `Asia/Manila` default documented in `.env.example` and applied locally in `.env`. Verified with `php artisan route:list -v --path=inventory --except-vendor`, `php artisan config:clear`, `php artisan config:show app.timezone`, focused Pest coverage, `npm run lint:check` (warnings only), `npm run types:check`, and `npm run build`. Remaining active counts reduced to 15. |
| 2026-06-04 | **Implemented** all remaining Future-tier items: Future.1 (Playwright E2E testing, 7 suites passing), Future.2 (CSV/PDF report exports for 5 report types with role-based access), Future.3 (stock movement audit viewer with date/user/type filters and export), Future.4 (email notifications for all 7 workflow events via `NotificationService`), Future.6 (batch stock receiving with multi-line form and validation), Future.7 (Sanctum-authenticated REST API with rate limiting, policies, and comprehensive tests), Future.8 (dashboard widgets with date-range filtering, trend charts, and cached query service). Added completed items to new "Completed / Implemented Improvements" section. Reclassified Future.5 (soft deletes) as Partially Implemented — migrations, models, controllers, Trash UI, and tests exist, but `deleted_by` tracking and deletion reasons remain pending. Updated Summary counts: Future reduced from 8 to 1, total active items from 15 to 8. Verified with `php artisan test --compact` (143 passed), `npm run build` (successful), and `npx playwright test` (7 passed). |
| 2026-06-04 (2nd pass) | **Completed** Future.5 (soft deletes) by adding `deleted_by` and `deletion_reason` columns via follow-up migrations for `products`, `bookings`, and `requisitions`; adding `deletedBy` relationships to all three models; updating `destroy()` and `trash()` actions in all three controllers to track and display the deleter and reason; adding a deletion-reason Dialog to the product Edit page; enriching per-model Trash Vue pages with "Deleted by" and "Reason" columns; creating a unified cross-model trash viewer (`TrashController` + `inventory/Trash.vue`) with search and type filtering at `/inventory/trash`; and expanding `SoftDeletesTest` with `deleted_by` tracking, deletion reason, and unified trash viewer coverage. Moved Future.5 from Partially Implemented to Completed. Updated Summary counts: Future reduced from 1 to 0, total active items from 8 to 7. Verified with `php artisan test --compact` (149 passed, 741 assertions) and `npm run build` (successful). |
