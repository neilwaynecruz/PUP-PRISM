# Accuracy Review — System Improvement Analysis

> **Review date:** 2026-07-03  
> **Method:** Line-by-line verification against the live codebase (`app/`, `routes/`, `tests/`, `config/`).

## Overall Verdict

The analysis is **directionally correct** and the prioritization is sound, but several claims were **overstated**, **understated**, or **outdated**. The 10-feature roadmap remains valid after corrections — with Feature 7 significantly narrowed and Feature 3 elevated in severity.

**Confidence after review:** ~85% accurate as-is; ~95% accurate with the corrections below applied.

---

## Verified Correct Claims At Review Time

| Claim | Evidence |
| ----- | -------- |
| Open Fortify registration, no role on create | `config/fortify.php` line 147; `CreateNewUser.php` lines 27–31 |
| Sanctum `expiration => null` | `config/sanctum.php` line 53 |
| API lacks `verified` middleware | `routes/api.php` line 19 |
| `RequisitionPolicy::create()` returns `true` | `app/Policies/RequisitionPolicy.php` line 32 |
| `BookingPolicy` has no `reject()` method | `app/Policies/BookingPolicy.php` — fixed 2026-07-03; `reject()` now mirrors `approve()` |
| `bulkReject` calls `authorize('reject')` | `BookingController.php` line 282 |
| No `ForecastController` / forecasting pages | Glob search returns 0 files — fixed 2026-07-03; `ForecastController` plus `inventory/forecasting/Index.vue` and `Show.vue` added |
| Notifications use `Queueable` but not `ShouldQueue` | All 7 files in `app/Notifications/` — fixed 2026-07-03; all implement `ShouldQueue` on the `notifications` queue |
| `InventoryRealtimeMessage` uses `ShouldBroadcastNow` | `app/Events/InventoryRealtimeMessage.php` — fixed 2026-07-03; now implements `ShouldBroadcast` |
| `DashboardStatsService` has no `Cache::` usage | Grep returns no matches — fixed 2026-07-04; `getAdminStats()` and `getProcurementStats()` use `DashboardStatsCache` |
| Playwright E2E not in CI | Fixed 2026-07-04 — `.github/workflows/e2e.yml` runs Playwright on push/PR; Pest remains in `tests.yml` |
| `PurchaseOrderGenerator` uses forecast snapshots for qty | `PurchaseOrderGenerator.php` `resolveRecommendedQuantity()` |
| PO `generate` action exists on controller | `PurchaseOrderController::generate()` line 285 |

---

## Post-Implementation Updates

The following review findings were correct when this document was written, but they are **no longer current** after the completed security work on 2026-07-03:

- `config/sanctum.php` no longer uses `expiration => null`; it now reads `SANCTUM_TOKEN_EXPIRATION` with a 90-day default.
- `routes/api.php` no longer lacks `verified` middleware; the protected API group keeps `verified` and now also splits read/write routes behind Sanctum ability middleware.
- `RequisitionPolicy::create()` and `BookingPolicy::create()` no longer return `true` for every authenticated user; they now require `Admin`, `Supply Head`, or `Property Custodian`.
- API token management is no longer test-only; Admin and Supply Head users now have a Settings-based token creation and revocation UI with one-time plaintext exposure.
- `BookingPolicy` now defines `reject()`; the booking Show-page reject dialog and `bulkReject` workflow are functional again for Admin and Property Custodian approvers on requested bookings.
- Web `BookingController::store()` and `RequisitionController::store()` now call `authorize('create', ...)`; `AuditLogPolicy` is explicitly registered in `AuthServiceProvider`.
- Security headers middleware is registered on the web stack, handover signatures are validated as PNG data URIs, and verification tokens are session-backed after the initial email-link redirect.
- All application notifications are queued on the `notifications` queue with 3 retries; `InventoryRealtimeMessage` broadcasts asynchronously via `ShouldBroadcast`. Production requires a persistent queue worker (README deployment + P1.7).
- Dedicated forecasting management UI is available at `/inventory/forecasting` for Admin and Supply Head users, with profile tuning and consumable forecast detail views.
- Forecast-driven procurement closes the loop from `forecast_stockout` alerts: `PurchaseOrderGenerator::generateFromForecastAlerts()` drafts POs for above-threshold forecast-urgent products; `ProcurementRecommendationNotification` notifies Supply Head on new alert creation; purchase orders Index exposes "Generate from forecasts".
- Notification preferences and daily digests are implemented: users manage mail/in-app/realtime channels per event type in Settings; `NotificationService` and notification `via()` respect stored or role-based defaults; `app:send-notification-digests` batches daily email summaries.
- Dashboard aggregate stats are cached via `DashboardStatsCache` (`DASHBOARD_CACHE_ENABLED`, `DASHBOARD_CACHE_TTL`) with version-based invalidation on stock movements, requisition/booking status changes, and purchase order updates. User-specific notifications in shared Inertia props remain uncached.
- Observability and CI E2E are implemented: optional `sentry/sentry-laravel` (env-gated via `SENTRY_LARAVEL_DSN`); `admin/health` returns `failed_jobs_count`, `queue_connection`, and `scheduler_last_runs`; scheduled commands record heartbeats via `SchedulerHeartbeat`; `CleanupTrashTest` and `BulkWorkflowTest` cover prior gaps; Playwright runs in `.github/workflows/e2e.yml`.

---

## Corrections Required

### 1. Feature 7 (Proactive Procurement) — OVERSTATED

**Original claim:** Forecast snapshots do not drive proactive alerts; procurement is fully reactive.

**Actual state:** `app:generate-demand-forecasts` already:
- Persists `ForecastSnapshot` records
- Creates `forecast_stockout` `InventoryAlert` rows via `syncForecastAlerts()` in `GenerateDemandForecasts.php`
- Is tested in `DemandForecastingTest.php` and `InventoryAlertsTest.php`

**What was still missing at review time (now implemented 2026-07-04):**
- ~~`PurchaseOrderGenerator::generateFromAlerts()` only includes products where `on_hand_qty <= reorder_threshold`~~ — `generateFromForecastAlerts()` targets active `forecast_stockout` alerts with stock above threshold
- ~~No `ProcurementRecommendationNotification` when forecast alerts are created~~ — dispatched via `NotificationService::procurementRecommendation()` in `syncForecastAlerts()`
- ~~No UI to generate POs from forecast-urgent items~~ — purchase orders Index "Generate from forecasts" button and `generateFromForecasts` controller action
- Redundant to add a separate `app:procurement-scan-forecasts` command *(intentionally not added)*

**Revised priority:** Medium (was High) — **implemented**

---

### 2. Feature 3 (Booking Reject) — UNDERSTATED

**Original claim:** `bulkReject` may fail due to missing `reject()` policy.

**Actual state — worse than documented:**
- `bookings/Show.vue` gates the reject button on `can.reject` (line 132), which calls `$user->can('reject', $booking)` — returns **false** when policy method is missing
- **Single reject UI is also broken** — reject dialog never appears for authorized users
- `update()` uses `authorize('approve')` for both approve and reject actions, but the reject form is hidden behind `can.reject`
- `bulkReject` will 403 on every iteration

**Revised priority:** High → **Critical** (user-facing workflow broken)

---

### 3. Feature 1 (Open Registration) — Needs nuance

**Original claim:** Any user can register and access the system.

**Nuance:**
- Web inventory routes require `role:Admin|Supply Head|Property Custodian` middleware — roleless users **cannot** use inventory via the web UI
- Real exposure: account sprawl, `/dashboard` access, and **API** access (`POST /api/requisitions` with a self-issued Sanctum token)
- No token management UI exists today, but tokens can be created programmatically

Risk remains **Critical** for production, but the attack path should be described as API + account sprawl, not full inventory access via web.

---

### 4. Feature 8 (Notification Preferences) — Duplicate framing

**Original claim:** Implied building a notification center.

**Actual state:** In-app notification center **already exists**:
- `AppNotificationMenu.vue` with real-time Reverb + 60s polling fallback
- `NotificationController` (mark read / read all)
- `NotificationCenterTest.php`, `WorkflowNotificationsTest.php`

**Revised scope:** Preferences and digests only — not a notification center.

---

### 5. Test count — OUTDATED

**Original claim:** "149+ Pest tests"

**Actual (2026-07-03):** `195 passed` (1 skipped), 1040 assertions — `php artisan test --compact`

---

### 6. AuditLogPolicy registration — Lower severity

**Original claim:** `AuditLogPolicy` not registered is a gap.

**Actual state:** `AuditLogController` calls `authorize('viewAny', AuditLog::class)` and tests pass. Laravel policy auto-discovery resolves `AuditLog` → `AuditLogPolicy` by naming convention. Explicit registration in `AuthServiceProvider` is **hygiene**, not a functional bug.

---

### 7. Dashboard HTTP caching — Clarification

**Original claim:** Dashboard has no caching.

**Actual state (updated 2026-07-04):**
- `DashboardStatsService` now caches `getAdminStats()` and `getProcurementStats()` via `DashboardStatsCache` (`DASHBOARD_CACHE_ENABLED`, default 90s TTL)
- `HandleInertiaRequests` sets `Cache-Control: private, no-store` for **all** authenticated GETs — browser HTTP caching remains disabled; application-level aggregate caching is separate
- `InertiaCacheHeadersTest.php` asserts `no-store`, not `max-age`

Feature 9 (application-level cache) is implemented.

---

### 8. API `authorize('create')` on store — Clarification

`Api\RequisitionController::store()` **does** call `$this->authorize('create', Requisition::class)` (line 55). Web `RequisitionController::store()` now also authorizes `create` explicitly (2026-07-03); route role middleware remains the outer compensating control.

---

### 9. Model count

**Original claim:** 22 Eloquent models  
**Actual:** 21 model files in `app/Models/`

Minor; does not affect recommendations.

---

## Gaps Not Covered in Original 10 Features

These are real but were omitted (consider Phase 4 or add as Feature 11+):

| Gap | Severity | Notes |
| --- | -------- | ----- |
| Booking reject UI broken (Show + bulk) | Critical | Covered by revised Feature 3 |
| Spatie **permissions** tables unused (roles only) | Low | Over-provisioned RBAC infra |
| `SESSION_ENCRYPT=false` default | Medium | Production hardening |
| Handover verify routes lack `verified` middleware | Low | Documented in security audit |
| No registration rate limit beyond Fortify login limits | Low | Separate from login throttle |
| `trash:cleanup` command untested | Low | Fixed 2026-07-04 — `CleanupTrashTest.php` |
| Bulk approve/reject/issue routes untested | Medium | Fixed 2026-07-04 — `BulkWorkflowTest.php` (requisitions: bulk-approve, bulk-issue; bookings: bulk-reject) |
| `PurchaseOrderSentNotification` untested | Low | Minor test gap |
| Unused frontend composables (`useOptimisticState`, etc.) | Low | Maintenance debt, not user-facing |

---

## Revised Priority Order

1. **Critical:** Fix `BookingPolicy::reject()` (broken reject UI + bulk reject)
2. **Critical:** Secure registration + API policy tightening
3. **High:** Sanctum hardening, security headers, queued notifications
4. **High:** Forecasting management UI (Feature 6)
5. **Medium:** Forecast-driven PO extension (narrowed Feature 7), notification preferences, dashboard cache, observability

---

## Files Updated After This Review

- `SYSTEM_IMPROVEMENT_ANALYSIS.md` — corrected Features 1, 3, 7, 8; test count; executive summary
- `AI_IMPLEMENTATION_PROMPTS.md` — corrected Prompts 3 and 7
- `IMPLEMENTATION_TRACKING.md` — updated priorities and added review note
