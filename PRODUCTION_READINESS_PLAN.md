# PUP PRISM — Production Readiness Improvement Plan

> **Purpose:** A comprehensive, prioritized roadmap to take this project from its current state to production-grade quality.
> **Classification:** Each item is tagged with a priority level, risk severity, and estimated effort.
> **Total items:** 47 improvements across 5 priority tiers.
> **Last verified against codebase:** 2026-06-03

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

## P0 — Critical (Must Fix Immediately)

These are non-negotiable fixes. Deploying to production without addressing these introduces unacceptable risk.

### P0.1 — Replace PostgreSQL-specific `::int` cast with portable SQL

**Location:** `app/Http/Controllers/DashboardController.php:46`

**Risk:** Database portability failure — the application will crash on MySQL, MariaDB, or SQL Server.

**Problem:** The query uses PostgreSQL's `::int` type-cast operator inside `DB::raw()`:
```php
->select('status', DB::raw('COUNT(*)::int as aggregate'))
```
This will **throw a SQL syntax error** on any non-PostgreSQL database. The existing tests do not catch this because `DashboardTest.php` creates a non-Admin user, so the admin-specific block (lines 27–59) is never executed in test.

**Fix:** Replace with the ANSI-SQL portable `CAST()`:
```php
->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as aggregate'))
```

**Effort:** 2 minutes  
**Verified:** Yes — source confirmed.

---

### P0.2 — Remove or implement `reserved_qty`

**Locations:**
- `database/migrations/..._create_product_stocks_table.php:19` — column definition
- `app/Models/ProductStock.php:11` — fillable attribute
- `app/Services/Inventory/InventoryService.php:69` — initialized to `0`
- `app/Http/Controllers/Inventory/ProductController.php` — read in 4 places (index eager load, index display, show eager load, show display)
- `database/factories/ProductStockFactory.php:22-27` — factory generates fake values that are never used

**Risk:** Dead schema debt, confusing UI (users see `reserved_qty: 0` with no explanation), and wasted query bandwidth.

**Problem:** The `reserved_qty` column on `product_stocks` is **always `0`** in practice — it is never incremented or decremented anywhere in the business logic. It is read and displayed in the UI as `0`, occupying database storage and query bandwidth with no functional purpose. The `ProductStockFactory` generates random `reserved_qty` values for tests, but these are never validated or asserted.

**Option A (Remove):** Drop the column in a new migration. Remove from `ProductStock::fillable`, `ProductController` eager loads and prop mappings, and `ProductStockFactory`.
**Option B (Implement):** Add reservation logic:
- Decrement `reserved_qty` when a requisition is submitted
- Increment `on_hand_qty` and decrement `reserved_qty` when a requisition is issued
- Revert `reserved_qty` (increment) if a requisition is cancelled/rejected
- Check `on_hand_qty - reserved_qty >= qty_requested` when issuing

**Recommended:** Option A unless the reservation feature is actively planned. The column adds complexity with zero value. If implementing Option B, also add a `available_qty` computed accessor to simplify frontend display.

**Effort:** Option A: 15 minutes | Option B: 1–2 days  
**Verified:** Yes — all write paths audited.

---

### P0.3 — Make `SESSION_ENCRYPT=true` in production

**Location:** `.env.example:32` — `SESSION_ENCRYPT=false`

**Risk:** Information disclosure — session data (user IDs, auth state, flash messages) is stored in the `sessions` table in plaintext.

**Problem:** Session data is stored in the `sessions` table **unencrypted**. With `SESSION_DRIVER=database`, any database read access (backup dumps, SQL injection, compromised read-only account) exposes active session contents. For a university system handling property accountability data, this is a significant information disclosure risk.

**Fix:** Add to deployment `.env`:
```ini
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SESSION_SECURE=true   # if HTTPS is enforced
```
And update `.env.example` to show `true` as the recommended default with an explanatory comment.

> **Note:** Enabling `SESSION_ENCRYPT` on an existing production database will invalidate all existing sessions. Plan this change during a maintenance window.

**Effort:** 2 minutes  
**Verified:** Yes — `.env.example` line 32 confirmed.

---

### P0.4 — Secure the `$appearance` cookie rendering in Blade

**Location:** `resources/views/app.blade.php:10`

**Risk:** Cross-Site Scripting (XSS) — a fragile pattern that could become exploitable if refactored.

**Problem:** A user-controlled cookie value is interpolated directly into a `<script>` context:
```php
const appearance = '{{ $appearance ?? "system" }}';
```
While Blade's `{{ }}` escapes HTML entities (preventing *current* exploitation), this is a **fragile pattern**. If anyone changes this to `{!! !!}` (unescaped Blade output) in a future refactor, it becomes **critical XSS** immediately. The value originates from a cookie (`$request->cookie('appearance')` in `HandleAppearance.php:19`), which is fully user-controllable.

**Fix (defense in depth):**
1. Restrict to known valid options in the middleware (`app/Http/Middleware/HandleAppearance.php`):
```php
$valid = ['light', 'dark', 'system'];
$appearance = in_array($request->cookie('appearance'), $valid, true)
    ? $request->cookie('appearance')
    : 'system';
View::share('appearance', $appearance);
```
2. Use JSON-safe encoding in the Blade template:
```php
const appearance = @json($appearance ?? 'system');
```

**Effort:** 5 minutes  
**Verified:** Yes — both `app.blade.php:10` and `HandleAppearance.php:19` confirmed.

---

### P0.5 — Fix `authorizeResource` mismatch in `ProductController`

**Location:** `app/Http/Controllers/Inventory/ProductController.php:23`

**Risk:** Authorization bypass or unexpected 403 responses due to implicit policy-method mapping.

**Problem:** `ProductController` calls `$this->authorizeResource(Product::class, 'product')` in its constructor. This auto-maps controller methods to `ProductPolicy` methods (`index` → `viewAny`, `show` → `view`, etc.). However, `ProductController::index()` is also protected by `'role:Admin|Supply Head|Property Custodian'` middleware in `routes/web.php`, creating **overlapping and potentially conflicting authorization layers**. If the route middleware is removed or changed, the policy behavior may not match expectations. Additionally, `authorizeResource` will call `create` for both `create()` and `store()` methods, but the route middleware restricts `POST products` to `Admin|Supply Head` while `ProductPolicy::create()` also checks `Admin|Supply Head` — this is redundant but consistent.

**Fix:** Either:
- Remove `authorizeResource` and rely exclusively on route-level middleware (less granular but explicit), **or**
- Remove route-level role middleware from product routes and let `ProductPolicy` handle everything (more granular, testable).

**Recommended:** Keep the policy and remove the redundant route middleware for product routes. This centralizes authorization logic in one testable place.

**Effort:** 15 minutes  
**Verified:** Yes — `ProductPolicy` and `routes/web.php` cross-checked.

---

## P1 — Required (Before Production Deployment)

These are necessary for a stable, secure, and operable production system.

### P1.1 — Create a README.md

**Location:** (missing) project root

**Risk:** Onboarding friction, operational errors, and single points of failure when only one person knows how to deploy.

**Problem:** There is no documentation for onboarding new developers or operators. No setup instructions, no architecture overview, no deployment guide.

**Required sections:**
```
README.md
├── Project Overview (what it does, who it's for)
├── Tech Stack (Laravel 13, Vue 3, Inertia 3, PostgreSQL, Tailwind CSS v4, etc.)
├── Prerequisites (PHP 8.4, PostgreSQL 16+, Node.js 22+, Composer 2+)
├── Setup Instructions
│   ├── Clone & install dependencies
│   ├── Database setup (create PostgreSQL database)
│   ├── Environment configuration (.env)
│   ├── Run migrations & seeders
│   ├── Build frontend assets
│   └── Start dev server
├── Default Accounts (admin@local.test / password, etc.)
├── Project Architecture
│   ├── Backend structure (app/ directory)
│   ├── Frontend structure (resources/js/ directory)
│   └── Database schema overview
├── Testing (how to run tests)
├── Deployment Checklist
│   ├── Environment variables to set
│   ├── Queue worker setup
│   ├── Cron job setup
│   ├── Mail configuration
│   └── SSL/HTTPS
└── Roles & Permissions (Admin, Supply Head, Property Custodian)
```

**Effort:** 1–2 hours  
**Verified:** Yes — no README.md exists in project root.

---

### P1.2 — Remove dead code (4 items)

**Locations:**
1. `database/factories/SaleFactory.php` — references `App\Models\Sale` (doesn't exist)
2. `database/factories/SaleLineFactory.php` — references `App\Models\SaleLine` (doesn't exist)
3. `app/Http/Requests/Inventory/CheckoutRequest.php` — references non-existent roles `'Admin/Manager'` and `'Cashier'`, no controller or route uses it
4. `pnpm-workspace.yaml` — leftover, project uses npm, not pnpm

**Risk:** Fatal errors if dead factories are ever instantiated; confusion for new developers encountering outdated role names.

**Problem:** The `SaleFactory` and `SaleLineFactory` reference model classes that don't exist. If these factories are ever instantiated (e.g., in a future test or seeder), they will throw `Class "App\Models\Sale" not found` fatal errors. The `CheckoutRequest` references authorization roles that don't exist (`Admin/Manager`, `Cashier`) — only `Admin`, `Supply Head`, `Property Custodian` exist in the system. These artifacts are remnants of an earlier POS/sales system (evidenced by the comment in `ReceivingCheckoutTest.php`: "Checkout/sales flow removed; replaced by requisition issuance & digital handover").

**Fix:** Delete these 4 files:
- `database/factories/SaleFactory.php`
- `database/factories/SaleLineFactory.php`
- `app/Http/Requests/Inventory/CheckoutRequest.php`
- `pnpm-workspace.yaml`

**Effort:** 5 minutes  
**Verified:** Yes — all 4 files confirmed present and unused.

---

### P1.3 — Add a `.gitattributes` export rule for `production` branch

**Location:** `.gitattributes` (exists)

**Risk:** Production archives may include unnecessary development files, increasing deployment size.

**Problem:** The current `.gitattributes` exports `CHANGELOG.md` and `README.md` as `export-ignore`, but neither file exists (they're treated as documentation placeholders). The file is otherwise correct for line-ending normalization and diff handling.

**Fix:** No change needed for the existing rules. However, add `export-ignore` for development-only files that should not reach production:
```gitattributes
/tests export-ignore
/phpunit.xml export-ignore
/pint.json export-ignore
/.editorconfig export-ignore
```

> **Note:** `storage/logs/laravel.log` is **not** tracked by git (it is properly ignored by `storage/logs/.gitignore` which contains `*` and `!.gitignore`). It may exist locally but will not be committed.

**Effort:** 5 minutes  
**Verified:** Yes — `.gitattributes` reviewed; `storage/logs/.gitignore` confirmed active.

---

### P1.4 — Harden `storage/logs` git isolation

**Location:** `storage/logs/laravel.log` (exists locally, contains connection errors and stack traces)

**Risk:** Accidental commit of log files containing file paths, DB connection errors, and environment details.

**Problem:** While `storage/logs/.gitignore` currently ignores all files in that directory (`*` + `!.gitignore`), the root `.gitignore` does **not** explicitly list `/storage/logs/*.log`. If the `storage/logs/.gitignore` file is ever deleted or corrupted, log files could leak into the repository. Defense in depth is warranted.

**Fix:** Add a redundant rule to the root `.gitignore`:
```bash
# Root .gitignore — add these lines
echo "/storage/logs/*.log" >> .gitignore
echo "!/storage/logs/.gitkeep" >> .gitignore
```

Also verify `storage/logs/.gitignore` contains:
```
*
!.gitignore
```

**Effort:** 2 minutes  
**Verified:** Yes — `git ls-files` confirms `storage/logs/laravel.log` is not tracked. Root `.gitignore` reviewed.

---

### P1.5 — Add a `.env.testing` file

**Location:** (missing)

**Risk:** CI pipeline failures due to inflexible test configuration; tests accidentally running against the wrong database.

**Problem:** Currently, all test environment configuration lives in `phpunit.xml`. While functional, this lacks flexibility for CI pipelines (GitHub Actions, GitLab CI) that may need to override specific settings without modifying `phpunit.xml`.

**Fix:** Create `.env.testing`:
```ini
APP_ENV=testing
APP_KEY=base64:TEST_KEY_HERE
APP_DEBUG=true
BCRYPT_ROUNDS=4
CACHE_STORE=array
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
```

> **Note:** Generate a real `APP_KEY` with `php artisan key:generate --env=testing` rather than using a placeholder.

**Effort:** 5 minutes  
**Verified:** Yes — no `.env.testing` file exists.

---

### P1.6 — Configure a production mailer

**Location:** `.env` — `MAIL_MAILER=log`

**Risk:** Users cannot reset passwords, verify emails, or receive handover notifications — all email-dependent workflows are silently broken.

**Problem:** All emails (password reset, email verification, handover verification notifications) are written to the log file — they are never actually delivered. This means:
- Users cannot reset their passwords via email
- Handover verification notifications are never sent to recipients
- Email verification links must be retrieved manually from logs

**Fix:** In production `.env`, configure one of:
```ini
# Option A: SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@pup.edu.ph
MAIL_FROM_NAME="PUP PRISM"

# Option B: Amazon SES
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@pup.edu.ph

# Option C: Postmark
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-token
```

Also update `.env.example` to include a comment: `# In production: change to smtp, ses, or postmark`.

**Effort:** 15–30 minutes  
**Verified:** Yes — `.env.example` line 50 confirms `MAIL_MAILER=log`.

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

> **Note:** With `QUEUE_CONNECTION=database`, ensure the `jobs` table exists (`php artisan queue:table` then migrate) before starting the worker.

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

**Problem:** The symlink from `public/storage` → `storage/app/public` does not exist. Any publicly stored files will not be accessible via the web. The root `.gitignore` already ignores `/public/storage`, which is standard Laravel behavior.

**Fix:**
```bash
php artisan storage:link
```

On Windows with Laragon, ensure the command is run with appropriate privileges (symlink creation may require Administrator privileges).

**Effort:** 1 minute  
**Verified:** Yes — `public/` directory listing confirms no `storage` symlink exists.

---

### P1.10 — Add admin dashboard test coverage

**Location:** `tests/Feature/DashboardTest.php`

**Risk:** PostgreSQL-specific syntax errors (like P0.1) and admin-only query regressions go undetected until production.

**Problem:** The dashboard test creates a non-admin user, so the entire admin-specific block (alerts, low stock queries, asset status counts, and the `::int` PostgreSQL query) is NEVER executed in tests. A SQL syntax error in this block would go undetected.

**Fix:** Add a second test method for admin dashboard:
```php
test('admin users see dashboard data', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    // Create some test data
    Product::factory()->consumable()->create(['reorder_threshold' => 10]);
    ProductStock::factory()->create(['on_hand_qty' => 5, 'reserved_qty' => 0]);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('alerts')
        ->has('lowStock')
        ->has('assetStatusCounts')
    );
});
```

**Effort:** 15 minutes  
**Verified:** Yes — `DashboardTest.php` contains only 2 tests, neither with Admin role.

---

### P1.11 — Set `APP_NAME` and application identity

**Location:** `.env:1` — `APP_NAME=Laravel`

**Risk:** Unprofessional branding, confusing email subjects, and potential cookie-name collisions if multiple Laravel apps share a domain.

**Problem:** The application name is still the default "Laravel" placeholder. This affects email subject lines (`MAIL_FROM_NAME`), document titles (via `config('app.name')`), and cookie names (Laravel hashes `APP_NAME` into cookie prefixes).

**Fix:** Update `.env` and `.env.example`:
```ini
APP_NAME="PUP PRISM"
```

> **Warning:** Do not use an excessively long name (e.g., "PUP Property & Resource Inventory System Management"). Laravel hashes `APP_NAME` into cookie and cache key prefixes; very long names can cause cookie truncation issues and clutter cache keys. Keep it concise.

**Effort:** 1 minute  
**Verified:** Yes — `.env.example` line 1 confirmed.

---

### P1.12 — Update `composer.json` package identity

**Location:** `composer.json:3` — `"name": "laravel/vue-starter-kit"`

**Risk:** Confusion in package management, dependency resolution, and developer onboarding; incorrect PHP version constraint may block updates or allow incompatible environments.

**Problem:** The `composer.json` still has the default starter kit package name `"laravel/vue-starter-kit"`. Additionally, the PHP requirement is `"^8.3"`, but the codebase uses PHP 8.4-specific features (e.g., constructor property promotion with `new` in initializers, typed class constants if any). Running this on PHP 8.3 would fail if 8.4 features are actually used. The AGENTS.md explicitly lists PHP 8.4 as the target version.

**Fix:** Update `composer.json`:
```json
{
    "name": "pup/prism",
    "require": {
        "php": "^8.4",
        ...
    }
}
```

Run `composer validate` after editing to ensure the file is valid JSON.

**Effort:** 2 minutes  
**Verified:** Yes — `composer.json` lines 3 and 12 confirmed.

---

### P1.13 — Force HTTPS and update `APP_URL` in production

**Location:** `app/Providers/AppServiceProvider.php:24` + `.env.example:5`

**Risk:** Mixed-content warnings, insecure cookie transmission, and session hijacking on shared networks.

**Problem:** `AppServiceProvider::boot()` does not call `URL::forceScheme('https')` for production environments. This means if the app is deployed behind an HTTPS-terminated load balancer or reverse proxy, Laravel may generate `http://` URLs for redirects, asset links, and signed URLs. Additionally, `.env.example` sets `APP_URL=http://localhost` with no comment about production HTTPS.

**Fix:** Add to `AppServiceProvider::boot()`:
```php
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    $this->configureDefaults();

    if (app()->isProduction()) {
        URL::forceScheme('https');
    }
}
```

And update `.env.example`:
```ini
# In production: use your HTTPS domain
APP_URL=http://localhost
```

**Effort:** 3 minutes  
**Verified:** Yes — `AppServiceProvider.php` reviewed; no `URL::forceScheme` found.

---

### P1.14 — Add production optimization commands to deployment

**Location:** Deployment checklist (P1.1 README) + `composer.json` scripts

**Risk:** Slower response times, higher memory usage, and unnecessary file system reads in production.

**Problem:** The deployment checklist in P1.1 does not mention Laravel's production optimization commands. Without these, every request re-reads config files, route definitions, and Blade views from disk. The `composer.json` `setup` script includes `npm run build` but does not include `php artisan optimize`.

**Fix:** Add these steps to the deployment checklist (and to `composer.json` `setup` script if appropriate):
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
**Verified:** Yes — `composer.json` `setup` script reviewed; no optimization commands present.

---

## P2 — Important (First Production Sprint)

These improvements are critical for long-term stability, security, and developer productivity.

### P2.1 — Remove `inspire` default console command

**Location:** `routes/console.php:7-9`

**Risk:** Operational clutter — developers may confuse the boilerplate command with real functionality.

**Problem:** The default Laravel boilerplate `inspire` command adds noise to `php artisan list`. It serves no purpose in this application.

**Fix:** Delete lines 7-9 from `routes/console.php`, leaving only the scheduled command.

**Effort:** 1 minute  
**Verified:** Yes — lines 7-9 confirmed.

---

### P2.2 — Consolidate test directory nesting

**Location:** `tests/Feature/Feature/Inventory/` (5 files)

**Risk:** Developer confusion, broken autoloading expectations, and tests that are easy to miss.

**Problem:** Tests are split across two parallel directories:
```
tests/Feature/Inventory/           (4 files)
tests/Feature/Feature/Inventory/   (5 files — WRONG)
```
The extra `Feature/` nesting is unintentional and confusing. It likely resulted from a copy-paste error during test generation.

**Fix:** Move all 5 files from `tests/Feature/Feature/Inventory/*` to `tests/Feature/Inventory/`. Update namespaces from `Tests\Feature\Feature\Inventory` to `Tests\Feature\Inventory`. Delete the orphaned `tests/Feature/Feature/` directory.

> **Files to move:**
> - `AuditComplianceTest.php`
> - `BookingAvailabilityTest.php`
> - `HandoverVerificationTest.php`
> - `RequisitionIssuanceTest.php`
> - `UatSeederTest.php`

**Effort:** 10 minutes  
**Verified:** Yes — directory structure confirmed.

---

### P2.3 — Replace hard `limit(200)` with pagination

**Locations:**
- `BookingController.php:41` — `Booking::query()->...->limit(200)` on bookings list
- `BookingController.php:23-27` — `Asset::query()->...->get()` loads **all** assets with no limit
- `HandoverController.php:28` — `User::query()->...->limit(200)` on user list
- `HandoverController.php:52` — `HandoverLog::query()->...->limit(20)` on recent handovers

**Risk:** Silent data loss in the UI — records become inaccessible as the dataset grows.

**Problem:** Hard limits silently hide records as the system grows. After 200 bookings, the oldest ones disappear from the UI with no warning or navigation to view them. The assets list in `BookingController::index()` has **no limit at all**, which will cause severe performance degradation as the asset registry grows. The `limit(20)` on recent handovers is acceptable since it is explicitly labeled "recent."

**Fix:**
1. Replace `->limit(200)` on bookings with `->paginate(50)`.
2. Add pagination or a reasonable limit to the assets query in `BookingController::index()`.
3. For the handover user list, consider a search-as-you-type endpoint instead of loading 200 users:
```php
// Instead of limit(200)
->when($request->filled('search'), function ($q, $search) {
    $q->where('name', 'like', "%{$search}%");
})
->paginate(50)
```

**Effort:** 30–45 minutes  
**Verified:** Yes — all 4 query locations confirmed.

---

### P2.4 — Add requisition rejection feature

**Locations:**
- `routes/web.php` — no reject route exists
- `RequisitionController.php` — no `reject()` method
- `RequisitionPolicy.php` — no `reject()` method
- `RequisitionApproveRequest.php` — only approve action allowed

**Risk:** Users cannot stop unwanted requisitions, leading to inventory being issued for requests that should have been denied.

**Problem:** The database schema allows `'Rejected'` as a valid requisition status (CHECK constraint includes it), but there is no controller action, route, or UI to reject a requisition. Once submitted, a requisition can only move forward (Approved → Issued). There is no way to formally reject a request. Note: **Booking rejection IS implemented** (`BookingController::update` handles both approve and reject actions), so this gap is specific to requisitions.

**Fix:**
1. Add `reject()` method to `RequisitionPolicy` (same role check as `approve()`)
2. Add `reject()` method to `RequisitionController`
3. Add route: `PUT /inventory/requisitions/{requisition}/reject`
4. Update frontend to show Reject button for submitted requisitions (next to Approve)
5. Consider adding a `rejected_at` timestamp and `rejected_by` fields to `Requisition` for full audit trail

**Effort:** 1–2 hours  
**Verified:** Yes — `RequisitionPolicy` and `routes/web.php` confirmed. Booking rejection confirmed as implemented.

---

### P2.5 — Cache reference data

**Locations:**
- `ProductController.php:76-77` — categories + origins queried on every index
- `ProductController.php:87-88` — categories + origins queried on every create
- `ProductController.php:161-162` — categories + origins queried on every edit
- `BookingController.php:23-27` — assets queried on every booking page
- (and similar patterns in other controllers)

**Risk:** Unnecessary database load and slower page response times.

**Problem:** Categories, origins, and assets are near-static reference data that rarely changes but is queried from the database on every page load. There is zero caching anywhere in the application.

**Fix:** Use `Cache::remember()` with a reasonable TTL:
```php
use Illuminate\Support\Facades\Cache;

// In controller:
$categories = Cache::remember('categories:all', 3600, fn () =>
    Category::query()->orderBy('name')->get(['id', 'name'])
);
$origins = Cache::remember('origins:all', 3600, fn () =>
    Origin::query()->orderBy('name')->get(['id', 'name'])
);
```

Add a `Cache::forget()` call inside update/create controller actions to invalidate when data changes:
```php
Cache::forget('categories:all');
```

For the assets list in `BookingController`, caching is less appropriate since asset status changes frequently. Use pagination instead (see P2.3).

**Effort:** 1 hour  
**Verified:** Yes — all query locations confirmed.

---

### P2.6 — Add missing feature tests (~10 gaps)

**Critical gaps (should be done before P2.7):**

| Feature | Route | Current Coverage | Status |
|---|---|---|---|
| Product show/edit/update/destroy | `products.show`, `.edit`, `.update`, `.destroy` | ❌ Not tested | Gap |
| Booking index | `bookings.index` | ❌ Not tested | Gap |
| Booking rejection (update) | `bookings.update` (reject action) | ❌ Not tested | Gap — feature exists in controller |
| Handover index | `handover.index` | ❌ Not tested | Gap |
| Handover verify GET page | `handover.verify` | ❌ Not tested | Gap |
| Stock Movements index | `movements.index` | ❌ Not tested | Gap |
| Requisition store/index/show | `requisitions.store`, `.index`, `.show` | ❌ Not tested | Gap |
| Receiving index | `receiving.index` | ❌ Not tested | Gap |
| Product label | `products.label` | Partially tested | `ProductLabelTest` exists but only covers authorization |
| PDF receipt generation | `handover.receipt` | ❌ Not tested | Gap |

> **Note:** Some test files exist for related functionality but do not cover the index/show endpoints:
> - `BookingAvailabilityTest` covers booking overlap logic, not `index` or `update` rejection.
> - `HandoverVerificationTest` covers store + verify POST, not `index` or verify GET page.
> - `RequisitionIssuanceTest` covers the issue endpoint, not store/index/show.
> - `ReceivingCheckoutTest` covers the store endpoint, not `receiving.index`.
> - `ProductCrudTest` covers `products.index` and `products.store`, not show/edit/update/destroy.

**Effort:** 4–6 hours total  
**Verified:** Yes — all test files reviewed against route list.

---

### P2.7 — Use orphaned factories in tests

**Location:** `database/factories/` — several factories exist but are never directly used

**Risk:** Bloated test suite with unused code; missed opportunities to simplify tests with factories.

**Problem:** Several factories are never directly instantiated in tests, meaning test data is created manually with verbose `Model::create([...])` calls. However, **some factories ARE used**: `ProductStock::factory()` is used in `RequisitionIssuanceTest` and `ReceivingCheckoutTest`; `StockLot::factory()` is used in `RequisitionIssuanceTest`; `User::factory()->assignedPosition()` is used across multiple tests.

**Truly unused factories:**
| Factory | Created For | Never Used |
|---|---|---|
| `BookingFactory` | `Booking` model | ✓ |
| `RequisitionFactory` | `Requisition` model | ✓ |
| `RequisitionLineFactory` | `RequisitionLine` model | ✓ |
| `HandoverLogFactory` | `HandoverLog` model | ✓ |
| `StockMovementFactory` | `StockMovement` model | ✓ |
| `DepartmentFactory` | `Department` model | ✓ |

**Fix:** Replace manual model creation in tests with factory calls where possible. For example:
```php
// Instead of:
$requisition = Requisition::create([
    'requester_id' => $user->id,
    'requester_position_id' => $position->id,
    'status' => 'Submitted',
    // ... 10+ more fields ...
]);

// Use:
$requisition = Requisition::factory()->create([
    'requester_id' => $user->id,
    'requester_position_id' => $position->id,
    'status' => 'Submitted',
]);
```

**Effort:** 1–2 hours  
**Verified:** Yes — grep confirms listed factories are never instantiated in tests.

---

### P2.8 — Add unit tests for `InventoryService`

**Location:** `app/Services/Inventory/InventoryService.php`

**Risk:** Logic errors in core business operations (receiving, issuing) caught only in production or via slow feature tests.

**Problem:** The core business logic (`receiveConsumable`, `receiveAssets`, `issueRequisition`) has no isolated unit tests. It is only tested indirectly through feature tests. Isolated unit tests would catch logic errors faster, run quicker, and be more maintainable.

**Required test cases for `InventoryService`:**

| Method | Test Cases |
|---|---|
| `receiveConsumable` | Creates stock lot with correct values. Upserts product stock (increment on_hand_qty). Creates StockMovement('receive'). Handles expiry dates. Handles null reference_no. Updates existing stock row instead of creating duplicate. |
| `receiveAssets` | Creates Asset records for each tag code. Creates StockMovement per asset. Handles duplicate tag codes (should throw). |
| `issueRequisition` | Throws if status is not 'Approved'. Throws if product is not consumable. Throws if insufficient stock. Allocates lots in FIFO/expiry-first order. Decrements stock lot correctly. Creates StockMovement entries. Updates requisition to 'Issued'. Uses pessimistic locking. Rollbacks on failure. |

**Effort:** 2–3 hours  
**Verified:** Yes — `InventoryService` has no dedicated test file.

---

### P2.9 — Update `.env.example` to production-safe defaults

**Location:** `.env.example`

**Risk:** Production misconfiguration if `.env.example` is copied without understanding the implications.

**Problem:** The example environment file has several settings that could be unsafe if copied directly to production:
- `APP_DEBUG=true` — should be `false` for production
- `APP_KEY=` — empty (doesn't hint that a key must be generated)
- `DB_PASSWORD=` — empty (reasonable but no comment about it)
- `SESSION_ENCRYPT=false` — should be `true`
- `MAIL_MAILER=log` — should have a comment about production alternatives
- `CACHE_STORE=database` — reasonable but no mention of Redis
- `QUEUE_CONNECTION=database` — no mention of ensuring the `jobs` table exists

**Fix:** Add comments explaining each setting and its production recommendation:
```ini
# In production: APP_DEBUG=false
APP_DEBUG=true

# Generate with: php artisan key:generate (REQUIRED for production)
APP_KEY=

# In production: use a strong password and restrict DB user permissions
DB_PASSWORD=

# In production: SESSION_ENCRYPT=true
SESSION_ENCRYPT=false

# In production: switch to smtp, ses, or postmark
MAIL_MAILER=log

# In production: switch to redis if available
CACHE_STORE=database
```

**Effort:** 15 minutes  
**Verified:** Yes — `.env.example` reviewed.

---

### P2.10 — Add base64 size validation for `signature_png`

**Location:** `app/Http/Controllers/Inventory/HandoverController.php:139-141`

**Risk:** Denial of Service (DoS) via memory exhaustion — an attacker could submit a multi-megabyte base64 string as `signature_png`, causing PHP memory limits to be exceeded during validation, storage, or PDF generation.

**Problem:** The `verify()` method validates `signature_png` as `['required', 'string']` with no maximum length or size constraint. Base64 encoding inflates binary data by ~33%, so a 5MB image becomes ~6.7MB of text. This is stored in the `handover_logs.signature_png` column (type `text`, which in PostgreSQL can hold up to 1GB). In addition to database bloat, the DomPDF receipt generation (`HandoverReceiptController`) loads this data into memory when rendering the receipt view.

**Fix:** Add a maximum size validation rule. A reasonable signature PNG should be under 200KB (base64 ~267KB):
```php
$request->validate([
    'signature_png' => ['required', 'string', 'max:300000'], // ~300KB base64 ≈ ~225KB binary
]);
```

Also consider adding a client-side file size check in the Vue signature pad component.

**Effort:** 5 minutes  
**Verified:** Yes — `HandoverController.php:139-141` confirmed. `signature_png` column is `text` type with no length constraint.

---

## P3 — Quality (Improve Maintainability)

These improvements make the codebase cleaner, safer, and easier to maintain.

### P3.1 — Implement PHP 8.4 backed enums for domain status/type fields

**Locations:**
- `app/Models/Product.php` — `type` field ('consumable', 'asset')
- `app/Models/Asset.php` — `status` field ('Available', 'Checked_Out', 'Unserviceable', 'Condemned')
- `app/Models/Booking.php` — `status` field ('Requested', 'Approved', 'Rejected', 'Cancelled')
- `app/Models/Requisition.php` — `status` field ('Draft', 'Submitted', 'Approved', 'Issued', 'Closed', 'Rejected')

**Risk:** Typos in status strings silently fail at runtime (database constraint level) instead of being caught by the IDE or compiler.

**Problem:** These values are currently plain strings scattered across controllers, policies, services, and tests with no centralized type safety. Typing a value as `'Aproved'` (misspelled) would silently pass the type system and only fail at the database constraint level or, worse, cause logic errors in policies.

**Fix:** Create `app/Enums/` directory with backed enums:
```php
<?php
namespace App\Enums;

enum ProductType: string
{
    case Consumable = 'consumable';
    case Asset = 'asset';
}

enum AssetStatus: string
{
    case Available = 'Available';
    case CheckedOut = 'Checked_Out';
    case Unserviceable = 'Unserviceable';
    case Condemned = 'Condemned';
}

enum RequisitionStatus: string
{
    case Draft = 'Draft';
    case Submitted = 'Submitted';
    case Approved = 'Approved';
    case Issued = 'Issued';
    case Closed = 'Closed';
    case Rejected = 'Rejected';
}
```
Then add casts to models:
```php
protected function casts(): array
{
    return [
        'type' => ProductType::class,
        'status' => AssetStatus::class,
    ];
}
```

> **Note:** This project uses PHP 8.4, which fully supports backed enums with `enum` cases as strings.

**Effort:** 2–3 hours  
**Verified:** Yes — all four models confirmed to use string-based status fields with no enum abstraction.

---

### P3.2 — Refactor controller prop transformations to Eloquent API Resources

**Locations:** All controllers in `app/Http/Controllers/Inventory/` and `app/Http/Controllers/Settings/`

**Risk:** Bloated controllers, DRY violations, and untransformable data logic.

**Problem:** Every controller method manually maps Eloquent models to arrays using inline `->map(fn (...) => [...])` closures inside `Inertia::render()` calls. This makes controllers bloated (e.g., `BookingController::index()` has ~40 lines of inline mapping), violates DRY, and makes the transformations impossible to unit test independently.

**Fix:** Create Eloquent API Resource classes:
```
app/Http/Resources/
├── ProductResource.php
├── ProductCollection.php
├── BookingResource.php
├── RequisitionResource.php
├── HandoverLogResource.php
├── UserResource.php
└── AssetResource.php
```

Example:
```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category?->name,
            'origin' => $this->origin?->name,
            'on_hand_qty' => $this->stock?->on_hand_qty,
            'reserved_qty' => $this->stock?->reserved_qty,
            'is_active' => $this->is_active,
        ];
    }
}
```

Then in controllers:
```php
return Inertia::render('inventory/products/Index', [
    'products' => ProductResource::collection($products),
]);
```

**Effort:** 4–6 hours  
**Verified:** Yes — all inventory controllers confirmed to use inline `->map()` closures.

---

### P3.3 — Re-enable stricter TypeScript/ESLint rules

**Locations:**
- `eslint.config.js:41` — `'vue/multi-word-component-names': 'off'`
- `eslint.config.js:42` — `'@typescript-eslint/no-explicit-any': 'off'`

**Risk:** Reduced type safety, accumulation of technical debt, and harder refactors.

**Problem:** Disabling these rules weakens code quality enforcement. `no-explicit-any` in particular bypasses TypeScript's primary value proposition. The `vue/multi-word-component-names` rule prevents HTML element name collisions (e.g., a `<Heading>` component collides with the HTML `<heading>` element in some contexts).

**Fix:**
```js
// Instead of off, use warn for migration period
'vue/multi-word-component-names': 'warn',
'@typescript-eslint/no-explicit-any': 'warn',
```
Then gradually fix violations:
- Rename single-word components (`Heading` → `PageHeading`, `InputError` → `FieldError`)
- Replace `any` types with proper interfaces (e.g., `usePage()` auth props)

> **Note:** `Dashboard.vue` currently uses `(page.props.auth as any)?.roles ?? []` which would trigger this rule once re-enabled.

**Effort:** 1–2 hours for fixes  
**Verified:** Yes — `eslint.config.js` lines 41–42 confirmed.

---

### P3.4 — Add missing model casts

**Locations:** Multiple models

**Risk:** Type coercion bugs, unexpected boolean/string comparisons, and inconsistent API responses.

**Problem:** Several models are missing explicit casts for fields that would benefit from them:

| Model | Field | Current Behavior | Recommended Cast |
|---|---|---|---|
| `Product` | `reorder_threshold` | Stored as integer but not cast | `integer` |
| `Product` | `is_active` | Stored as tinyint but not cast | `boolean` |
| `Product` | `type` | Plain string | `ProductType::class` (see P3.1) |
| `Asset` | `status` | Plain string | `AssetStatus::class` (see P3.1) |

> **Correction from original plan:** `Booking` already has correct `start_at` and `end_at` casts (line 74–77). `Asset` timestamps are automatically handled by Laravel's base `Model` class and do not need explicit casts. `StockLot` already has `received_at` and `expires_at` casts.

**Fix:** Audit each model and add casts where needed:
```php
// Product.php
protected function casts(): array
{
    return [
        'reorder_threshold' => 'integer',
        'is_active' => 'boolean',
        'type' => ProductType::class,      // when P3.1 is implemented
    ];
}
```

**Effort:** 15 minutes  
**Verified:** Yes — `Booking.php` already has correct datetime casts. `Product.php` has no casts at all.

---

### P3.5 — Add database indices for frequently queried columns

**Locations:** Existing migrations

**Risk:** Query performance degradation as data volume grows.

**Problem:** While most foreign keys are indexed automatically by Laravel, some query patterns could benefit from additional indices:
- `stock_movements.movement_type` — used in WHERE filters on audit log
- `stock_movements.performed_by` — used in joins
- `products.type` — used in WHERE filters (product listing)
- `products.is_active` — used in WHERE filters
- `products.name` — already indexed (confirmed in migration), but `sku` is not

**Fix:** Create a new migration adding these indices:
```php
Schema::table('stock_movements', function (Blueprint $table) {
    $table->index('movement_type');
    $table->index('performed_by');
});
Schema::table('products', function (Blueprint $table) {
    $table->index('type');
    $table->index(['is_active', 'type']);
    $table->index('sku');   // frequently searched in product lookups
});
```

**Effort:** 15 minutes  
**Verified:** Yes — `products` migration confirms `name` is indexed but `sku` is not. Foreign keys are indexed by Laravel convention.

---

### P3.6 — Optimize Chart.js import

**Location:** `resources/js/pages/Dashboard.vue:3`

**Risk:** Unnecessary JavaScript bundle bloat (~60KB+ gzipped for unused chart types).

**Problem:** The dashboard imports `chart.js/auto` which registers ALL chart types (bar, line, pie, doughnut, radar, polar area, bubble, scatter) at ~63KB gzipped. Only a single bar chart with 2 bars is used.

**Fix:** Use selective import:
```ts
import { Chart, BarController, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);
```

Or, as an alternative, replace Chart.js with a lightweight CSS-based bar chart or an SVG chart since only 2 data bars are shown.

**Effort:** 30 minutes  
**Verified:** Yes — `Dashboard.vue` line 3 imports `chart.js/auto`.

---

### P3.7 — Add proper HTTP caching headers to Inertia responses

**Location:** Middleware or controllers

**Risk:** Unnecessary server load from repeated full-page requests for static-ish data.

**Problem:** All Inertia page responses are served without cache-control headers. Pages like product listings and settings could benefit from short-lived browser caching. Inertia's default behavior is to serve with `no-cache`, which is safe but suboptimal for listing pages.

**Fix:** In `HandleInertiaRequests.php`, add cache headers for non-sensitive, non-auth pages:
```php
public function share(Request $request): array
{
    // For GET requests to listing pages
    if ($request->isMethod('GET') && !$request->is('settings/*')) {
        $request->headers->set('Cache-Control', 'private, max-age=30');
    }
    // ...existing shared data...
}
```

> **Caution:** Do not cache pages that display sensitive inventory data or user-specific actions without ensuring `private` and very short TTL.

**Effort:** 15 minutes  
**Verified:** Yes — `HandleInertiaRequests.php` confirmed; no cache headers currently set.

---

### P3.8 — Consolidate route middleware declarations

**Location:** `routes/web.php`

**Risk:** Copy-paste errors in authorization, accidental exposure of routes to wrong roles.

**Problem:** The role middleware is repeated on every route individually. With ~25 inventory routes, this creates a lot of repetition and a risk of copy-paste errors. For example, changing the role requirements for product creation requires editing 4 separate route declarations.

**Fix:** Use `Route::group()` with shared middleware:
```php
Route::prefix('inventory')->name('inventory.')->group(function () {
    // Routes accessible by Admin|Supply Head|Property Custodian
    Route::middleware('role:Admin|Supply Head|Property Custodian')->group(function () {
        Route::get('bookings', ...);
        Route::get('requisitions', ...);
        Route::get('products', ...);
    });

    // Routes accessible by Admin|Supply Head only
    Route::middleware('role:Admin|Supply Head')->group(function () {
        Route::post('products', ...);
        Route::put('products/{product}', ...);
        Route::post('receiving', ...);
    });

    // Routes accessible by Admin only
    Route::middleware('role:Admin')->group(function () {
        Route::delete('products/{product}', ...);
        Route::get('movements', ...);
    });
});
```

**Effort:** 30 minutes  
**Verified:** Yes — `routes/web.php` lines 27–113 confirmed to repeat middleware per route.

---

### P3.9 — Document Wayfinder build requirement in deployment

**Location:** `.gitignore` lines 9–11 + deployment documentation

**Risk:** Deployment failures because generated route/action files are missing.

**Problem:** The `.gitignore` excludes `/resources/js/actions`, `/resources/js/routes`, and `/resources/js/wayfinder` — these are auto-generated by the `@laravel/vite-plugin-wayfinder` Vite plugin during `npm run build`. If the production build pipeline skips `npm run build`, or if a developer clones the repo and runs `npm run dev` without first building, the frontend will fail to compile because the imported `@/actions` and `@/routes` files don't exist.

**Fix:** Add to README / deployment checklist:
```bash
# After composer install, always build frontend assets
npm ci
npm run build
```

Also verify `vite.config.ts` includes the Wayfinder plugin (already confirmed present at line 24).

**Effort:** 5 minutes  
**Verified:** Yes — `.gitignore` lines 9–11 and `vite.config.ts:24` confirmed.

---

### P3.10 — Set application timezone to `Asia/Manila`

**Location:** `config/app.php:68`

**Risk:** All timestamps stored in UTC while users expect Philippine time; scheduling confusion for bookings and requisitions.

**Problem:** `config/app.php` sets `'timezone' => 'UTC'`. For a Philippine university system, this means all `created_at`, `updated_at`, `start_at`, `end_at`, `initiated_at`, and `verified_at` timestamps are stored in UTC. While Laravel handles timezone conversion for display if configured, the default expectation for a local system is local time. This can cause confusion in booking schedules, handover timestamps, and audit trails.

**Fix:** Update `config/app.php`:
```php
'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
```

And add to `.env` and `.env.example`:
```ini
APP_TIMEZONE=Asia/Manila
```

> **Note:** Laravel stores timestamps in the database using the configured timezone. If the application has been running with `UTC`, changing the timezone will **not** retroactively convert existing data. Plan this change before any production data is written, or implement a migration to offset existing timestamps.

**Effort:** 2 minutes  
**Verified:** Yes — `config/app.php:68` confirmed as `'UTC'`.

---

## Future — Enhancements (Next Major Iteration)

These are valuable additions that significantly expand the system's capability.

### Future.1 — Add browser-based E2E testing

**Rationale:** The current tests are all backend feature/integration tests. Critical user journeys (login → create product → receive stock → create requisition → approve → issue → verify stock movement) through the browser are never tested.

**Recommended tools:** Laravel Dusk or Playwright (Playwright is preferred for modern SPA testing with Inertia)

**Key journeys to cover:**
1. User registration → email verification → login → dashboard view
2. Product CRUD lifecycle (create → view → edit → delete)
3. Stock receiving flow (receive consumable + asset)
4. Requisition lifecycle (submit → approve → issue → verify stock decrement)
5. Asset handover flow (initiate → verify → download receipt)
6. Asset booking flow (request → approve → verify calendar display)

**Effort:** 2–3 days

---

### Future.2 — Add export functionality (CSV/Excel/PDF reports)

**Rationale:** University property managers need to generate reports for audits. The current system has no export capabilities except for individual handover receipts.

**Recommended exports:**
1. Product inventory listing (CSV/PDF)
2. Stock movement audit log (CSV/PDF)
3. Unserviceable/condemned asset report (PDF)
4. Booking schedule report (PDF)
5. Requisition history report (PDF)

**Effort:** 1–2 days

---

### Future.3 — Implement activity log / audit trail viewer with search and filtering

**Rationale:** The `StockMovement` table already contains a comprehensive audit trail, but the current UI (`inventory/movements/Index.vue`) only supports basic search. A robust audit viewer with date range filters, user filters, and action type filters would greatly improve accountability tracking.

**Effort:** 1–2 days

---

### Future.4 — Add email notifications for key workflow events

**Rationale:** Currently, only the handover verification has an email notification. Key events should trigger notifications:

| Event | Recipient | Purpose |
|---|---|---|
| Requisition submitted | Supply Head | Action required |
| Requisition approved | Requester | Status update |
| Requisition issued | Requester | Ready for pickup |
| Requisition rejected | Requester | Status update |
| Booking requested | Property Custodian | Action required |
| Booking approved/rejected | Requester | Status update |
| Product stock below reorder threshold | Supply Head | Inventory alert |

**Effort:** 2–3 days

---

### Future.5 — Implement soft deletes with trash / restore UI

**Rationale:** The codebase contains no soft-delete functionality. `Product::delete()` performs a **hard delete** (confirmed: no `SoftDeletes` trait is used in any model). For a university property accountability system, accidental deletion of products, bookings, or requisitions is a significant data-loss risk. Implementing soft deletes with a "Trash" UI and full audit trail (who deleted, when, why) would align with accountability requirements.

**Models to consider for soft deletes:**
- `Product` — highest priority (master data)
- `Booking` — medium priority (scheduling data)
- `Requisition` — medium priority (transactional data)
- `HandoverLog` — lower priority (already has verified_at audit)

**Effort:** 2–3 days (includes migration, trait addition, policy updates, and Trash UI)

> **Correction from original plan:** The original document incorrectly stated that `Product` already uses soft deletes. Verified: `SoftDeletes` trait is **not** present in any model. `ProductController::destroy` catches `QueryException` for hard-delete foreign key violations.

---

### Future.6 — Add batch operations for stock receiving

**Rationale:** The current `ReceivingController` accepts stock one SKU at a time. For bulk receiving events (e.g., a delivery of 50 items), this is inefficient. A batch receiving interface with a file upload (CSV) or multi-line form would greatly improve UX.

**Effort:** 1 day

---

### Future.7 — Implement webhook or API endpoints for integration

**Rationale:** A REST API (even a limited one) would allow integration with PUP's existing systems (HRIS for employee data, Finance for procurement, etc.). Consider using Laravel's API routes with Sanctum token auth.

**Suggested endpoints:**
- `GET /api/products` — inventory listing
- `GET /api/assets` — asset registry
- `POST /api/requisitions` — submit requisition (for external systems)
- `GET /api/stock-movements` — audit trail

**Effort:** 2–3 days

---

### Future.8 — Add dashboard widgets with date-range filtering

**Rationale:** The current dashboard shows a static snapshot. Adding date-range pickers, trend charts (receiving/issuing over time), and exportable widgets would make it a true operations dashboard.

**Effort:** 1–2 days

---

## Summary: Priority Matrix

| Tier | Count | Items | Total Est. Effort |
|---|---|---|---|
| **P0 — Critical** | 5 | `::int` cast, `reserved_qty`, `SESSION_ENCRYPT`, `$appearance` cookie, `authorizeResource` | ~30 min |
| **P1 — Required** | 14 | README, dead code, `.gitattributes`, logs isolation, `.env.testing`, mailer, queue, cron, storage link, admin test, app name, `composer.json`, HTTPS/timezone, production optimization | ~4–6 hours |
| **P2 — Important** | 10 | `inspire`, test dirs, pagination, rejection flow, cache, test gaps, factories, unit tests, `.env.example`, `signature_png` validation | ~11–18 hours |
| **P3 — Quality** | 10 | PHP enums, API Resources, ESLint, model casts, DB indices, Chart.js, cache headers, routes, Wayfinder build, timezone cleanup | ~9–15 hours |
| **Future** | 8 | E2E tests, exports, audit viewer, notifications, soft deletes, batch receiving, API, dashboard widgets | ~10–16 days |

**Total items:** 47 improvements across 5 tiers  
**Total estimated effort for production readiness (P0 + P1):** ~5–7 hours  
**Total estimated effort for full maturity (P0–P3):** ~24–38 hours

---

## Quick-Win Checklist (Do These First)

If you only have 2 hours, do these in order:

- [ ] **P0.1** — Fix `::int` cast (2 min)
- [ ] **P0.3** — Set `SESSION_ENCRYPT=true` (2 min)
- [ ] **P0.4** — Secure `$appearance` cookie rendering (5 min)
- [ ] **P1.2** — Delete 4 dead files (5 min)
- [ ] **P1.4** — Harden `storage/logs` git isolation (2 min)
- [ ] **P1.9** — Create storage symlink (1 min)
- [ ] **P1.11** — Set `APP_NAME` to "PUP PRISM" (1 min)
- [ ] **P1.12** — Update `composer.json` name and PHP version (2 min)
- [ ] **P1.13** — Force HTTPS and set timezone (3 min)
- [ ] **P1.10** — Add admin dashboard test (15 min)
- [ ] **P2.1** — Remove `inspire` command (1 min)
- [ ] **P2.2** — Consolidate test directories (10 min)
- [ ] **P2.3** — Replace `limit(200)` with pagination (30 min)
- [ ] **P2.5** — Cache reference data (30 min)
- [ ] **P2.6** — Add 2–3 critical missing tests (30 min)

**Total for quick wins:** ~2.5 hours — addresses the most impactful issues first.

---

## Changelog

| Date | Changes |
|---|---|
| 2026-06-03 | **Verified** all items against actual codebase. Added `Risk` and `Verified` fields to every item. Corrected inaccuracies: P1.4 (logs not tracked by git), P3.1 (PHP 8.4 not 8.1), P3.4 (Booking already has casts; Product missing casts), Future.5 (no soft deletes exist), P1.11 (overly long APP_NAME warning). Added P0.5 (`authorizeResource` redundancy). Enhanced P2.3 (unlimited asset loading), P2.4 (booking rejection exists), P2.6 (nuanced test gap table), P2.7 (clarified used vs unused factories). Updated totals from 40 to 41 items. |
| 2026-06-03 (2nd pass) | **Re-evaluated** against additional files (`AppServiceProvider`, `config/fortify.php`, `vite.config.ts`, `composer.json`, `HandoverController`). Added 6 new items: P1.12 (`composer.json` identity), P1.13 (HTTPS + timezone), P1.14 (production optimization), P2.10 (`signature_png` DoS), P3.9 (Wayfinder build requirement), P3.10 (timezone). Updated Summary counts and Quick-Win checklist. Total items: 47. |
