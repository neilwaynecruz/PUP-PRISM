# PUP-PRISM Responsive Design, Workflow Efficiency, and Verification Report

**Review date:** 2026-10-30

**Repository:** `C:\laragon\www\PUP_PRISM`

**Branch:** `main` tracking `origin/main`

**Status:** **PARTIALLY VERIFIED**

The current implementation passes the configured TypeScript, ESLint, Prettier, Pint, Vite, relevant Pest, Chromium navigation, responsive/accessibility, Dashboard visual, full Phase 3 visual, and Phase 5 forecasting visual checks. It is not marked fully verified because no physical iPhone, iPad, Android, or Samsung Internet device was available, assistive-technology sessions were not performed, and Firefox/WebKit were not rerun after the final navigation and Dashboard changes.

No commit, push, merge, deployment, dependency change, or secret exposure was performed.

## 1. Initial findings and preserved work

At the start of this continuation pass, the worktree already contained 36 tracked modified files from earlier responsive-design work. Those changes included the maroon-and-gold migration, master-data refinements, Bookings adaptations, Receiving and Stock Movements fixes, Settings refinements, and Playwright coverage. They were preserved.

The most important reproducible findings were:

- The Dashboard light theme used too many tinted surfaces and decorative color washes.
- The navigation trigger did not expose its state or controlled element, and close paths did not share one reliable state transition.
- The JavaScript `max-width: 768px` breakpoint conflicted with Tailwind's `md` boundary.
- The drawer did not subscribe to `app:close-overlays` and used Inertia `success` rather than the broader `navigate` event.
- Mobile open state could survive a mobile-to-desktop-to-mobile transition.
- Dashboard custom dates allowed an invalid reverse range without immediate accessible feedback.
- Purchase-order receipt idempotency checked the cache before obtaining a request lock. A lock timeout could also be misreported as success.
- The existing report overstated current browser/device evidence.

## 2. Completed checklist

- [x] Preserve pre-existing work and role-based navigation.
- [x] Retain semantic maroon/gold tokens and distinct status colors.
- [x] Neutralize the Dashboard light-mode canvas and analytical cards.
- [x] Keep Dashboard dark mode readable with differentiated surfaces.
- [x] Add accessible Dashboard date-range error prevention.
- [x] Correct drawer state, route close, breakpoint state, and trigger semantics.
- [x] Verify backdrop, Escape, focus, active state, scroll lock, and desktop collapse.
- [x] Serialize identical receiving requests before the idempotency check.
- [x] Fail visibly on lock contention instead of returning false success.
- [x] Preserve partial receiving, quantity/tag validation, locking, audit logs, and realtime ordering.
- [x] Recheck Stock Movements navigation and Receiving transaction tests.
- [x] Complete two verification passes and inspect screenshots.
- [x] Document unavailable environments and limitations.

## 3. Branding and Dashboard design

The approved direction remains institutional maroon for primary actions, focus, selection, and active navigation; restrained gold for secondary emphasis; neutral surfaces for structure; and green/amber/red/blue-cyan for semantic states.

`resources/css/app.css` supplies semantic primary, accent, surface, input, table-border, disabled, and status values for both themes. No blind global replacement was performed.

`Dashboard.vue` now:

- Uses a neutral constrained page surface instead of a pink canvas.
- Uses `bg-card`, structural borders, and minimal `shadow-xs` elevation.
- Removes decorative KPI blur blobs, full-card pastel washes, and hover-lift effects.
- Keeps color on compact icons, indicators, badges, and chart series.
- Uses two KPI columns on phones, three at intermediate widths, and six on large desktops.
- Uses neutral cards for trends, summaries, purchase orders, supplier performance, exception lists, and recovery information.
- Replaces hardcoded inventory links with Wayfinder route functions.
- Constrains large-screen width for readable proportions.

Light mode now has neutral backgrounds, white cards, gray borders, strong headings, maroon actions, and limited gold. Dark mode uses differentiated charcoal/maroon surfaces, visible borders, and controlled status colors. The predictive panel retains a subtle institutional tint to distinguish it as analysis, but the overall page no longer reads as pink or visually noisy.

## 4. Manual-input and efficiency audit

Inputs were classified as required manual, derived, prepopulated/editable, searchable, scan-assisted, reusable preference, conditional, system-generated, approval-controlled, or sensitive/non-automatable.

| Workflow | Efficiency/error-prevention pattern | Inputs intentionally manual |
|---|---|---|
| Dashboard | Today/week/month presets; invalid custom range is announced and blocked. | Custom dates when presets do not fit. |
| Products | Search/filter, master-data selections, bulk labels, duplicate controls. | Identity, classification, reorder policy, descriptions. |
| Assets | QR/tag lookup, search, lifecycle display, product/location selection. | Physical tag confirmation, condition, custody, disposal decisions. |
| Suppliers | Existing records are selected instead of retyped; responsive filtering/cards. | Legal/contact data and activation decisions. |
| Purchase Orders | Supplier/product selection and calculated line/order summaries retained. | Quantity, negotiated overrides, notes, submission, approval. |
| Receiving | PO lines/reference context, remaining quantities, partial receipts, asset-only tags, scanner, SKU, name search, catalog browsing. | Delivered quantity, physical tags, exceptions, final confirmation. |
| Stock Movements | Search, QR/manual identifiers, structured movement types. | Count/adjustment quantity and audit reason. |
| Counts/Reconciliation | Expected stock is reference data; variance is derived. | Physical quantity and variance reason. |
| Forecasting | Forecast, confidence, horizon, and recommendation are derived decision support. | Whether to act; no automatic purchasing or stock changes. |
| Handover/Transfers/Lifecycle/Maintenance | Asset search/scan and related-record selection. | Recipient, condition, signatures, outcome, approvals. |
| Bookings | Adaptive calendar, availability context, resource selection, conflict validation. | Resource, time range, purpose, confirmation, approval. |
| Requisitions | Catalog/search/scanner selection and structured lines. | Quantity, justification, submission, approval. |
| QR/Bulk Labels | Existing records drive labels; manual lookup remains available. | Print/layout confirmation and physical application. |
| Users/Master Data | Searchable relationships, normalization/duplicates, filters, status controls. | Identity, role, ownership, and deactivation impact. |
| Personal Settings | Appearance/notifications are reusable preferences. | Password, 2FA, token, and security actions. |

No speculative autosave, silent submission, automatic approval, or browser storage of sensitive values was introduced. No large dataset was preloaded merely to mimic server search.

## 5. Receiving reliability

`InventoryService::receivePurchaseOrder()` now obtains a cache lock before checking/writing its idempotency token. Identical requests cannot both pass the cache check before one records completion.

Retained safeguards:

- Purchase-order, line, and product row locks with stable ordering.
- Partial receiving and receipt-status transitions.
- Positive and remaining-quantity validation.
- Asset-tag uniqueness checks.
- Atomic stock, purchase-order, and audit updates.
- Realtime dispatch only after commit.

The request lock has a 120-second lease and waits up to 5 seconds. Timeout now throws a clear `RuntimeException` instead of returning a fresh order that could appear successful. The regression holds the lock, expects the failure, and confirms quantities, stock, and movements remain unchanged. The configured PostgreSQL concurrency test was also run.

## 6. Navigation toggle correction

### Root cause and fix

- The mobile query is now `(width < 48rem)`, matching Tailwind's pre-`md` behavior.
- `app:close-overlays` and Inertia `navigate` close the drawer.
- Listeners are removed when the provider unmounts.
- Leaving mobile mode clears `openMobile`, preventing unexpected reopening after orientation/breakpoint changes.
- Mobile and desktop containers expose `id="app-navigation-sidebar"`.
- The trigger exposes `aria-controls`, accurate `aria-expanded`, and dynamic Open/Close or Expand/Collapse labels.
- The competing static header label was removed.
- Existing Reka UI dialog behavior supplies focus containment, Escape/backdrop dismissal, and focus restoration.

Tests verify first-click opening, explicit close, backdrop close, Escape, focus entry/trapping, focus restoration, body scroll lock, route-close behavior, active Products state, breakpoint reset, and desktop collapse/expand.

## 7. Shared responsive improvements

- Mobile record cards paired with desktop tables for Users, Suppliers, Notifications, inventory trash, booking trash, and requisition trash.
- Shared `AdminPagination` on relevant list/trash pages.
- `min-h-11` essential phone controls.
- Safe-area/dynamic-height handling in drawer and scanner/dialog surfaces.
- Bookings: day view on phones, three-day view on tablets, month view on desktop.
- Shared master-data cards, badges, filters, forms, validation, empty states, and dialogs.
- Notification preferences adapt without duplicate input IDs.
- Responsive settings navigation and appearance controls.

## 8. Module coverage

The full Phase 3 suite rendered 28 authenticated page families at 390×844, 834×1112, and 1440×900 in both themes. The responsive suite separately exercised 320, 360, 375, 390, 430, 768, 834, 1024, 1280, 1440, and 844×390 landscape.

| Module/page family | Reviewed result |
|---|---|
| Dashboard | Themes, KPI grid, actions, charts, statuses, date validation, exports, forecast widget, overflow. |
| Products/Assets | Lists/cards, filters, QR/manual alternatives, bulk labels, lifecycle entry points. |
| Suppliers/Purchase Orders | Supplier cards/filters, PO lists/forms, approvals, receiving links. |
| Receiving/Stock Movements | Selection alternatives, partial safeguards, typed tags, page test ID, navigation. |
| Counts/Reconciliation | Count/variance workflows; physical observations remain explicit. |
| Forecasting | Forecast, Optimization/ABC, and Expiration/FEFO tabs passed dual-theme visual review. |
| Handover/Transfers/Lifecycle/Maintenance | Asset selection and protected workflow actions reviewed. |
| Bookings | Phone day, tablet three-day, desktop month, form, queue, dialogs, trash. |
| Requisitions | Catalog/scanner/manual selection, responsive lists, approvals, trash. |
| QR/Bulk Labels | Camera-independent fallback and responsive configuration. |
| Audit Logs/Alerts/Operations Health | Filters, panels, navigation, responsive rendering. |
| Reports/Notifications | Exports, history cards, filters, pagination, preferences. |
| Users | Phone record cards and large-screen table explicitly tested. |
| Master Data | Independent dual-theme phone/tablet/desktop test described below. |
| Settings/Auth | Personal settings scope, responsive navigation, login, protected-route confirmation. |
| PWA/offline | Source/route interfaces inspected; physical install/offline use unavailable. |

## 9. Master-data review

Departments, Positions, Categories, Origins, and Locations were independently exercised in both themes at phone, tablet, and desktop sizes.

Verified: labeled search, applicable department/status filters, mobile records, desktop layouts, create/edit forms, validation presentation, shared badges/counts/actions/dialogs, empty states, pagination patterns, duplicate prevention, 44px controls, and no tested overflow.

Locations additionally uses the shared language for search, filtering, creation, counts/status, and an accessible edit dialog. Shared administration components were reused.

## 10. Settings and secrets

Profile, security, appearance, notification, and API-token settings are personal settings. The frontend does not expose or edit `.env`, `APP_KEY`, database credentials, mail credentials, Reverb secrets, or infrastructure values.

No system-wide environment editor was created. A future administrative settings module would require a separate secure model, policies, audit trail, and explicit authorization.

## 11. Accessibility

Automated Chromium verification covered headings, skip navigation, visible labels, connected date errors, drawer state/names, focus containment/restoration, Escape/backdrop/route dismissal, background scroll lock, mobile touch targets, active navigation state, dual-theme master data, and overflow.

Manual NVDA, JAWS, VoiceOver, and TalkBack sessions were not performed and remain recommended.

## 12. Performance

- No dependency was added.
- CSS grid/flex, existing conditional rendering, and shared components provide the adaptations.
- Server search/pagination patterns were preserved.
- Final Vite build: 12.21 seconds on this workstation.
- Representative chunks: app 373.27 kB (92.61 kB gzip), Dashboard 230.64 kB (72.87 kB gzip), QR scanner 138.27 kB (51.14 kB gzip).
- Dashboard and QR chunks merit future production profiling, but no speculative rewrite was made.

## 13. Browser/device coverage

### Current pass

- Chromium via Playwright.
- Phones: 320×720, 360×800, 375×812, 390×844, 430×932.
- Phone landscape: 844×390.
- Tablets: 768×1024, 834×1112, 1024×768.
- Laptop/desktop: 1280×800, 1440×900.
- Light and dark themes.
- Full visual sweep: 28 authenticated routes × 3 viewports × 2 themes, plus login and six focused Dashboard screenshots.
- Forecasting: three tabs × 5 viewports × 2 themes.

### Unavailable

- Physical Android/iOS phones and tablets.
- Samsung Internet, Chrome on iOS, and native iOS/macOS Safari on hardware.
- Fresh Firefox/WebKit reruns after final source changes.
- Physical mobile-keyboard/native date-picker behavior.

Earlier report revisions recorded Firefox/WebKit success, but that is historical regression evidence rather than verification of the final source state.

## 14. Actual test results

| Command | Result |
|---|---|
| `npm run type-check` | PASS — 0 errors. |
| `npm run lint:check` | PASS — 0 errors. |
| `npm run format:check` | PASS — all matched resource files formatted. |
| `vendor/bin/pint --dirty --format agent` | PASS — `{"result":"pass"}`. |
| `node node_modules/vite/bin/vite.js build` | PASS — 3,204 modules. |
| Dashboard/Receiving/PostgreSQL Pest set | PASS — 25 tests, 135 assertions. |
| `navigation.spec.ts --project=chromium` | PASS — 4 tests in 1.2 min. |
| `accessibility-responsive.spec.ts --project=chromium` | PASS — 22 tests in 6.0 min. |
| `phase3-visual-review.spec.ts --project=chromium` | PASS — 2 tests in 6.0 min. |
| `phase5-visual-review.spec.ts --project=chromium` | PASS — 1 test in 41.1 sec. |

One initial Phase 3 invocation exceeded the orchestration tool's five-minute response limit. It was not counted as a test failure. The same command was rerun in a persistent session and passed in six minutes.

## 15. Two verification passes

### Pass 1

- Reviewed/preserved the existing diff.
- Corrected Dashboard surfaces, routes, and date validation.
- Corrected navigation semantics/events/breakpoint state.
- Corrected receipt serialization and timeout behavior.
- Ran targeted TypeScript, navigation, date, receipt, Pint, and build checks.

### Pass 2

- Rebuilt after the final navigation source change.
- Ran full Chromium navigation and 22-case responsive/accessibility suites.
- Ran the 25-test backend set.
- Ran focused/full Phase 3 and Phase 5 visual suites.
- Directly inspected Dashboard phone/tablet/desktop screenshots in both themes.
- Reviewed final diff/stat and status.

## 16. Modified files and final status

The final worktree contains 41 modified tracked files and no untracked files.

- Backend: `InventoryService.php`.
- Theme: `resources/css/app.css`.
- Shell/navigation/settings: AppSidebar, header, appearance/user menu, sidebar primitives/provider/trigger, settings layout.
- Dashboard/forecasting: Dashboard, ForecastWidget, forecasting Index/Show.
- Administration: Locations, Users, and Departments/Positions/Categories/Origins Index/Create/Edit.
- Responsive lists: inventory Trash, Bookings Index/Trash, Requisitions Trash, Suppliers, Notifications, Notification Preferences.
- Tests: Purchase Order Receiving Transactions, accessibility-responsive, Phase 3, Phase 4, Phase 5.
- Documentation: this report.

## 17. Limitations and commit readiness

1. Physical-device/native-browser verification is unavailable.
2. Manual assistive-technology sessions are outstanding.
3. Final-source cross-browser verification is Chromium-only.
4. Multi-node receipt serialization requires a shared lock-capable production cache; database row locks still protect purchase-order/stock rows.
5. The Dashboard remains intentionally information-rich and long on phones, although cards are compact.
6. Dashboard/QR bundle sizes should be measured with production telemetry.

No known critical/high-severity defect remains in the tested Chromium/backend scope. The changes are **technically ready for review**, but the broad 41-file diff should be logically grouped before any commit. Physical-device, assistive-technology, and final Firefox/WebKit checks are recommended before production release.

**Final verdict: PARTIALLY VERIFIED.**

---

## 18. System reliability and usability audit (continuation, 2026-10-30)

**Status of this pass: PARTIALLY VERIFIED** — every reproduced defect is fixed and covered by automated tests that pass, but physical devices, assistive-technology sessions, and two network-simulation tests in WebKit could not be run (see 18.11).

The 41 in-progress tracked files listed in section 16 were preserved. Fixes in this pass were layered on top of them (`AppSidebar.vue` and `layouts/settings/Layout.vue` already contained in-progress edits; only the lines described below were added). No commit, push, merge, deploy, reset, or discard was performed, no dependencies were added, and no `.env` value or credential was read into code, tests, or this report.

### 18.1 Initial failure baseline

| Check | Result before changes |
|---|---|
| `npm run type-check` | Pass |
| `npm run lint:check` | Pass |
| `npm run format:check` | Pass |
| Pest (SQLite) | Pass — which hid the backend defects below, because SQLite matches `LIKE` case-insensitively and treats unknown double-quoted identifiers as string literals |
| Browser diagnostic sweep (30 routes, Chromium/Firefox/WebKit, admin) | All routes 200, no horizontal overflow; global-search page error, keyboard and state defects reproduced (18.2) |
| Read-only PostgreSQL probe (dev database) | `LIKE '%lcd p%'` → 0 products; case-insensitive match → 1 |

### 18.2 Reproduced defects and root causes

| # | Defect (how reproduced) | Affected area | Root cause | Correction | Regression test |
|---|---|---|---|---|---|
| 1 | Searches miss records unless the user types the stored capitalisation (PostgreSQL probe) | All server-side searches: global search, products, assets, suppliers, purchase orders, stock movements, bookings, requisitions, handover, users, departments, positions, categories, origins, alerts, audit logs, trash, notifications, reports, API | 95 `where(..., 'like', ...)` calls; PostgreSQL `LIKE` is case-sensitive | Converted to Laravel `whereLike`/`orWhereLike` (compiles to `ILIKE` on PostgreSQL, `LIKE` on SQLite). The PO number generator's exact-prefix match was intentionally left case-sensitive | `CaseInsensitiveSearchTest` (source guard + real PostgreSQL search) |
| 2 | Global search returned HTTP 500 for every query by Admins and Supply Heads (PostgreSQL error `column "reference_no" does not exist`) | Global search | Purchase-order search used a non-existent `reference_no` column; the column is `po_number`. SQLite silently treated `"reference_no"` as a string literal, so tests passed | Search and display `po_number`; numeric terms also match the record ID | `SearchReliabilityTest` (PO number + supplier name) |
| 3 | Procurement CSV/PDF exports listed internal IDs instead of PO numbers | Reports | Same missing `reference_no` attribute (silently `null`, fell back to ID) | Use `po_number` | `SearchReliabilityTest` (procurement export) |
| 4 | Notification history search and category filter returned HTTP 500 on PostgreSQL (`operator does not exist: text ->> text`) | Notifications | `notifications.data` is a `text` column; JSON-path filters need `jsonb` on PostgreSQL | Driver-aware expression (`data::jsonb ->> 'key'` on PostgreSQL, JSON path elsewhere); malformed date filters are ignored instead of reaching the database | `SearchReliabilityTest` (SQLite + real PostgreSQL) |
| 5 | Asset results in global search opened the product page | Global search | URL built from `inventory.products.show` with the product ID | Link to `inventory.assets.show` | `SearchReliabilityTest` |
| 6 | Searching `#12` found nothing | Global search | Only bare digits were treated as record numbers | Accept an optional leading `#` | `SearchReliabilityTest` |
| 7 | Uncaught `focus is not a function` page error every time global search opened (all three browsers) | Global search dialog | Template ref pointed at the `Input` component instance, not the `<input>` | Focus the component's root element | E2E: opens focused, no page errors |
| 8 | "No matching records found." flashed before the search had run, and again while a newer request was in flight (Chromium, Firefox) | Global search dialog | Empty state keyed on `!loading`; an aborted request's `finally` cleared `loading` while the newer request was pending | Track the term whose request completed and a request ID; only the newest request may update state; empty state shown only for the completed current term | E2E with delayed responses |
| 9 | Arrow keys/Enter did nothing in global search | Global search dialog | No keyboard handling | Combobox/listbox semantics, `aria-activedescendant`, Arrow/Home/End/Enter, scroll-into-view, live result count | E2E keyboard selection |
| 10 | Failed record search gave a dead-end message | Global search dialog | No retry path; message not announced | Alert role, distinct offline vs server wording, **Try again** button | E2E simulated 500 + retry |
| 11 | Ctrl+K ignored while focus was in any field; ignored with Caps Lock | Keyboard shortcuts | Typing-target early return ran before the shortcut check; case-sensitive key check | Handle Ctrl/Cmd+K first, case-insensitively, and prevent the browser default | E2E Ctrl+K from a page field, all viewports |
| 12 | Sidebar filter text and hidden menu items persisted after navigating | Sidebar navigation search | Persistent layout keeps component state across Inertia visits | Clear the filter on Inertia `navigate` | E2E sidebar filter cleared |
| 13 | Active Settings page indicated only by colour | Settings navigation | No `aria-current` | `aria-current="page"` on the active link | E2E settings links |

Checked and found working (no change needed): list-page searches debounce and route through Inertia, which cancels superseded visits and keeps query parameters for back/forward; requisition line search aborts superseded requests; theme switching applies immediately and persists across reloads; profile validation keeps input and shows the error beside the field; notification preference toggles persist; Security settings correctly require password confirmation; API-token and security forms already disable submit buttons while processing.

### 18.3 Search corrections (summary)

Global search is server-side (5 results per record type, policy-filtered) plus client-side page/action filtering. All list-page searches are server-side and paginated; no full datasets are loaded for searching. Leading/trailing spaces are trimmed server-side; clearing a query restores results; loading, empty, and failure states are now distinct and announced.

### 18.4 Settings corrections

Only one confirmed Settings defect (13). Verified by E2E: every Settings link (Profile, Security via password confirmation, API Tokens, Appearance, Notifications), theme persistence, profile validation and persistence, and notification-preference persistence. No interface for server configuration was added.

### 18.5 Shared-component corrections

The shared defects were in `GlobalSearchDialog.vue`, `useKeyboardShortcuts.ts`, `AppSidebar.vue`, and the Settings layout. No defect was found in the shared `Input`, `Dialog`, `DropdownMenu`, `Checkbox`, or pagination components; the focus error came from how the dialog referenced `Input`, not from `Input` itself.

### 18.6 Module coverage

Diagnostic sweep (admin, three browsers): Dashboard, Products, Assets, Physical Counts, Suppliers, Purchase Orders, Receiving, Stock Movements, Forecasting, Handover, Bookings, Requisitions, Audit Logs, Trash, Notifications, Alerts, Operations Health, Users, Departments, Positions, Categories, Origins, Locations, Bulk Label sheet, and all five Settings pages loaded with HTTP 200, no page errors, and no horizontal overflow. Workflow-level coverage came from the existing navigation, accessibility/responsive, handover, bookings, requisitions, auth, and security specs (Pass 2).

### 18.7 Accessibility results

Global search now exposes combobox/listbox roles, active-descendant, a polite live region, and an alert role for failures; Settings exposes the current page. Drawer focus restoration is verified for keyboard activation in all browsers. No manual screen-reader session was performed.

### 18.8 Responsive results

All ten required viewports (320×720, 360×800, 390×844, 430×932, 844×390, 768×1024, 834×1112, 1024×768, 1280×800, 1440×900) pass in Chromium, Firefox, and WebKit: drawer opens on first activation (below 768 px), Escape closes it and returns focus, desktop collapse/expand works (768 px and up), Ctrl+K search opens, no horizontal overflow, no page errors.

### 18.9 Browser-console findings

- Fixed: `focus is not a function` (global search, all browsers).
- Environment only: Firefox in the sandbox could not download Google Fonts (no internet); FullCalendar's embedded icon font logs harmless glyph warnings in Firefox.
- Not a defect: `/inventory/reports` returns 404 — it was a guessed URL in the diagnostic; no link points there (reports are export endpoints).

### 18.10 Test commands and results

| Command | Result |
|---|---|
| `php artisan test --compact` (full suite, after backend fixes) | 542 passed, 1 skipped (pre-existing skip) |
| `php artisan test --compact tests/Feature/Search tests/Feature/Notifications tests/Feature/Inventory/ReportExportTest.php` (final) | 60 passed |
| PostgreSQL-backed tests (`--filter=postgresql`) | 2 passed against `pgsql_testing`, inside rolled-back transactions; test data unchanged afterwards (100 products, 133 users) |
| `npx playwright test tests/e2e/search-settings-reliability.spec.ts` (Chromium, Firefox, WebKit) | 61 passed, 2 skipped (WebKit, see 18.11) |
| `npx playwright test navigation accessibility-responsive handover bookings requisitions auth security --project=chromium` | 34 passed |
| `npm run type-check` / `lint:check` / `format:check` | Pass |
| `npm run build` | Pass |
| `vendor/bin/pint --dirty --format agent` | Pass |

Regression tests were written to fail first: the source guard listed every case-sensitive search, the PostgreSQL test failed on the `reference_no` error, and the Settings E2E failed on the missing `aria-current`.

### 18.11 Unavailable environments and remaining limitations

1. **WebKit request interception:** a probe showed Playwright's `page.route` intercepts `fetch()` in Chromium and Firefox but not in this Windows WebKit build (intercepted 0 requests). The two tests that simulate slow or failing search responses are skipped in WebKit only, with the reason in the test; the same behaviour passes in Chromium and Firefox.
2. No physical iPhone, iPad, Android, or Samsung Internet device; no manual screen-reader session.
3. The `pgsql_testing` database has one pending migration (the handover cancellation fields); it does not affect the search tests.
4. `LIKE` wildcards typed by users (`%`, `_`) are not escaped, so they act as wildcards. Low risk; not changed.
5. Physical-device and assistive-technology limitations from section 17 still apply.

### 18.12 Files changed in this pass

- Backend search: 23 controllers/services converted to `whereLike` (`GlobalSearchService`, `NotificationController`, `ProcurementReportService`, `ProductInventoryReportService`, `StockMovementAuditReportService`, Admin/Inventory/Api controllers).
- Frontend: `GlobalSearchDialog.vue`, `useKeyboardShortcuts.ts`, `AppSidebar.vue` (navigate listener only), `layouts/settings/Layout.vue` (`aria-current` only).
- Tests: `tests/Pest.php` (`withPostgresTestingDatabase` helper), new `tests/Feature/Search/CaseInsensitiveSearchTest.php`, `tests/Feature/Search/SearchReliabilityTest.php`, `tests/e2e/search-settings-reliability.spec.ts`.
- Generated: Wayfinder `resources/js/actions` and `resources/js/routes` files (line-number comments only, regenerated by the build).
- Documentation: this section.

### 18.13 Final Git status and commit readiness

Branch `main` tracking `origin/main`; 70 tracked files modified (41 pre-existing in-progress files plus this pass) and 3 new test files; nothing staged or committed. Temporary diagnostic specs were deleted.

**Commit readiness:** the search, notification, and report fixes are self-contained and suitable to commit as one change (backend + tests), separately from the in-progress responsive work. The data-correctness fixes (18.2 #1–#4) affect every PostgreSQL deployment and should be prioritised.

**Final verdict for this pass: PARTIALLY VERIFIED.**

### 18.14 Follow-up: header Search, notifications, and profile menu stopped responding

**Reported:** Search, the notification bell, and the profile menu could not be clicked.

**Reproduced (Chromium, 1536x864):** after opening the notification menu and choosing **View notification history** (or a notification), `<body>` kept `pointer-events: none; overflow: hidden` and the bell trigger stayed `data-state="open"` / `aria-expanded="true"` on the next page. `elementFromPoint` over Search, the bell, and the profile trigger returned `<html>`, so every click on the page was ignored. Opening and closing the menus without navigating, and navigating from the profile menu, did not reproduce it.

**Root cause:** `AppNotificationMenu.vue` is a modal dropdown, but its history link is a plain Inertia `<Link>` and its notification rows are plain buttons that call `router.visit`. Unlike menu items, they do not close the menu, so it stayed open across the navigation (the layout persists) and kept the page body locked. No other dropdown in the app navigates this way.

**Fix:** the menu's open state is controlled; it closes before navigating from a notification or the history link, and also on every Inertia `navigate` event (covers back/forward and any future links).

**Regression tests:** `search-settings-reliability.spec.ts` › *Header and account menus* (history link and notification row; the row test seeds its own notification through the new `createUnreadNotification` E2E helper). With the fix temporarily reverted the history-link test failed on `pointer-events: none`; with the fix, both pass in Chromium, Firefox, and WebKit (6/6).

**For an already-open browser tab:** reload once; the stuck state does not survive a full page load.

### 18.15 Follow-up: Settings → Security only worked after a refresh

**Reported:** clicking **Security** in My settings did not open the page until the browser was refreshed.

**Evidence from the real browser** (`storage/logs/browser.log`, Laravel Boost capture, `http://localhost:8000`, Chrome): every SSR page load logged hydration mismatches in the sidebar footer, "rendered on server: Dark mode / moon icon" vs "expected on client: Light mode / sun icon" (52 occurrences today), followed by Vue update crashes (`insertBefore … not a child of this node`, `Cannot set properties of null (setting '__vnode')`, `Cannot read properties of null (reading 'parentNode')` inside the Inertia page update). Once Vue's DOM bookkeeping is corrupted, Inertia cannot render the next page, so links appear dead until a full reload.

**Why it was not visible before:** the isolated E2E server does not server-render pages (it returns an empty `<div id="app">`), and the E2E browser used a light OS theme.

**Root cause:** `useAppearance().resolvedAppearance` read `matchMedia('(prefers-color-scheme: dark)')` during render. The server has no window and always rendered the light-OS variant; a browser on a dark OS rendered the other variant on its first (hydration) pass.

**Fix:** `resources/js/composables/useAppearance.ts` keeps the OS preference in a ref that starts as `false` (identical to the server) and is read after mount, with a single shared `change` listener. The first client render now matches the server; the theme toggle label and icon update immediately after mount as a normal reactive update.

**Regression test:** `tests/e2e/ssr-hydration.spec.ts` reproduces local development exactly: it renders the page through the Vite dev SSR endpoint (`/__inertia_ssr`), serves that HTML with the development client from the same Vite server to a dark-OS browser, asserts that Vue actually hydrated `#app`, then navigates Profile → Appearance → Security and fails on any hydration warning or Vue update error. Without the fix it fails with the same four mismatches recorded in the real browser log; with the fix all three themes (System, Dark, Light) pass. It runs in Chromium and skips when the Vite dev server is not running.

**Development note:** today's log also shows `[vite] Failed to reload … importing non-existent modules` errors. Those came from Wayfinder route files being regenerated concurrently (the dev server plus production builds started during this audit) and only affect an open development tab; avoid running `npm run build` while `npm run dev` is active.

**Result:** `ssr-hydration.spec.ts` and `search-settings-reliability.spec.ts` in Chromium: 26 passed. Lint, format, and type-check pass.

