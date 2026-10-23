# Production Deployment Runbook

This runbook defines a repeatable deployment process for PUP PRISM. It does not assume a hosting provider and does not authorize a production deployment. Complete it first in an isolated staging environment using institution-approved infrastructure and secrets.

## 1. Runtime requirements

| Component | Requirement |
| --- | --- |
| PHP | 8.4 with the extensions required by `composer check-platform-reqs` |
| Web application | Laravel 13 served by PHP-FPM or an equivalent managed runtime |
| Database | PostgreSQL; SQLite is limited to the isolated E2E suite |
| Node.js | 22 or another version supported by the lockfile, used during asset builds |
| Queue | Supervised Laravel worker using the database or Redis driver |
| Scheduler | One `php artisan schedule:run` invocation every minute |
| Realtime | Laravel Reverb when realtime notifications are enabled |
| Storage | Persistent, private `storage/app` plus writable `storage` and `bootstrap/cache` |
| Transport | HTTPS with a valid certificate and HTTP-to-HTTPS redirect |

Confirm the exact release requirements instead of copying local workstation values:

```bash
php --version
composer --version
composer check-platform-reqs
node --version
npm --version
php artisan about --only=environment
```

## 2. Required configuration

Store real values in an approved secret manager or deployment environment. Never commit the production `.env` file.

| Area | Production expectation |
| --- | --- |
| Application | `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, unique `APP_KEY` |
| Database | `DB_CONNECTION=pgsql` and least-privilege credentials |
| Sessions | `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, HTTP-only cookie, `lax` or `strict` SameSite policy |
| Accounts | `REGISTRATION_ENABLED=false` unless the institution explicitly approves self-registration |
| Queue | Persistent `database` or `redis` connection; never `sync` for normal production operation |
| Cache | Database or Redis; never the `array` store |
| Mail | Approved delivery transport and verified sender; never `log` or `array` |
| Files | Private persistent disk for attachments; do not expose `storage/app/private` through a public symlink |
| Reverb | Unique application ID, key, and secret; TLS at the proxy or Reverb endpoint |
| Monitoring | Sentry or an approved centralized error/logging service |

Run the non-secret configuration check after installing dependencies and building assets:

```bash
php artisan app:production-readiness
php artisan app:production-readiness --json
```

A successful command means no checked configuration blocker was detected. It does not prove that external services, workers, cron, backups, DNS, or TLS are operational.

## 3. Pre-deployment checks

1. Approve a release commit or tag and review its complete diff.
2. Confirm CI, dependency audits, and staging browser tests.
3. Create and verify a PostgreSQL backup and a matching private-file backup.
4. Record any migration that is not safely reversible.
5. Confirm queue depth and allow active jobs to finish when required.
6. Confirm a rollback or forward-fix owner is available.

Do not continue when high-severity exploitable dependency findings, failed migrations, authorization failures, or data-integrity test failures remain unresolved.

## 4. Deployment procedure

Commands below are examples for a release directory. Adapt process supervision and paths to the approved platform.

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan app:production-readiness
php artisan down --retry=60 --refresh=15
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Use `php artisan migrate --pretend` during release review when SQL visibility is useful. The pretend output is not a substitute for testing the migration against a representative PostgreSQL staging database.

The service worker uses an explicit public-shell allowlist. Deploy `sw.js`, `offline.html`, the web manifest, icons, and compiled assets in the same release. Existing clients receive an update notice and choose when to refresh; PRISM does not cache authenticated Inertia pages.

## 5. Long-running processes

### Queue worker

Run under Supervisor, systemd, Laravel Cloud, or an equivalent process manager:

```bash
php artisan queue:work --queue=default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600
```

See [QUEUE_OPERATIONS.md](./QUEUE_OPERATIONS.md) for retry and failed-job procedures.

### Scheduler

Configure one invocation per minute:

```cron
* * * * * cd /var/www/pup_prism && php artisan schedule:run >> /dev/null 2>&1
```

Verify registration with `php artisan schedule:list`, then verify real execution through scheduler heartbeats in the authorized operations-health page.

### Reverb

If realtime delivery is enabled, supervise the Reverb process and place it behind the approved TLS proxy:

```bash
php artisan reverb:start
```

The public Reverb key may be included in the browser bundle. The Reverb secret must remain server-side.

## 6. Post-deployment verification

Perform these checks using dedicated staging or approved production test records:

1. `GET /up` returns a successful public availability response.
2. An authorized Admin can open the protected operations-health page.
3. Login, logout, email verification, and privileged-user 2FA work.
4. Role-restricted pages reject unauthorized users.
5. A representative product search and read-only report load correctly.
6. A controlled requisition, receiving, and stock-lot workflow preserves quantities.
7. A test queued notification is processed once.
8. Scheduler heartbeats advance after their expected run.
9. Private attachments cannot be fetched by guessed public URLs.
10. The PWA manifest, offline page, and update notice load over HTTPS.

Inspect application, web-server, worker, Reverb, database, and browser logs during the checks.

## 7. Failure and rollback

- If code fails before a migration, keep maintenance mode active and redeploy the previous release.
- If a forward-compatible migration succeeded, prefer a reviewed forward fix. Do not assume `migrate:rollback` is safe.
- If a destructive or incompatible migration corrupted data, restore the verified backup into a new database and follow [BACKUP_DISASTER_RECOVERY_RUNBOOK.md](./BACKUP_DISASTER_RECOVERY_RUNBOOK.md).
- Restart workers after restoring the previous code so they do not execute stale classes.
- Run `php artisan optimize:clear` and rebuild caches for the selected release.
- Record the incident, affected release, recovery action, and validation evidence.

## 8. External integrations

No undocumented university identity provider, student information system, document platform, or external reporting API is considered connected. Those integrations require approved specifications, credentials, data-sharing rules, timeouts, bounded retries, audit requirements, and staging verification before implementation.

Existing supported integration surfaces are Laravel Sanctum APIs, the configured mail transport, private file storage, Reverb, Sentry, CSV/PDF exports, and QR-assisted browser workflows.

## 9. Release decision

Code readiness and deployment readiness are separate decisions. A release is ready for controlled deployment only after:

- application quality gates pass;
- dependency risks are accepted or remediated;
- staging uses production-equivalent PostgreSQL and services;
- backup and restore evidence is current;
- monitoring, queue, scheduler, mail, storage, TLS, and Reverb are verified; and
- the institution approves the deployment window and recovery objectives.

