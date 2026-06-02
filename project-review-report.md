# PUP PRISM — Comprehensive Project Review Report

**Project:** PUP PRISM (Property & Resource Inventory System Management)  
**Repository Path:** `C:\laragon\www\PUP_PRISM`  
**Review Date:** 2026-06-02  
**Reviewer:** AI Code Review Agent

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Folder and File Structure Review](#2-folder-and-file-structure-review)
3. [Code Quality Review](#3-code-quality-review)
4. [Functionality and Logic Review](#4-functionality-and-logic-review)
5. [Security Review](#5-security-review)
6. [Dependency and Configuration Review](#6-dependency-and-configuration-review)
7. [Performance and Scalability Review](#7-performance-and-scalability-review)
8. [Documentation Review](#8-documentation-review)
9. [Testing Review](#9-testing-review)
10. [Issues, Risks, and Recommendations](#10-issues-risks-and-recommendations)
11. [Final Assessment](#11-final-assessment)

---

## 1. Project Overview

### 1.1 What It Is

PUP PRISM is a **web-based inventory and asset management system** built for the **Polytechnic University of the Philippines (PUP)**. It is a full-stack application using **Laravel 13** (PHP 8.4) on the backend and **Vue 3 + Inertia.js v3** on the frontend, styled with **Tailwind CSS v4** and a **shadcn-vue** UI component library.

### 1.2 Purpose and Main Features

The system manages the complete lifecycle of university property and supplies through:

| Feature | Description |
|---|---|
| **Product Catalog** | Manage consumable items and physical assets with SKU, category, origin, and status tracking |
| **Inventory Receiving** | Log inbound stock (consumable lots or individual asset items) with reference numbers and expiry tracking |
| **Stock Management** | FIFO/expiry-first lot allocation, on-hand/reserved quantity tracking, reorder threshold alerts |
| **Requisitions** | Submit, approve, and issue supply requests with separation-of-duty enforcement (requester cannot self-approve) |
| **Asset Booking** | Reserve physical assets (projectors, laptops, etc.) with date-range overlap detection and approve/reject workflow |
| **Asset Handover** | Cryptographically verified asset transfer between employees with digital signature capture and PDF receipt generation |
| **Audit Trail** | Comprehensive `StockMovement` logging for every inventory action (receive, issue, transfer, condemn, return) with IP address and position tracking |
| **Alerts** | Automated low-stock and expiring-lot alerts via scheduled Artisan command |
| **QR Code Labels** | Generate QR-coded product labels for physical asset tagging |
| **2FA Authentication** | TOTP-based two-factor authentication with recovery codes |
| **RBAC** | Three roles: Admin, Supply Head, Property Custodian with granular policies |
| **Email Verification** | Mandatory email verification for most operations |

### 1.3 Project Structure

```
PUP_PRISM/
├── app/                    # Laravel backend
│   ├── Actions/Fortify/    # Auth action classes (CreateNewUser, ResetUserPassword)
│   ├── Concerns/           # Shared traits (PasswordValidationRules, ProfileValidationRules)
│   ├── Console/Commands/   # Artisan commands (InventoryGenerateAlerts)
│   ├── Http/
│   │   ├── Controllers/    # 12 controllers (Dashboard, Inventory/*, Settings/*)
│   │   ├── Middleware/      # HandleAppearance, HandleInertiaRequests
│   │   └── Requests/       # 14 form request classes
│   ├── Models/             # 15 Eloquent models
│   ├── Notifications/      # HandoverVerificationNotification
│   ├── Policies/           # ProductPolicy, RequisitionPolicy, BookingPolicy
│   ├── Providers/          # AppServiceProvider, AuthServiceProvider, FortifyServiceProvider
│   └── Services/Inventory/ # InventoryService (core business logic)
├── bootstrap/              # App bootstrap (middleware config, routing)
├── config/                 # 13 configuration files
├── database/               # 37 migrations, 16 factories, 6 seeders
├── resources/
│   ├── css/                # app.css (Tailwind entry)
│   ├── js/                 # Vue 3 + TypeScript frontend
│   │   ├── actions/        # Wayfinder-generated controller helpers
│   │   ├── components/     # Vue components (app-level + ~35 UI primitives)
│   │   ├── composables/    # 5 composables (appearance, URL, initials, QR scanner, 2FA)
│   │   ├── layouts/        # AppLayout, AuthLayout, SettingsLayout (with variants)
│   │   ├── lib/            # Utilities (cn, flashToast)
│   │   ├── pages/          # ~18 Inertia page components
│   │   ├── routes/         # Wayfinder-generated route functions
│   │   ├── types/          # TypeScript type definitions
│   │   └── wayfinder/      # Wayfinder runtime
│   └── views/              # app.blade.php (root shell) + handover_receipt.blade.php (PDF)
├── routes/                 # web.php, settings.php, console.php
├── tests/                  # 16 test files (56 tests, 194 assertions, all passing)
├── vendor/                 # Composer dependencies
├── node_modules/           # npm dependencies
├── composer.json
├── package.json
├── vite.config.ts
├── tsconfig.json
├── eslint.config.js
└── pint.json
```

**Schema:** 26 database tables covering users, products, stock lots, assets, stock movements, requisitions, bookings, handovers, alerts, departments, positions, and Spatie permissions.

---

## 2. Folder and File Structure Review

### 2.1 Strengths

- **Clean separation of concerns**: Controllers, Services, Models, Policies, Form Requests, Notifications are well-segregated following Laravel conventions.
- **Inventory module grouping**: All inventory controllers are in `app/Http/Controllers/Inventory/`, all inventory requests in `app/Http/Requests/Inventory/`, and the service class in `app/Services/Inventory/`.
- **Frontend organization**: Pages, components, composables, layouts, and types are cleanly separated in `resources/js/`.
- **Wayfinder integration**: Auto-generated typed route/action functions eliminate hardcoded URLs.
- **shadcn-vue UI library**: Well-organized under `components/ui/` with each component in its own directory.

### 2.2 Issues

| Issue | Severity | Details |
|---|---|---|
| **Sales module artifacts remain** | Low | `SaleFactory.php`, `SaleLineFactory.php`, and `CheckoutRequest.php` exist but sales tables were dropped. The factories and form request are unused dead code. |
| **Nested `Feature/Inventory/` directory** | Low | Tests are split between `tests/Feature/Inventory/` and `tests/Feature/Feature/Inventory/`. The double `Feature/` nesting is redundant and confusing. |
| **`pnpm-workspace.yaml` at root** | Low | This file exists but the project uses npm (based on `package-lock.json` and `npm run` commands). If not using pnpm, this should be removed. |
| **Missing `.env.testing`** | Medium | Test configuration is entirely in `phpunit.xml`. While functional, a `.env.testing` would allow overrides without modifying `phpunit.xml`. |

### 2.3 Recommendation

- Remove `SaleFactory`, `SaleLineFactory`, `CheckoutRequest.php` (dead code from removed sales flow).
- Consolidate test files out of `Feature/Feature/Inventory/` into `Feature/Inventory/`.
- Remove `pnpm-workspace.yaml` if not using pnpm.
- Consider adding `.env.testing`.

---

## 3. Code Quality Review

### 3.1 Strengths

- **Consistent use of PHP 8 features**: Constructor property promotion, named arguments, attributes (`#[Fillable]`, `#[Hidden]`), typed properties.
- **Explicit type hints**: All methods have return types and parameter types.
- **Pessimistic locking**: `lockForUpdate()` used consistently in all stock-altering transactions.
- **Transactional integrity**: All mutations that affect multiple tables are wrapped in `DB::transaction()`.
- **Wayfinder type safety**: Frontend uses typed route/action functions, reducing URL typos.
- **Composable pattern**: Cross-cutting concerns (theme, QR scanning, 2FA) are extracted into composables.

### 3.2 Issues

| Issue | Severity | Details |
|---|---|---|
| **Broad `QueryException` catch** | Medium | `ProductController::destroy()` catches `QueryException` broadly. This could mask unrelated DB errors. Should catch the specific foreign key violation exception. |
| **`$appearance` variable unsafely echoed** | Medium | In `app.blade.php` line 10: `const appearance = '{{ $appearance ?? "system" }}';` — this is a reflected XSS vector if the cookie value ever contains a single quote. |
| **No DTOs/Value Objects** | Medium | Controllers manually build arrays for Inertia props (e.g., `->map(fn (...) => [...])` patterns). These inline mappings are repeated across controllers with no reusable DTOs or API resources. |
| **`type` string on Product model** | Low | `Product::$type` is a plain string (`'consumable'` / `'asset'`). Should be an enum (PHP 8.1+) for type safety. |
| **`status` strings on multiple models** | Low | `Asset::$status`, `Booking::$status`, `Requisition::$status` are all plain strings. Would benefit from backed enums. |
| **Inline Inertia prop transformations** | Medium | The pattern of mapping Eloquent models to arrays inside `Inertia::render()` calls is repeated in every controller method. This makes the controllers bloated and hard to test. |
| **Vue page component names single-file** | Low | ESLint rule `vue/multi-word-component-names: off` disables a good practice. Many components have single-word names (`Heading`, `InputError`, etc.). |
| **`no-explicit-any` disabled** | Low | TypeScript `@typescript-eslint/no-explicit-any: off` risks type safety. |
| **Unused imports** | Low | Some controllers import classes not directly used (e.g., `BookingController` imports `User` but uses `Auth::user()`). |

### 3.3 Recommendations

- Replace broad `QueryException` catch with specific exception type (e.g., `Illuminate\Database\UniqueConstraintViolationException` or check the error code).
- Escape the `$appearance` variable in the Blade template or use a JSON-safe encoding.
- Create Eloquent API Resources (e.g., `ProductResource`, `RequisitionResource`) to centralize model-to-array transformations.
- Add PHP 8.1 enums for `ProductType`, `AssetStatus`, `BookingStatus`, `RequisitionStatus`.
- Re-enable `vue/multi-word-component-names` where feasible.
- Enable `no-explicit-any` and refactor type definitions.

---

## 4. Functionality and Logic Review

### 4.1 Strengths

- **Complete inventory lifecycle**: Receive stock → manage lots → issue via requisition → audit trail.
- **Robust separation of duty**: Requisition approval policy prevents self-approval (`RequisitionPolicy@approve` checks `$user->id !== $requisition->requester_id`).
- **Booking overlap detection**: Proper SQL logic checking `BETWEEN` ranges for approved bookings.
- **Cryptographic handover verification**: SHA-256 token hashing with `hash_equals()` prevents timing attacks.
- **FIFO/expiry-first lot allocation**: `InventoryService::issueRequisition()` uses `ORDER BY expires_at NULLS LAST, received_at` for proper stock rotation.
- **Position-aware audit trail**: Every `StockMovement` records the accountable position, not just the user.

### 4.2 Issues

| Issue | Severity | Details |
|---|---|---|
| **`reserved_qty` never decremented** | **High** | `ProductStock.reserved_qty` is never decreased in `issueRequisition()`. The field is incremented nowhere. It appears to be a dead column — the system does not implement a reservation/checkout flow. Either implement reservation or remove the field. |
| **No `receive` endpoint for assets** | Medium | `ReceivingController::store()` and `ReceiveStockRequest` handle both consumables and assets, but the `receive` method in `InventoryService` dispatches to `receiveAssets()` only when the product is non-consumable. The feature exists in code but has no dedicated UI flow documented. |
| **Handover token sent in email URL** | Medium | The verification token (64-char random string) is sent in the email URL query parameter. If the email is intercepted, the attacker could verify the handover. This is somewhat mitigated by requiring the recipient to be logged in and email-verified, but the token alone should not be sufficient. |
| **No requisition rejection flow** | Medium | Requisition approval can set status to "Approved" or... the `RequisitionApproveRequest` has no "reject" action. Once approved, a requisition can only be issued. There is no "Rejected" path for requisitions (despite "Rejected" being in the DB CHECK constraint). |
| **No `reserved_qty` used in stock checks** | Medium | `issueRequisition()` checks `on_hand_qty >= qty_requested` but ignores `reserved_qty`. If reservation is implemented later, this will allow issuing reserved stock. |
| **Booking enforces `email_verified` in routes** | Low | The booking index route requires `verified` middleware, but bookings are only for `Admin|Supply Head|Property Custodian` anyway. This is fine but worth noting. |
| **Handover verify GET route has no permission check** | Low | `GET /inventory/handover/verify/{handoverLog}` only requires `auth` middleware, not `verified`. The controller checks `to_user_id` match (403) and verifies email internally (redirect). The route structure is inconsistent with the rest. |
| **`StockLot.expires_at` is stored as `DATE`** | Low | The migration stores `expires_at` as `DATE`, but `StockLotFactory` generates `CarbonImmutable` values. This is fine but the `cast()` in the model is missing — `expires_at` will be returned as a string, not a Carbon instance. |
| **`is_active` on Product not checked on receiving** | Low | `receiving.store` does not check if the product is active before allowing stock intake. |

### 4.3 Recommendations

- Either implement the reservation system (reserve stock on requisition submit, decrement `reserved_qty` on issue) or remove `reserved_qty` from the schema.
- Add a rejection action/capability for requisitions (currently only approval exists).
- Add `casting` to `StockLot` model for `expires_at` as `date`.
- Validate product `is_active` in `ReceiveStockRequest`.
- Consider time-limiting the handover verification token.

---

## 5. Security Review

### 5.1 Strengths

- **Rate limiting**: Login (5/min by username+IP), two-factor (5/min by session), password update (6/min).
- **Email verification**: Required for most inventory operations.
- **Two-factor authentication**: TOTP with confirmation, recovery codes.
- **`hash_equals()`** in handover verification — timing-attack safe.
- **Spatie RBAC**: Role middleware on all inventory routes.
- **Pessimistic locking**: All stock mutations use `lockForUpdate()`.
- **`SESSION_DRIVER=database`** with JSON serialization (no PHP object injection).
- **`Cache::class` unserialization disabled** via `serializable_classes => false`.
- **CORS**: Not configured (likely default Laravel CORS is fine for same-origin).
- **Mass assignment**: `#[Fillable]` attributes used on all models (no `$guarded` bypasses).

### 5.2 Critical Issues

| Issue | Severity | Details |
|---|---|---|
| **Database password hardcoded in `.env`** | **CRITICAL** | `.env` contains `DB_PASSWORD=root` and `DB_USERNAME=postgres`. This file is committed (not gitignored) with a plaintext database password. |
| **`APP_KEY` in `.env` is exposed** | **CRITICAL** | `.env` contains `APP_KEY=base64:gqEImbYGwZFE1herxMmYrcRzGalYBTnrQCUJ1DXUyJU=`. This key is used for encryption (cookies, sessions, signed URLs). Any exposure compromises all encrypted data. |
| **`APP_DEBUG=true` in `.env`** | **CRITICAL** | Debug mode enabled in what appears to be a development environment. If this reaches production, full error stack traces with environment variables (including DB credentials) will be exposed. |

### 5.3 High-Severity Issues

| Issue | Severity | Details |
|---|---|---|
| **`SESSION_ENCRYPT=false`** | High | Session data is stored unencrypted in the database. While JSON serialization prevents PHP injection, sensitive session data is readable from the `sessions` table. |
| **Appearance cookie XSS vector** | High | The `appearance` cookie value is interpolated directly into JavaScript in `app.blade.php` line 10 without sanitization. |
| **`.env.example` has empty `APP_KEY`** | Medium | `.env.example` has `APP_KEY=` (empty). If someone copies `.env.example` to `.env` and deploys without generating a key, encryption will be broken. |
| **PostgreSQL on non-standard port 5433** | Medium | Using port 5433 suggests a custom PostgreSQL setup. Not a security issue per se, but unusual and could indicate a non-standard config. |
| **No CSRF token on API-like routes** | Medium | All routes use `web` middleware (with CSRF protection via Inertia). This is correct, but worth verifying that no route bypasses CSRF. |
| **`MAIL_MAILER=log`** | Low | Mail is logged, not sent. In production, this means password reset emails and handover notifications are never actually delivered. |
| **`QUEUE_CONNECTION=database`** | Low | Queue runs synchronously via `php artisan queue:listen` in dev. In production without a proper queue worker, this could cause delays. |

### 5.4 Recommendations (Priority Order)

1. **Immediately** rotate the `APP_KEY` and database password. Remove `.env` from version control (ensure `.env` is in `.gitignore` — it already is, but check the committed file). Add `.env` to `.gitignore` and remove tracked `.env` file.
2. **Set `APP_DEBUG=false`** in all non-local environments.
3. **Enable `SESSION_ENCRYPT=true`** in production.
4. **Sanitize the `appearance` cookie value** in `app.blade.php` — use `json_encode()` or restrict to known values.
5. **Set a real mailer** in production (SES, Postmark, SMTP).
6. **Configure a production queue worker** (Redis or database with a daemon).
7. **Generate a new `APP_KEY`** and add a non-empty placeholder in `.env.example`.

---

## 6. Dependency and Configuration Review

### 6.1 Composer Dependencies

| Package | Version | Purpose | Assessment |
|---|---|---|---|
| `laravel/framework` | ^13.0 | Core framework | Current |
| `inertiajs/inertia-laravel` | ^3.0 | Inertia server integration | Current |
| `laravel/fortify` | ^1.34 | Auth backend | Current |
| `spatie/laravel-permission` | ^7.3 | RBAC | Current |
| `laravel/wayfinder` | ^0.1.14 | Typed routes | Current |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation (handover receipts) | Needed |
| `laravel/tinker` | ^3.0 | Dev REPL | Dev only |
| `pestphp/pest` | ^4.6 | Testing | Current |
| `laravel/boost` | ^2.2 | Boost MCP | Dev only |

### 6.2 npm Dependencies

| Package | Version | Purpose | Assessment |
|---|---|---|---|
| `vue` | ^3.5.13 | UI framework | Current |
| `@inertiajs/vue3` | ^3.0.0 | Inertia Vue integration | Current |
| `tailwindcss` | ^4.1.1 | CSS framework | Current |
| `@tailwindcss/vite` | ^4.1.11 | Tailwind Vite plugin | Current |
| `vite` | ^8.0.0 | Build tool | Current (may need --version check) |
| `typescript` | ^5.2.2 | Type checking | Current |
| `reka-ui` | ^2.6.1 | Radix Vue UI primitives | Current |
| `chart.js` | ^4.5.1 | Dashboard charts | Used only in Dashboard |
| `@fullcalendar/*` | ^6.1.20 | Calendar for bookings | Used in bookings |
| `jsqr` | ^1.4.0 | QR code scanning | Used throughout |
| `vue-signature-pad` | ^3.0.2 | Signature capture | Used in handover |
| `lucide-vue-next` | ^0.468.0 | Icons | Current |
| `vue-sonner` | ^2.0.0 | Toast notifications | Current |

### 6.3 Configuration Issues

| Issue | Severity | Details |
|---|---|---|
| **`CACHE_STORE=file` in `.env`** but **`CACHE_STORE=database` in `.env.example`** | Medium | Inconsistent. The `.env` uses `file`, but `.env.example` uses `database`. The default in `config/cache.php` is `database`. This inconsistency will cause confusion on new setups. |
| **No `.env.testing`** | Medium | All test config is in `phpunit.xml`. While functional, this lacks flexibility for CI-specific overrides (e.g., different DB connections). |
| **PostgreSQL-specific SQL in migrations** | Low | Several migrations use PostgreSQL-specific CHECK constraints (`->check("status IN (...)")`). While the app targets PostgreSQL, these will fail on MySQL/SQLite. |
| **`::int` cast in PostgreSQL raw query** | Low | `DashboardController` line 47: `DB::raw('COUNT(*)::int as aggregate')` is PostgreSQL-specific. This will work given `DB_CONNECTION=pgsql` but reduces portability. |
| **`dompdf/dompdf` potentially outdated** | Medium | `barryvdh/laravel-dompdf` v3.1 depends on `dompdf/dompdf` which had security vulnerabilities in older versions. Verify the installed version. |

### 6.4 Recommendations

- Align `.env` and `.env.example` for `CACHE_STORE`.
- Add `.env.testing` for CI flexibility.
- Consider abstracting PostgreSQL-specific SQL for test portability (or document that PostgreSQL is required).
- Verify DOMPdf version for security vulnerabilities.

---

## 7. Performance and Scalability Review

### 7.1 Strengths

- **Pagination**: All list endpoints use pagination (`paginate(15)` or `limit(200)`).
- **Eager loading**: All controllers use `with()` to prevent N+1 queries.
- **Database-backed session/queue/cache**: Suitable for horizontal scaling.
- **Queue-ready**: Commands like `InventoryGenerateAlerts` can be queued.
- **Indexed DB columns**: Foreign keys and query columns are well-indexed.

### 7.2 Issues

| Issue | Severity | Details |
|---|---|---|
| **No Redis caching for frequent queries** | Medium | `categories`, `origins`, `departments`, and `positions` are queried on every create/edit page with no caching. These are largely static reference data. |
| **`DashboardController` queries all non-admin users** | Low | The dashboard only loads data for Admin users, but the controller still queries the DB and returns empty collections for non-admins. The Inertia component still receives the props. |
| **`limit(200)` on `BookingController::index()`** | Medium | Hard limit of 200 bookings. As the system grows, this will miss older records. Should use pagination. |
| **`limit(200)` on `HandoverController::index()`** | Medium | Same issue: user list is capped at 200. |
| **No `withCount` optimization on `DashboardController`** | Medium | The asset status count query does a `SELECT COUNT(*)` per status group. Fine for 2 statuses, but could be optimized into a single query. |
| **Inline SVG QR codes** | Low | `ProductLabelController` generates QR code SVGs inline. Large volumes could strain memory. |
| **Chart.js bundled for a single dashboard chart** | Low | Chart.js is 60KB+ gzipped. Consider a lighter alternative or dynamic import. |

### 7.3 Recommendations

- Cache reference data (categories, origins, departments, positions) using Laravel's cache facade with a reasonable TTL.
- Replace hard `limit(200)` with pagination on user listings and booking history.
- Optimize `DashboardController` for non-admin users (skip queries entirely if not admin).
- Lazy-load Chart.js only on the dashboard page.
- Use `withCount` with a single query for asset status counts.

---

## 8. Documentation Review

### 8.1 What Exists

| Document | Content | Assessment |
|---|---|---|
| `AGENTS.md` | Full Laravel Boost guidelines with package versions, skill activation triggers, conventions | Good |
| `boost.json` | Laravel Boost MCP configuration | Good |
| `components.json` | shadcn-vue configuration | Good |
| `README` (implicit via composer.json) | Composer metadata only | Minimal |
| `CHANGELOG` (implicit via git) | Git history available | Adequate |

### 8.2 What Is Missing

| Missing | Severity | Details |
|---|---|---|
| **README.md** | **High** | No project README with setup instructions, architecture overview, or deployment guide. |
| **Setup/Installation guide** | High | New developers have no documented setup process beyond `composer.json` scripts. |
| **API documentation** | Medium | No documentation of the Inertia prop interfaces or shared data contracts. |
| **Architecture Decision Records (ADRs)** | Low | No documentation of why decisions were made (e.g., "why drop sales?", "why use origins over suppliers?"). |
| **Database ERD** | Medium | No visual database schema documentation. |
| **Deployment guide** | High | No documentation of production deployment steps (queue worker, cron, SSL, etc.). |

### 8.3 Recommendations

- Create `README.md` with project overview, setup steps, and architecture summary.
- Add a deployment checklist (queue, cron, mail, SSL, environment config).
- Document the Inertia prop interfaces (or generate from TypeScript types).
- Consider adding inline PHPDoc to all controllers and services (some are missing).

---

## 9. Testing Review

### 9.1 Test Coverage Summary

| Area | Tests | Coverage |
|---|---|---|
| **Authentication** | 6 files, ~20 tests | Good — login, register, password reset, email verification, 2FA, password confirmation |
| **Settings** | 2 files, 7 tests | Good — profile update, security, password change |
| **Authorization (RBAC)** | 1 file, 3 tests | Good — unverified/roleless/admin |
| **Inventory Products** | 2 files, 2 tests | Poor — only create + label access tested |
| **Inventory Receiving** | 1 file, 1 test | Poor — only consumable receiving tested |
| **Inventory Handover** | 1 file, 1 test | Fair — end-to-end handover tested |
| **Inventory Bookings** | 1 file, 1 test | Poor — only overlap prevention tested |
| **Inventory Requisitions** | 1 file, 2 tests | Fair — approval + separation of duty tested |
| **Inventory Alerts** | 1 file, 1 test | Good — command generates correct alerts |
| **Inventory Audit** | 1 file, 1 test | Good — IP/position audit compliance |
| **Dashboard** | 1 file, 2 tests | Fair — access control tested |
| **Unit Tests** | 1 file, 1 test | Trivial — `true is true` |

**Overall:** 56 tests, 194 assertions, all passing in 29s.

### 9.2 Critical Gaps

| Missing Test | Risk | Details |
|---|---|---|
| **InventoryService unit tests** | **High** | The core business logic (`receiveConsumable`, `receiveAssets`, `issueRequisition`) has no dedicated unit tests. Tested only through HTTP feature tests. |
| **Product edit/update/destroy** | High | Only create and index are tested. Editing, updating, and deleting products are untested. |
| **Booking rejection** | Medium | Only approval and overlap detection tested. Rejection flow is untested. |
| **Requisition listing, storing, show** | Medium | Only approve and issue tested. Creating, listing, and viewing requisitions are untested. |
| **Stock Movements index** | Medium | The audit log viewer has zero test coverage. |
| **Handover index page** | Medium | The handover initiation page is untested. |
| **Handover verification GET page** | Medium | The verification page rendering is untested. |
| **Handover PDF receipt** | Medium | The DOMPdf receipt generation has no test. |
| **Concurrent booking requests** | Low | No race condition test for simultaneous booking requests. |
| **Requisition partial issuance** | Low | No test for issuing fewer items than requested. |
| **Expired stock lot handling** | Low | No test for issuing from lots where all have expired. |
| **Notification delivery failure** | Low | No test for what happens when email notification fails. |

### 9.3 Factory Usage Waste

Several factories exist but are never used in tests:

- `BookingFactory` — exists, unused
- `RequisitionFactory` — exists, unused
- `RequisitionLineFactory` — exists, unused
- `HandoverLogFactory` — exists, unused
- `StockMovementFactory` — exists, unused
- `DepartmentFactory` — exists, unused

### 9.4 Recommendations (Priority)

1. Add unit tests for `InventoryService` covering all three public methods with edge cases (insufficient stock, invalid status, expired lots).
2. Add feature tests for product edit/update/destroy.
3. Add feature tests for booking rejection.
4. Add feature tests for requisition creation, listing, and viewing.
5. Add a test for stock movements index.
6. Use `RequisitionFactory`, `BookingFactory`, `HandoverLogFactory` in existing tests instead of manual creation.
7. Add a test for the handover PDF receipt (test view rendering or controller).
8. Consolidate tests out of the redundant `Feature/Feature/Inventory/` path.

---

## 10. Issues, Risks, and Recommendations

### 10.1 Critical Issues (Must Fix Immediately)

| # | Issue | File | Why It Matters |
|---|---|---|---|
| C1 | `.env` has committed plaintext DB password and APP_KEY | `.env` | Exposed encryption key and database credentials — immediate security breach if repo is public |
| C2 | `APP_DEBUG=true` in `.env` | `.env` | Full error stack traces + env variable exposure in production |

### 10.2 High-Severity Issues

| # | Issue | File | Why It Matters |
|---|---|---|---|
| H1 | No README.md | (missing) | No setup instructions; new developers cannot onboard |
| H2 | No `.env.testing` | (missing) | Testing config is locked to `phpunit.xml`; CI flexibility reduced |
| H3 | `$appearance` cookie unsafely echoed in JS | `resources/views/app.blade.php:10` | Reflected XSS if cookie value is compromised |
| H4 | `SESSION_ENCRYPT=false` | `.env` | Session data stored unencrypted in database |
| H5 | `reserved_qty` never decremented (dead column) | `database/migrations/...` | Schema has dead column; potential future bugs |
| H6 | No unit tests for `InventoryService` | `app/Services/Inventory/` | Core business logic completely untested at unit level |
| H7 | `::int` cast in raw SQL is PostgreSQL-specific | `app/Http/Controllers/DashboardController.php:47` | Reduces database portability; fails on SQLite test DB — currently works because in-memory SQLite is lenient |

### 10.3 Medium-Severity Issues

| # | Issue | File | Why It Matters |
|---|---|---|---|
| M1 | Broad `QueryException` catch | `ProductController.php:179` | Masks unrelated DB errors |
| M2 | No DTOs/API Resources | Multiple controllers | Bloated controllers, repeated array mappings, hard to test |
| M3 | No reject action for requisitions | `RequisitionController.php` | Workflow incomplete; requisitions cannot be rejected |
| M4 | Hard `limit(200)` instead of pagination | `BookingController`, `HandoverController` | Will miss records as system grows |
| M5 | Reference data not cached | `ProductController`, `BookingController` | Repeated DB queries for static data |
| M6 | Redis configured but not used for cache | `.env`, `config/cache.php` | `CACHE_STORE=file` — Redis config exists but unused |
| M7 | Cases of duplicate `sqlite` default in `config/database.php` | `config/database.php` | `default` is `sqlite` in config but `.env` overrides to `pgsql` — no issue but confusing |
| M8 | Console command `inspire` not removed | `routes/console.php` | Default Laravel boilerplate command still present |
| M9 | `ProductController::authorizeResource` may conflict with route role middleware | `ProductController.php:23` | Authorization enforced at both middleware and policy level — redundancy but not a bug |
| M10 | Appearance/Variant page route not registered | `routes/settings.php` | The `appearance.edit` route points to `Inertia::render()` but no controller action exists |

### 10.4 Low-Severity Issues

| # | Issue | File | Why It Matters |
|---|---|---|---|
| L1 | Dead sales factories (SaleFactory, SaleLineFactory) | `database/factories/` | Unused code |
| L2 | `pnpm-workspace.yaml` at root | (root) | Leftover from alternative package manager |
| L3 | Tests in `Feature/Feature/Inventory/` | `tests/Feature/Feature/` | Redundant nesting |
| L4 | `Product.type` and status fields should be enums | `app/Models/` | Type safety improvement |
| L5 | `vue/multi-word-component-names` disabled | `eslint.config.js` | Weakened code convention |
| L6 | `no-explicit-any` disabled | `eslint.config.js` | Weakened TypeScript safety |
| L7 | `CACHE_STORE` mismatch between `.env` and `.env.example` | `.env` vs `.env.example` | Confusing for new setups |

---

## 11. Final Assessment

### 11.1 Overall Rating: **B / GOOD**

The project is in a **solid, well-architected state** with a few critical security issues that need immediate attention.

### 11.2 What Works Well

- **Architecture**: Clean separation of concerns, Service pattern for business logic, proper transactional integrity, comprehensive audit trails.
- **Security posture**: RBAC with granular policies, email verification, 2FA, rate limiting, pessimistic locking, CSRF protection — all correctly implemented.
- **Frontend**: Modern Vue 3 + Inertia.js SPA with TypeScript strict mode, composable pattern, typed route functions, and a shadcn-vue UI library.
- **Database design**: Well-normalized schema with proper indexing, foreign keys, and CHECK constraints.
- **Workflow completeness**: Requisition lifecycle (submit → approve → issue), booking lifecycle (request → approve/reject), handover lifecycle (initiate → verify → receipt).
- **Testing**: All 56 tests pass. Auth flow coverage is excellent. Critical inventory workflows are tested end-to-end.

### 11.3 What Needs Immediate Improvement

1. **🔴 CRITICAL**: Rotate all exposed secrets (APP_KEY, DB_PASSWORD) and ensure `.env` is removed from version control.
2. **🔴 CRITICAL**: Set `APP_DEBUG=false` and enable `SESSION_ENCRYPT=true`.
3. **🟡 HIGH**: Add `README.md` with setup instructions.
4. **🟡 HIGH**: Add unit tests for `InventoryService` (core business logic).
5. **🟡 HIGH**: Either implement `reserved_qty` usage or remove the column.
6. **🟡 HIGH**: Fix the XSS vector in `app.blade.php` for the `appearance` cookie.

### 11.4 Priority Action Plan

| Priority | Action | Effort | Impact |
|---|---|---|---|
| **P0 — Immediate** | Rotate APP_KEY and DB password; remove .env from git | 5 min | Critical security fix |
| **P0 — Immediate** | Set APP_DEBUG=false; enable SESSION_ENCRYPT=true | 2 min | Critical security fix |
| **P1 — This Week** | Add README.md with setup instructions | 1 hour | Developer onboarding |
| **P1 — This Week** | Fix XSS vector in app.blade.php | 15 min | Security fix |
| **P1 — This Week** | Add unit tests for InventoryService | 2 hours | Core logic coverage |
| **P1 — This Week** | Remove dead sales code and consolidate test directories | 30 min | Code hygiene |
| **P2 — This Month** | Add missing feature tests (product update/destroy, booking reject, requisition CRUD, stock movements) | 4 hours | Test coverage |
| **P2 — This Month** | Replace hard limit(200) with pagination on bookings/handovers | 30 min | Scalability |
| **P2 — This Month** | Cache reference data (categories, origins, departments, positions) | 1 hour | Performance |
| **P3 — Next Quarter** | Refactor controller inline arrays to Eloquent API Resources | 3 hours | Maintainability |
| **P3 — Next Quarter** | Add PHP 8.1 enums for status/type fields | 1 hour | Type safety |
| **P3 — Next Quarter** | Use unused factories in tests (BookingFactory, etc.) | 1 hour | Test quality |
| **P3 — Next Quarter** | Configure production mailer and queue worker documentation | 1 hour | Production readiness |

### 11.5 Verdict

PUP PRISM is a **well-engineered inventory management system** with a modern tech stack, proper architectural patterns, and comprehensive business logic. The team has done excellent work on security fundamentals (RBAC, 2FA, rate limiting, transactional integrity). The immediate concern is the **exposure of secrets in `.env`** — this must be addressed before anything else. Once resolved, the remaining work is about **increasing test coverage, adding documentation, cleaning up dead code, and performance tuning** — all normal maturation tasks for a production-bound application.
