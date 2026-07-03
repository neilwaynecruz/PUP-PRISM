# AI Agent Implementation Prompts

> Copy-paste any prompt below into Cursor, Claude, ChatGPT, Gemini, Windsurf, GitHub Copilot, or any AI coding agent.
> Each prompt is self-contained and production-ready.

---

## Documentation Update Requirements (All Prompts)

When you finish (or stop) work on any feature, **you must update** these files under `docs/system-improvement/`:

| File | What to update |
| ---- | -------------- |
| [IMPLEMENTATION_TRACKING.md](./IMPLEMENTATION_TRACKING.md) | Feature row status, summary counts, overall project status |
| [SYSTEM_IMPROVEMENT_ANALYSIS.md](./SYSTEM_IMPROVEMENT_ANALYSIS.md) | Implementation Checklist table (match tracking file) |
| [ACCURACY_REVIEW.md](./ACCURACY_REVIEW.md) | Only if the implementation changes or invalidates an accuracy note |

### Status values (use exactly one per feature)

| Status | When to use |
| ------ | ----------- |
| `✅ DONE` | Fully implemented, tested (`php artisan test`), and verified — no known gaps |
| `🟡 IN PROGRESS` | Partially done; list what remains in the completion log |
| `⬜ NOT STARTED` | No meaningful implementation yet |
| `⚠️ BLOCKED` | Cannot continue; document blocker and dependency |

### Required updates in IMPLEMENTATION_TRACKING.md

1. Set the feature's **Status** column in the Feature Checklist table.
2. Recalculate the **Summary** table (implemented / not started / in progress / blocked / completion %).
3. Set **Final Project Status** to `✅ FINISHED` only when **all** features and P1 tasks are `✅ DONE`; otherwise keep `🚧 IN PROGRESS`.
4. Append or update a row in **Feature Completion Log** (section below) with: date, feature name, status, tests run, and brief notes.

**Do not mark `✅ DONE` without passing tests for the affected area.**

---

## Prompt 1 — Secure Registration & Admin User Provisioning

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Secure Registration & Admin User Provisioning

Objective:
Replace open self-registration with admin-controlled user provisioning for the internal PUP PRISM inventory system.

Context:
PUP PRISM is a Laravel 13 + Inertia v3 + Vue 3 application for PUP's Supply and Property Management Office. It uses Laravel Fortify for authentication, Spatie Laravel Permission for roles (Admin, Supply Head, Property Custodian), and Wayfinder for typed frontend routes. Registration is currently open in config/fortify.php. CreateNewUser in app/Actions/Fortify/CreateNewUser.php assigns no role. API routes in routes/api.php lack verified middleware. RequisitionPolicy::create() returns true for all authenticated users.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Add REGISTRATION_ENABLED env flag (default false in .env.example for production guidance).
- When REGISTRATION_ENABLED is false, disable Fortify Features::registration() or block registration routes.
- Create Admin-only UserManagementController with index, create, store, edit, update, deactivate actions.
- Create StoreUserRequest and UpdateUserRequest: name, email, password (on create), role (required, one of Admin|Supply Head|Property Custodian), position_id (optional, exists:positions,id).
- Assign Spatie role on user create; sync role on update.
- Log user create/update/deactivate via AuditLogService.
- Add routes under /admin/users with role:Admin middleware in routes/web.php.
- Add resources/js/pages/admin/users/Index.vue, Create.vue, Edit.vue using AppLayout, Wayfinder routes, existing UI components (Input, Select, Button, InputError).
- Add admin nav link visible only to Admin role (inventoryNavigation.ts or separate admin nav).
- Add verified middleware to the routes/api.php group.
- Optional migration: users.is_active (boolean, default true), users.invited_at (nullable timestamp).
- Block deactivating the last Admin account.
- Block Admin self-deactivation.
- Add Pest feature tests: non-admin gets 403; admin can create user with role; registration disabled returns 404 or 403; deactivated user cannot login.
- Run vendor/bin/pint --dirty --format agent on PHP changes.
- Run php artisan test --compact on affected test files.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 2 — API & Sanctum Token Hardening

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

API & Sanctum Token Hardening

Objective:
Harden Sanctum API access with token expiration, scoped abilities, a management UI, and stricter authorization policies.

Context:
PUP PRISM exposes a Sanctum-authenticated REST API in routes/api.php (products, assets, stock-movements, requisitions). config/sanctum.php has expiration set to null. No token management UI exists. RequisitionPolicy::create() and BookingPolicy::create() return true for any authenticated user. API routes lack verified middleware. Tests exist in tests/Feature/Api/ApiIntegrationTest.php.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Set config/sanctum.php expiration from SANCTUM_TOKEN_EXPIRATION env (default 90 days in minutes).
- Tighten RequisitionPolicy::create() and BookingPolicy::create() to require hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']).
- Add verified middleware to the api route group in routes/api.php.
- Create ApiTokenController in app/Http/Controllers/Settings/ (or similar) with index, store, destroy.
- Only Admin and Supply Head can manage API tokens (policy or middleware).
- Token creation: name (required), abilities checkboxes (read, write). Use $user->createToken($name, $abilities).
- Token list: show name, abilities, last_used_at, created_at, expires_at. Never re-show plain token after creation.
- Add resources/js/pages/settings/ApiTokens.vue; add link in settings Layout.vue.
- Apply abilities middleware on API routes: read endpoints accept abilities:read or full token; write endpoints require abilities:write.
- Document SANCTUM_TOKEN_EXPIRATION in .env.example.
- Update tests/Feature/Api/ApiIntegrationTest.php: roleless user POST /api/requisitions returns 403; token with read-only ability cannot POST.
- Run vendor/bin/pint --dirty --format agent and php artisan test --compact --filter=Api.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 3 — Authorization & Booking Reject Bug Fix

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Authorization & Booking Reject Bug Fix

Objective:
Fix the broken booking reject workflow caused by a missing reject() method on BookingPolicy.

Context:
PUP PRISM booking rejection is currently broken in two places:
1. bookings/Show.vue gates the reject button on can.reject, which calls $user->can('reject', $booking). BookingPolicy has no reject() method, so the button never appears.
2. BookingController::bulkReject calls $this->authorize('reject', $booking) and will 403.

Note: BookingController::update() uses authorize('approve') for both approve and reject, but the reject form is hidden behind the broken can.reject check.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Add reject(User $user, Booking $booking): bool to BookingPolicy mirroring approve() with BookingStatus::Requested guard.
- Verify bookings/Show.vue reject dialog appears for Admin/Property Custodian on requested bookings.
- Verify bulkReject works for authorized users.
- Optionally register AuditLogPolicy in AuthServiceProvider (hygiene only — auto-discovery already works).
- Optionally add $this->authorize('create', Model::class) to web store() methods for defense-in-depth.
- Add Pest tests: single reject via update action=reject; bulkReject succeeds; Show page can.reject is true for authorized user.
- Run php artisan test --compact on affected booking tests.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 4 — Production Security Headers & Handover Signature Validation

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Production Security Headers & Handover Signature Validation

Objective:
Add HTTP security headers for production and validate handover signature payloads as real PNG images.

Context:
PUP PRISM has no security headers middleware. HandoverController accepts signature_png as any string up to 300000 characters without format validation. Handover verification uses tokens in URL query parameters. Tests exist in tests/Feature/Inventory/HandoverVerificationTest.php. E2E coverage in tests/e2e/handover.spec.ts.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Create app/Http/Middleware/SecurityHeadersMiddleware.php setting:
  - X-Frame-Options: DENY
  - X-Content-Type-Options: nosniff
  - Referrer-Policy: strict-origin-when-cross-origin
  - Strict-Transport-Security: max-age=31536000; includeSubDomains (production only, when request is secure)
- Register middleware in bootstrap/app.php on web stack.
- Create app/Services/Inventory/HandoverSignatureValidator.php:
  - Require data:image/png;base64, prefix
  - Decode base64; reject invalid encoding
  - Verify PNG magic bytes (0x89 0x50 0x4E 0x47)
  - Max decoded size 512000 bytes
  - Return validated string or throw ValidationException
- Use validator in HandoverController and HandoverVerificationController.
- Update resources/js/pages/inventory/handover/Verify.vue to POST verification token in request body instead of query string where feasible.
- Update HandoverVerificationNotification URL generation if needed.
- Add/update HandoverVerificationTest: reject invalid signature format, reject oversized decoded PNG, accept valid PNG data URI.
- Ensure existing valid handover flows still pass E2E and feature tests.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 5 — Queued Notifications & Async Broadcasting

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Queued Notifications & Async Broadcasting

Objective:
Move notification delivery and WebSocket broadcasting off the HTTP request cycle using Laravel queues.

Context:
PUP PRISM has 7 notification classes in app/Notifications/ that use Queueable but not ShouldQueue. InventoryRealtimeMessage in app/Events/ uses ShouldBroadcastNow (synchronous). NotificationService orchestrates dispatch. Tests in tests/Feature/Notifications/WorkflowNotificationsTest.php. Queue default is database per config/queue.php. composer run dev already runs queue:listen.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Add implements ShouldQueue to all classes in app/Notifications/.
- Set protected string $queue = 'notifications' on each notification class.
- Change InventoryRealtimeMessage from ShouldBroadcastNow to ShouldBroadcast.
- Configure retry behavior: $tries = 3 on notifications where appropriate.
- Update WorkflowNotificationsTest: use Queue::fake() to assert notifications are queued, or keep sync driver and assert delivery still works.
- Document queue worker requirement in README.md deployment section (reference PRODUCTION_READINESS_PLAN.md P1.7).
- Ensure NotificationService dispatch patterns remain unchanged from caller perspective.
- Verify useRealtimeNotifications.ts polling fallback still works when WebSocket is down.
- Run php artisan test --compact tests/Feature/Notifications/.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 6 — Forecasting Management Module (Dedicated UI)

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Forecasting Management Module (Dedicated UI)

Objective:
Build a dedicated forecasting management UI exposing the existing DemandForecaster backend to Admin and Supply Head users.

Context:
PUP PRISM already has ForecastProfile, ForecastSnapshot models, DemandForecaster service, GenerateDemandForecasts command (scheduled daily 01:30), ForecastWidget.vue and ProductForecastPanel.vue components, and forecast summary on Dashboard. There is no ForecastController and no pages under resources/js/pages/inventory/forecasting/. Nav is defined in resources/js/lib/inventoryNavigation.ts.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Create app/Http/Controllers/Inventory/ForecastController.php with:
  - index: paginated list of consumable products with latest ForecastSnapshot, filters (urgency, min confidence, method, search)
  - show: single product forecast detail with chart data, profile settings, confidence explanation
  - updateProfile: update ForecastProfile (method, lookback_days, lead_time_days, safety_stock_days, forecast_horizon_days)
- Add routes under /inventory/forecasting with role:Admin|Supply Head middleware.
- Create resources/js/pages/inventory/forecasting/Index.vue and Show.vue.
- Reuse ForecastWidget, ProductForecastPanel, Chart.js patterns from Dashboard.vue.
- Add nav item to inventoryNavigation.ts for authorized roles.
- Run php artisan wayfinder:generate after adding routes.
- Use Wayfinder imports in Vue pages.
- Add Pest tests in tests/Feature/Inventory/ForecastingPageTest.php: custodian forbidden; supply head can view index; profile update persists; index returns expected props.
- Run vendor/bin/pint --dirty --format agent, npm run build, php artisan test --compact --filter=Forecasting.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 7 — Forecast-Driven Procurement Extension

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Forecast-Driven Procurement Extension

Objective:
Close the loop from forecast_stockout alerts to draft purchase orders for products still above reorder threshold.

Context:
IMPORTANT — do NOT rebuild what already exists:
- app:generate-demand-forecasts already creates forecast_stockout InventoryAlert records via syncForecastAlerts() in GenerateDemandForecasts.php
- PurchaseOrderGenerator::resolveRecommendedQuantity() already uses ForecastSnapshot data
- PurchaseOrderController::generate() already calls generateFromAlerts()

The gap: generateFromAlerts() only includes products where on_hand_qty <= reorder_threshold. Forecast-urgent products ABOVE threshold are excluded from auto PO drafts.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Extend PurchaseOrderGenerator with generateFromForecastAlerts(User $requestedBy) targeting active forecast_stockout alerts where product on_hand_qty > reorder_threshold.
- Add ProcurementRecommendationNotification dispatched to Supply Head when new forecast_stockout alerts are created (hook in GenerateDemandForecasts::syncForecastAlerts).
- Add controller action and button for "Generate Draft POs from Forecasts".
- Do NOT create app:procurement-scan-forecasts command (redundant).
- Do NOT create new alert type — use existing forecast_stockout.
- Pest tests: generateFromForecastAlerts includes above-threshold forecast-urgent product; notification sent; skips product without supplier.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 8 — Notification Preferences & Smart Digests

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Notification Preferences & Smart Digests

Objective:
Allow users to control which notification events they receive on which channels, with optional daily email digests.

Context:
NotificationService in app/Services/NotificationService.php dispatches 7 notification types. AppNotificationMenu.vue shows real-time notifications. Settings pages exist under resources/js/pages/settings/. No per-user preference storage exists.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Migration notification_preferences: user_id, event_type, mail_enabled, database_enabled, broadcast_enabled, digest_frequency (instant|daily), unique(user_id, event_type).
- Create NotificationPreference model and NotificationPreferencePolicy (user owns record).
- Define event_type constants matching existing notification classes (requisition_submitted, booking_approved, low_stock, etc.).
- Create NotificationPreferenceController in Settings namespace.
- Create resources/js/pages/settings/NotificationPreferences.vue with toggle grid per event type and channel.
- Update NotificationService to check preferences before notify(); if no row exists, use role-based defaults.
- Seed default preferences on user create (Admin user management or observer).
- Create app:send-notification-digests command: daily mail digest for users with digest_frequency=daily per event type.
- Schedule digest command daily at 08:00 in routes/console.php.
- Pest tests: disabled mail channel prevents mail notification; digest command sends aggregated email; user can update preferences.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 9 — Dashboard Performance Caching Layer

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Dashboard Performance Caching Layer

Objective:
Cache DashboardStatsService aggregate query results to reduce database load on the dashboard landing page.

Context:
DashboardStatsService in app/Services/DashboardStatsService.php runs 15+ aggregate queries per request. DashboardController serves role-specific stats. InventoryService records stock movements. Config pattern exists for inventory settings. DASHBOARD_CACHE_ENABLED can be added to .env.example.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Add DASHBOARD_CACHE_ENABLED env (default true) and DASHBOARD_CACHE_TTL env (default 90 seconds).
- Wrap DashboardStatsService getAdminStats, getProcurementStats, getCustodianStats (if exists) outputs in Cache::remember when enabled.
- Cache key format: dashboard:{role}:{hash of date range from+to}.
- Invalidate cache tags or keys on: StockMovement created, Requisition status change, Booking status change, PurchaseOrder created/updated.
  - Implement via explicit Cache::forget calls in InventoryService or lightweight observer.
- Do NOT cache user-specific notifications (loaded via HandleInertiaRequests shared props).
- Add tests/Feature/DashboardCacheTest.php: second request uses cache; stock movement invalidates cache.
- Run vendor/bin/pint --dirty --format agent and php artisan test --compact --filter=Dashboard.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Prompt 10 — Observability, Scheduler Health & E2E in CI

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following feature:

Observability, Scheduler Health & E2E in CI

Objective:
Add production error tracking, scheduler/queue health visibility, Playwright E2E in GitHub Actions, and tests for uncovered commands and bulk routes.

Context:
bootstrap/app.php has empty withExceptions(). admin/health route exists in routes/web.php (role:Admin). Playwright tests in tests/e2e/ (7 suites) are not in .github/workflows/tests.yml. trash:cleanup command in app/Console/Commands/CleanupTrash.php has no test. Bulk routes on bookings/requisitions lack tests.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the feature using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, migrations, APIs, components, services, or utilities only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per Documentation Update Requirements at the top of AI_IMPLEMENTATION_PROMPTS.md.

Specific Feature Requirements:
- Add sentry/sentry-laravel as optional dependency; configure via SENTRY_LARAVEL_DSN in .env.example (empty default).
- Register Sentry in bootstrap/app.php or via package auto-discovery only when DSN is set.
- Extend admin/health JSON response with:
  - failed_jobs_count (from failed_jobs table)
  - queue_connection (config value)
  - scheduler_last_runs (map of command name => timestamp from cache)
- Update GenerateDemandForecasts, InventoryGenerateAlerts, CleanupTrash to Cache::put('scheduler:last_run:{command}', now()) on successful completion.
- Create tests/Feature/Console/CleanupTrashTest.php for trash:cleanup command.
- Add Pest tests for bulk-approve, bulk-reject, bulk-issue on requisitions and bulk-reject on bookings.
- Add GitHub Actions job (e2e.yml or extend tests.yml):
  - PHP 8.4, composer install, npm ci, npm run build
  - cp .env.testing .env, php artisan key:generate, php artisan migrate --force
  - php artisan db:seed --class=RoleSeeder (minimal)
  - Start php artisan serve in background
  - npx playwright install --with-deps chromium
  - npx playwright test
- Document SENTRY_LARAVEL_DSN and CI E2E in README.md.
- Run vendor/bin/pint --dirty --format agent.

Expected Output:
- Fully implemented feature (or clear partial delivery with remaining work listed)
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands run and results)
- Any assumptions made
- Documentation updates completed:
  - docs/system-improvement/IMPLEMENTATION_TRACKING.md — status, summary counts, completion log entry
  - docs/system-improvement/SYSTEM_IMPROVEMENT_ANALYSIS.md — Implementation Checklist row synced
  - docs/system-improvement/ACCURACY_REVIEW.md — only if relevant accuracy notes changed

Important:
- Do not mark this feature as DONE in code or docs unless fully implemented, tested, and verified.
- If incomplete: set status to IN PROGRESS or BLOCKED in IMPLEMENTATION_TRACKING.md and explain what remains or what blocks you.
- If partially done: list completed vs remaining sub-tasks in the Feature Completion Log.
- Never mark DONE without updating IMPLEMENTATION_TRACKING.md and SYSTEM_IMPROVEMENT_ANALYSIS.md checklist.
```

---

## Usage Notes

1. **One feature per agent session** — Implement and test one prompt at a time to avoid conflicts.
2. **Run tests after each feature** — `php artisan test --compact` plus `npm run build` for frontend changes.
3. **Always update documentation** — See [Documentation Update Requirements](#documentation-update-requirements-all-prompts) and [IMPLEMENTATION_TRACKING.md](./IMPLEMENTATION_TRACKING.md).
4. **Dependencies** — Feature 7 (Forecast-Driven Procurement) works best after Feature 6 (Forecasting UI) but can be implemented independently.
5. **Deployment** — Features 5 and 7 benefit from P1.7 queue worker from `PRODUCTION_READINESS_PLAN.md`.
