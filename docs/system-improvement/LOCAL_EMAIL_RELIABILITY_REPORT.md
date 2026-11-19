# Local email reliability — PUP-PRISM

Date: November 17, 2026 (Asia/Manila)

## Verdict

**PARTIALLY VERIFIED.** Code-level delivery safeguards and relevant regression checks pass. Live Resend acceptance, inbox receipt, the final trusted internal HTTPS hostname, physical LAN-device link access, production worker startup/restart, and PostgreSQL deployment are not verified. No real email was sent; no active business database was migrated, reset, or reseeded. Nothing was committed, pushed, merged, or deployed.

## Baseline and decisions

- Existing database queue connections already use after-commit dispatch. Fortify verification and reset notifications were synchronous; they now have queued subclasses without changing the native verification/reset routes, signatures, tokens, or controllers.
- Several operational emails used relative application links. Email action links are now absolute; in-app notification URLs remain relative.
- Handover mail previously followed ordinary mute/digest preferences and could send an obsolete token after a resend or cancellation. Required handover mail is now immediate, encrypted in the queue, and checked against the current pending handover and token hash. Optional in-app/broadcast preferences remain respected. Handovers are excluded from ordinary daily digests.
- Installed Resend SDK supports idempotency, but Laravel's installed Resend transport does not supply the HTTP key. A local PSR HTTP wrapper adds it without modifying vendor files or dependencies.
- Removed the default Resend-to-log failover member. Log/array mailers remain available for development/testing but are not evidence of delivery. The readiness regression explicitly continues rejecting composite mailers containing log transport.
- Preserve existing recipient authorization, verified-email requirements, signatures, custody updates, cancellation, receipt generation, and audit rules. Recipient-only in-app acceptance is deferred; opening an email link still does not finalize a handover. TOTP/recovery-code behavior is unchanged.

## Implemented behavior

### Queueing, retries, and duplicate prevention

- Security mail uses `security`; other mail uses `mail`; database and broadcast notification channels remain independent.
- Notifications dispatch after commit. Tests check that rollbacks enqueue nothing and committed transactions create encrypted security jobs.
- Delivery metadata has a stable recipient/message identity, indexed status/expiry, attempt count, optional provider ID, sanitized failure category, and next-attempt time.
- An atomic cache lock prevents concurrent processing when workers share the same cache. A database primary key protects delivery identity. Use a shared database/Redis cache for multiple workers/hosts; per-process array caches are test-only.
- Freeze the exact first Resend request bytes, encrypt them with Laravel's application key, and replay them with the same HTTP `Idempotency-Key`. This includes necessary transient message content; it is not exposed by model serialization or Operations Health. Tokens are not placed in application diagnostic messages.
- Provider idempotency is limited to 24 hours. The ordinary-mail deadline is 23 hours from notification creation, not from each retry. Verification/reset deadlines are shorter: configured token lifetime minus one minute. Expiry is never extended by retrying.
- Mail middleware allows at most five job attempts; transport attempts are also bounded. Backoff is 60, 300, 900, then 1800 seconds, honoring a larger valid Retry-After only within the deadline. Queue `$tries=0` delegates to the fixed retry deadline; it does **not** mean unlimited mail attempts because middleware and delivery checks enforce the bounds.
- Authentication/request rejection and idempotency mismatch stop; network failures, 408, rate limiting, provider failures, and concurrent-idempotent conflicts can retry. Application defects fail the job rather than being treated as temporary delivery success.
- Accepted messages are not sent again. Explicitly requesting a fresh resend creates a new message identity. Rotating a handover token invalidates the older queued mail.

### Truthful state and retention

- Operations Health displays Queued, Processing, Accepted by Resend, Retry scheduled, Failed, Expired, Superseded, and Cancelled-before-transport counts. Only administrators retain access to this page.
- Acceptance requires the Resend provider ID. A missing provider receipt is a failure, not inbox delivery. A canceled mail event is not acceptance.
- No Delivered/Bounced state is invented. No webhook or outbound delivery-status polling was added; those would require additional design/authorization.
- Verification/reset screens and handover feedback describe queued requests rather than claiming inbox delivery. Under log/array testing configuration, queued feedback does not imply a real email was sent.
- Payloads are cleared after acceptance, permanent/exhausted failure, cancellation, supersession, or expiry. Hourly scheduled cleanup clears expired payloads and marks abandoned pending records expired; metadata is removed 30 days after expiry. Scheduler downtime delays physical cleanup. Failed-job retention remains governed by existing settings; encrypted auth/handover job payloads may remain in that restricted store until pruned. Backups also need an institutional retention policy.
- Queue-event metadata initialization failure is reported safely without undoing an already queued job; the worker retries tracking initialization when processing. This is not a transactional outbox or a guarantee against every queue-infrastructure failure.

### Security safeguards

- Verification URLs are fixed when requested, remain signed, and are suppressed if expired, already verified, inactive, or the email address changed.
- Reset notifications retain the broker token and reject superseded/used tokens before sending. Existing throttling and password-reset validation remain in place.
- Handover notifications reject inactive recipients, different recipients, completed/cancelled handovers, and mismatched current token hashes.
- No security bypass, automatic verification, administrative ownership override, secret settings page, dependency change, or external integration was introduced.

## Activation requirements — not executed on the active database

1. Review/back up the target database and apply the new `2026_11_17_013741_create_email_deliveries_table` migration through the normal controlled deployment procedure. It adds a table; no existing business records need rewriting. PostgreSQL-specific migration/integration execution remains outstanding.
2. Configure `MAIL_MAILER=resend`, a valid sender/domain and server-side API key, a persistent queue, and the approved internal HTTPS `APP_URL`. This implementation's tracking/idempotency applies to the **default Resend mailer**, not arbitrary explicit/composite transports. Application notifications do not override that mailer.
3. Ensure every permitted LAN client can resolve and trust the internal hostname/certificate. Recipients outside the permitted LAN/VPN cannot follow private links. No hostname, DNS, certificate, firewall, or service configuration was changed here.
4. Restart workers after releasing the code. Existing active runbooks, development command, and health guidance now include:

   ```sh
   php artisan queue:work --queue=security,mail,default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600
   ```

   For dedicated security capacity, use a separately supervised `--queue=security` worker and another `--queue=mail,default,notifications,broadcast` worker. This is a recommendation, not an installed service. Job timeout is 30 seconds; Resend HTTP/connect limits are 15/5 seconds; persistent queue retry_after must remain greater than job timeout (existing database default: 90 seconds).
5. Keep the scheduler running, monitor failed jobs/Operations Health, and maintain accurate server/device clocks for signed links and TOTP. No active worker was started here because that could send pending real mail.
6. Do not blindly replay expired or superseded failed jobs. Request a fresh verification/reset/handover email through its existing throttled, authorized workflow. A permanent/exhausted ledger entry remains terminal; `queue:retry` does not silently authorize a new send.

## Actual verification

All PHP tests used isolated test data. Resend HTTP was mocked; no internet email delivery occurred.

| Check | Result |
| --- | --- |
| New EmailReliabilityTest | 19 passed, 98 assertions |
| Combined affected PHP regression command below | 156 passed, 733 assertions, 27.05 seconds |
| PHPStan (`php vendor/bin/phpstan analyse --memory-limit=1G --no-progress`) | Passed; no errors |
| `php vendor/bin/pint --dirty --format agent` | Passed with formatting applied; initial sandbox write failure required a permitted rerun |
| `npm run type-check` | Passed |
| `npm run lint:check` | Passed after build/Wayfinder generation settled; initial sandbox directory permission error and transient unresolved-route import-order findings were not hidden |
| `npm run format:check` | Passed |
| `npm run build:e2e` | Passed, 47.02 seconds |
| `npm run build` | Passed; normal assets restored after browser runs |
| Chromium handover E2E | 1 passed, 42.4 seconds including setup |
| Firefox + WebKit handover E2E | 2 passed, 1.4 minutes including setup; retries disabled |
| Independent read-only implementation review | No remaining critical/high blocker identified in this change |
| Test sensitivity experiment | Inverting permanent/transient classification made 4 targeted cases fail; condition restored and affected tests rerun |
| Full application Pest / full browser suite | Not rerun; affected regression scope run instead |
| Live provider/inbox/LAN-device verification | Not performed; approved recipient and reachable trusted endpoint required |

Combined PHP command:

```sh
php artisan test --compact tests/Feature/Auth tests/Feature/Notifications tests/Feature/Settings/NotificationPreferenceTest.php tests/Feature/Inventory/HandoverVerificationTest.php tests/Feature/Inventory/HandoverWorkflowTest.php tests/Feature/Admin/SystemHealthServiceTest.php tests/Feature/Admin/OperationsHealthPageTest.php tests/Feature/PerformanceOptimizationTest.php tests/Feature/Console/PruneOperationalDataTest.php tests/Feature/Console/ProductionReadinessCheckTest.php
```

Browser commands:

```sh
npx playwright test tests/e2e/handover.spec.ts --project=chromium --retries=0
npx playwright test tests/e2e/handover.spec.ts --project=firefox --project=webkit --retries=0
```

Browser coverage is desktop emulation (1280×900), not physical-device verification. Tests cover the existing signature and receipt workflow; they do not prove live email or independent mobile/light/dark visual coverage. No design-system/layout changes were made. Existing accessible status announcements and theme styles were retained.

Build warnings remain for the existing approximately 500 kB PDF chunk and plugin build timings. They are not email-delivery failures and were not suppressed. Node SQLite/NO_COLOR warnings were informational.

## Change inventory and readiness

- Added EmailDelivery model/factory/migration, reliable notification trait, queued verification/reset subclasses, delivery service, Resend HTTP wrapper, mail channel/middleware/exception, and focused tests.
- Updated User dispatch, existing mail notifications, handover queue feedback, provider bindings, Operations Health, queue guidance, retention command/schedule, digest filtering, mail failover config, development worker command, auth feedback copy, and corresponding regression fixtures.
- Updated README and current queue/deployment/Redis runbooks. Historical Phase 5 reporting is archival and was not rewritten.
- Vite/Wayfinder regenerated source-line comments in tracked route/action files; no frontend application route contracts were intentionally changed.
- Existing email work at the beginning of this continuation was preserved and completed incrementally. Working tree remains uncommitted on `main`; no staging or repository history changes were performed.

Ready for code review and controlled activation, **not production sign-off**. Before declaring live operation verified: apply the reviewed migration, confirm worker/scheduler supervision and shared locking, approve a test recipient, verify actual inbox receipt and internal link access from another permitted device, and check failure/recovery with the institution's final infrastructure. Do not provide API keys or passwords in chat.

### Final working-tree snapshot

Branch: `main`. `git diff --check` passed. ` M` means modified and unstaged; `??` means new and untracked. Generated route/action entries include source-comment/line-ending churn from the normal build, not intentional API changes.

```text
 M README.md
 M app/Console/Commands/PruneOperationalData.php
 M app/Console/Commands/SendNotificationDigests.php
 M app/Http/Controllers/Inventory/HandoverController.php
 M app/Models/User.php
 M app/Notifications/BookingStatusChangedNotification.php
 M app/Notifications/BookingSubmittedNotification.php
 M app/Notifications/DailyNotificationDigest.php
 M app/Notifications/ExpiringStockAlertNotification.php
 M app/Notifications/HandoverVerificationNotification.php
 M app/Notifications/LowStockAlertNotification.php
 M app/Notifications/ProcurementRecommendationNotification.php
 M app/Notifications/PurchaseOrderSentNotification.php
 M app/Notifications/RequisitionStatusChangedNotification.php
 M app/Notifications/RequisitionSubmittedNotification.php
 M app/Providers/AppServiceProvider.php
 M app/Services/Operations/OperationalDataRetentionService.php
 M app/Services/Operations/QueueOperationsService.php
 M app/Services/Operations/SystemHealthService.php
 M composer.json
 M config/mail.php
 M docs/operations/PRODUCTION_DEPLOYMENT_RUNBOOK.md
 M docs/operations/QUEUE_OPERATIONS.md
 M docs/operations/REDIS_PRODUCTION_MIGRATION.md
 M resources/js/actions/App/Http/Controllers/Admin/AlertsController.ts
 M resources/js/actions/App/Http/Controllers/Admin/UserManagementController.ts
 M resources/js/actions/App/Http/Controllers/Api/RequisitionController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/AuditLogController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/BookingController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/ForecastController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/HandoverController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/InventoryReportController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/ProductController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/PurchaseOrderController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/RequisitionController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/StockMovementController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/SupplierController.ts
 M resources/js/actions/App/Http/Controllers/Inventory/TrashController.ts
 M resources/js/pages/auth/ForgotPassword.vue
 M resources/js/pages/auth/VerifyEmail.vue
 M resources/js/routes/admin/alerts/index.ts
 M resources/js/routes/admin/users/index.ts
 M resources/js/routes/api/requisitions/index.ts
 M resources/js/routes/inventory/audit-logs/index.ts
 M resources/js/routes/inventory/bookings/index.ts
 M resources/js/routes/inventory/forecasting/index.ts
 M resources/js/routes/inventory/forecasting/profile/index.ts
 M resources/js/routes/inventory/handover/index.ts
 M resources/js/routes/inventory/handover/verify/index.ts
 M resources/js/routes/inventory/index.ts
 M resources/js/routes/inventory/movements/index.ts
 M resources/js/routes/inventory/products/index.ts
 M resources/js/routes/inventory/purchase-orders/index.ts
 M resources/js/routes/inventory/reports/generated/index.ts
 M resources/js/routes/inventory/reports/index.ts
 M resources/js/routes/inventory/requisitions/index.ts
 M resources/js/routes/inventory/suppliers/index.ts
 M routes/console.php
 M tests/Feature/Admin/SystemHealthServiceTest.php
 M tests/Feature/Auth/PasswordResetTest.php
 M tests/Feature/Auth/VerificationNotificationTest.php
 M tests/Feature/Console/ProductionReadinessCheckTest.php
 M tests/Feature/Notifications/WorkflowNotificationsTest.php
?? app/Models/EmailDelivery.php
?? app/Notifications/Concerns/HasReliableMailDelivery.php
?? app/Notifications/QueuedResetPassword.php
?? app/Notifications/QueuedVerifyEmail.php
?? app/Services/Operations/EmailDeliveryException.php
?? app/Services/Operations/EmailDeliveryService.php
?? app/Services/Operations/NotificationMailMiddleware.php
?? app/Services/Operations/ReliableMailChannel.php
?? app/Services/Operations/ResendRequestClient.php
?? database/factories/EmailDeliveryFactory.php
?? database/migrations/2026_11_17_013741_create_email_deliveries_table.php
?? docs/system-improvement/LOCAL_EMAIL_RELIABILITY_REPORT.md
?? tests/Feature/Notifications/EmailReliabilityTest.php
```
# LAN HTTPS activation checkpoint — November 19, 2026

Status: **PARTIALLY VERIFIED**. This checkpoint does not replace the earlier automated-test results or claim physical-device verification.

- The email-delivery migration was already recorded as run during read-only preflight; it was not reapplied.
- Created a restricted custom-format PostgreSQL backup before HTTPS activation: `storage/app/private/lan-https-20261119/pup_prism-before-https.dump` (352,243 bytes). `pg_restore --list` succeeded with 513 listing entries. SHA-256: `73CFE071E4C9712AAAFBC0F96091639606EC49470A8216D897E8FA2BE5EB2CC5`. This verifies archive readability, not a restoration drill.
- Prepared a temporary HTTPS proxy bound only to `192.168.1.9:8443`, forwarding to the current application's loopback-only backend at `127.0.0.1:8020`. No dependencies, main `.env`, Laragon certificates, or trust stores were changed.
- Operational files, encrypted private keys, passphrase, logs, and backup are in the Git-ignored private directory with restricted Windows ACLs. Share only `PUP-PRISM-LAN-Root.cer`, never the directory or private files.
- Leaf certificate is valid November 19, 2026 through February 17, 2027. Public root SHA-256 fingerprint: `5C:13:F6:F6:88:91:B0:7D:21:02:C0:2C:02:92:05:A4:31:45:12:AF:8D:D1:3F:19:C5:2A:BC:EF:5A:C7:0B:FB`.
- Verified `openssl verify` for the LAN IP and `node --check proxy.mjs`. HTTPS requests using the explicit CA (without disabling TLS verification) returned `/up` 200, `/login` 200, a private-key path 404, an unapproved Host header 403, and the public root endpoint 200. Login session cookie is Secure and HttpOnly; the CSRF cookie is Secure and intentionally readable by JavaScript. No HTTP asset URLs were found in login HTML; this is not a complete browser/runtime audit.
- Temporary startup overrides disable debug and SSR, enable secure cookies with a separate session-cookie name, and trust only the loopback proxy. The proxy replaces forwarded headers and restricts incoming source addresses to `192.168.1.0/24`.
- Startup process IDs observed: backend launcher 24468, PHP listener 27824, Node proxy 29992. These are temporary processes, not registered startup services. Verify process identity before stopping them; IDs can be reused.
- Firewall creation failed with Windows **Access is denied**. No firewall rule was created. Intended rule allows only the Node executable, TCP 8443, local address `192.168.1.9`, remote subnet `192.168.1.0/24`, Private profile, with edge traversal blocked. User/admin action remains required. Wi-Fi remains Public; do not change it unless the network is trusted.
- Physical iPhone/iPad certificate installation, explicit root trust, LAN reachability, camera behavior, printing, and email-link opening remain unverified. IP changes require updating the endpoint and certificate. No WebSocket proxy is configured; real-time WSS functionality is not verified by this setup.
- No test email, mail/security worker, or general scheduler was started. Do not start general workers merely for this diagnostic: they may send unrelated queued messages. Approved real-email recipient remains `cruzneilwayne@gmail.com`, pending device HTTPS confirmation and a scoped delivery check.
- Existing uncommitted application work remains preserved. `git diff --check` was rerun at this checkpoint; no commit, push, migration execution, reseed, or deployment was performed.

## Portable same-laptop runtime — November 19, 2026

**Package implemented and tested; school-laptop deployment remains PARTIALLY VERIFIED.** This replaces the need to assume the developer and professor share one machine or LAN address. It does not convert the temporary HTTPS endpoint above into a permanent deployment.

### Changes

- Added `Start PUP-PRISM.cmd` and `resources/windows/Prism.ps1`: explicit Configure/Check/Start/Stop, component supervision, and opt-in current-user sign-in task installation/removal. Paths are resolved relative to the package, with local PHP path/port stored privately. No laptop name, LAN IP, password, or Resend key is embedded.
- Added `app:local-runtime-check` for read-only, non-secret readiness checks: URL, debug/SSR, required tables/migrations, persistent queue/cache, assets/public entry point, mail configuration, and retry-window safety. No provisioning, key generation, migration, or inventory mutation is performed by the launcher.
- Reused `queue:listen` with a 60-second subprocess limit, below the configured queue retry window. Native Windows PCNTL absence is not hidden by relying on ineffective worker signal timeouts.
- Added `RETENTION_SCHEDULING_ENABLED=false` default. Scheduled email pruning, trash deletion, and operational retention are not registered until explicitly enabled. Heartbeat expectations exclude disabled cleanup, avoiding misleading overdue warnings. The local package deliberately refuses destructive cleanup being enabled; activation later needs an approved policy change.
- Added developer provisioning/update guidance in `docs/operations/WINDOWS_LOCAL_INSTALLATION.md` and linked it from README. Backups, migrations, dependency installation, release updates, and restoration remain explicit developer-controlled tasks, not an unsafe automatic updater.

### Executed verification

1. `php artisan test --compact tests/Feature/Console/LocalRuntimeCheckTest.php tests/Feature/Console/PruneOperationalDataTest.php tests/Feature/Console/CleanupTrashTest.php tests/Feature/Admin/SystemHealthServiceTest.php tests/Feature/Notifications/EmailReliabilityTest.php tests/Feature/Auth/VerificationNotificationTest.php tests/Feature/Auth/PasswordResetTest.php`: **50 passed, 204 assertions**, final run **14.14 seconds**. Tests used SQLite in memory and non-sending mail, not the active PostgreSQL database.
2. `pwsh -NoProfile -File tests/windows/PortableRuntime.Tests.ps1 -ReuseFixture <generated synthetic fixture>`: **27 checks passed**. Initial fixture creation used the same script without `-ReuseFixture`. The separate copy is under `storage/framework/testing/portable installation 5ca1c061d0804e2a80668d0bec3c56b1`, intentionally a different path containing spaces. It uses a dedicated SQLite file, a new test application key, and `MAIL_MAILER=array`; the active `.env`, uploads, and PostgreSQL business records were not copied.
3. Integration checks covered real login HTML, all three component runners, a harmless queued job writing a private fixture marker, repeated Start without duplicate runners, recovery after terminating only the test web child, missing-PHP readiness/stop behavior, occupied-port rejection before workers start, environment/key preservation, simulated sign-in resume after a stop, and stopping owned processes.
4. Task-registration tests use function doubles for OS cmdlets. They verify current-user limited-privilege actions, quoted portable paths, IgnoreNew/restart settings, ownership-checked removal, and rollback of a partially failed registration. **No actual Windows startup tasks were installed by the tests.** A simulated resume is not a physical reboot/sign-in verification.
5. `composer phpstan`: **pass**, no errors across 360 application files. A redundant PHP-version comparison was moved to the PowerShell provisioning boundary rather than suppressing the analyzer.
6. `php vendor/bin/pint --dirty --format agent`: final result **pass**. Three PowerShell scripts also passed AST parsing. `git diff --check` passed before the final documentation-only update and was rechecked afterward.
7. A deliberate temporary inversion of the cleanup-registration condition caused both opt-in/opt-out regression cases to fail. The correct condition was restored, then the affected regression group passed. No scheduler was run against business data during this mutation check.

### Failures investigated and corrected

- Background processes inherited output handles and kept the calling test attached. Windows shell-detached runners with restricted transcripts removed this coupling; later integration runs return normally.
- Synthetic fixture provisioning initially omitted `public/index.php`. The fixture now includes public entry files, and readiness requires an actual entry point and the expected health content instead of accepting any HTTP 200.
- Loopback probing via `localhost` timed out in this environment. Readiness now probes explicit IPv4 loopback with proxy bypass while retaining the stable configured `localhost` user/email URL.
- A test-harness script-scope error was corrected in the isolated child process; no real task registration was involved. These earlier failures are not counted as successful runs.

### Limits and final state

- This is a Windows/PowerShell 7.2+ same-laptop package using Laravel's loopback development server, not a verified internet-facing/multi-user production web-server installation. Other operating systems are not certified.
- PostgreSQL service provisioning, a real Windows reboot/sign-in, actual Task Scheduler permissions/registration, the professor's physical laptop, and real inbox/link receipt still require handover checks. Synthetic SQLite verification does not substitute for PostgreSQL or physical-device testing.
- Reverb supervision, browser/WebSocket verification, phone access, full browser regression, full Pest suite, and automatic backup/update/restore are not covered by this package's tests. No frontend source/build changes were needed for this task; existing compiled assets were used in the fixture.
- The Stop action is for idle/drained installations and may interrupt active jobs. Do not use it as a graceful zero-downtime deployment mechanism. Configure PostgreSQL auto-start separately and review scheduler timezone/notification recipients before real activation.
- No real Resend email was sent, no active mail worker or scheduler was started, no school network/firewall/trust configuration was changed, and no active business database was migrated or reseeded. Synthetic test processes were stopped; the fixture is retained in a Git-ignored directory for inspection.
- Existing uncommitted work remains present on `main`. New package files, command, test fixtures, regression tests, and documentation remain uncommitted. No dependencies were changed, and nothing was committed, pushed, merged, or deployed. Ready for focused code review and controlled handover testing, not an unconditional production sign-off.
