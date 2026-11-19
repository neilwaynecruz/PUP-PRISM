# Windows single-laptop installation

This package separates the professor's daily use from developer maintenance. It targets Windows 10/11, PowerShell 7.2+, PHP 8.4+, and the existing Laravel application. PostgreSQL remains the supported business database. It does not install Windows, PHP, PostgreSQL, certificates, firewall rules, or third-party services. No dependency versions are changed.

## Professor: daily use

After developer setup, double-click **Start PUP-PRISM.cmd** in the installation folder, or a shortcut to that file. It checks the installation, starts one web, queue, and scheduler runner, waits for readiness, and opens the browser. Running it again does not deliberately start another copy.

Keep the laptop awake while working. Closing the browser does not stop the application. Background execution before Windows sign-in, operation while asleep, and automatic recovery from Windows shutdown are not promised. Network access from phones is deliberately excluded.

Inventory data stays on this laptop. Resend requires internet; email links using `localhost` must be opened on this same laptop. A phone's `localhost` is the phone, not the professor's laptop. Never change signed URLs to work around this.

## Developer: initial provisioning

1. Install compatible PHP, PostgreSQL, PowerShell 7.2+, Composer, and Node/npm. Node is required for builds, not the packaged PHP runners. Start the intended PostgreSQL service and provision an **empty, dedicated database** for a new installation. Do not reuse development credentials or data by copying them blindly.
2. Obtain the approved source release. In that installation run `composer install --no-interaction --prefer-dist` and `npm ci`. The lockfiles remain authoritative. Production dependency exclusions require a separately validated release build.
3. Create `.env` **only if it does not exist**. Configure the database, approved Resend key and sender, `APP_DEBUG=false`, `APP_URL=http://localhost:8000`, `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=90`, `CACHE_STORE=file` (or database), and `RETENTION_SCHEDULING_ENABLED=false`. Set `INERTIA_SSR_ENABLED=false` for this non-SSR local package. Use an appropriate persistent session driver. Never paste keys into support reports.
4. Generate an `APP_KEY` **only for a new installation with no existing key**. Never regenerate it when updating or restoring an installation.
5. Review migrations and back up any existing target records before applying them. Run the reviewed `php artisan migrate --force --no-interaction` against the confirmed target. Never use `migrate:fresh`, `db:wipe`, demo seeders, or `composer run setup` on active inventory.
6. Build assets using `npm run build`. Do not leave `public/hot` pointing at a developer Vite server. Configure/verify any Reverb build settings separately; this package does not supervise Reverb or claim real-time WebSocket verification.
7. Configure the detected PHP CLI and selected stable local port:

```powershell
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action Configure -Port 8000
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action Check
```

If PHP is not on PATH, append `-PhpPath 'C:\path to PHP\php.exe'` to Configure. The script locates the application relative to itself, not the developer's machine name. A different port requires matching `.env` `APP_URL`. Do not silently switch ports: old emailed links depend on the original address.

School Windows policy may block PowerShell scripts. Have IT approve/sign the reviewed scripts as required; the launcher does not bypass execution policy or weaken machine security settings.

The check is read-only: it verifies boot prerequisites, built assets, URL, database connection and required tables, migration status, queue retry window, mail configuration, and cleanup policy. It reports failed check names, not credentials. A Resend key being present does **not** prove provider acceptance or inbox receipt.

This lightweight same-laptop runtime uses Laravel's loopback development server. It is not an internet-facing or multi-user production web-server deployment. HTTP localhost is limited to the device; remote use requires a separate HTTPS design. Do not disable browser security, expose the server on `0.0.0.0`, or mark public Wi-Fi trusted to extend this package.

## Startup and supervision

```powershell
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action Start
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action InstallStartup
```

InstallStartup explicitly registers three path-specific tasks for the **current Windows user at sign-in**, with limited privileges and no stored password. Windows policy may require administrator assistance; an access-denied result is not a successful installation. Tasks are not started by registration. Other professor accounts must be configured under the correct account. Never copy the private local configuration from the developer's installation.

Each component has an installation-specific named mutex. Runners retry failed children with a short bounded backoff and stop after five child exits. Task Scheduler can restart failed runners; inspect logs if a component keeps failing rather than endlessly restarting it. PostgreSQL must be configured separately as an automatic Windows service. Database or configuration failures stop startup rather than automatically creating or changing records.

The queue reuses `queue:listen` for `security,mail,default,notifications,broadcast`, three ordinary attempts, a 60-second subprocess limit, and a retry-after greater than 60 seconds. Native Windows lacks PCNTL; `queue:work --timeout` alone is not sufficient. Notification-specific deadlines and retry safeguards remain in application code. Long PDF reports can exceed this listener limit; measure them and use a reviewed worker/retry-window design rather than blindly raising limits.

The scheduler can generate forecasts, alerts, and email digests. **Starting the package can therefore process real queued mail.** Review recipients/configuration before first activation. Scheduled times use Laravel's configured application timezone, not automatically the Windows clock timezone; review that policy for the school before activation. Startup does not test or claim email delivery.

Destructive scheduled cleanup is **off by default**. `RETENTION_SCHEDULING_ENABLED=true` explicitly enables all existing scheduled pruning and 30-day trash cleanup in Laravel, but this safety-first local launcher deliberately rejects that setting. Activating retention later requires approval and a reviewed change to the launcher policy. Manual cleanup commands are not disabled by this flag. Expired-email send prevention is independent of cleanup and remains enforced. If a previous installation caches configuration, a developer must review and clear/rebuild that cache; the launcher rejects cached configuration rather than silently overriding it.

Runtime configuration and last-attempt child logs are under `storage/app/private/local-runtime`, restricted to the configuring Windows account and SYSTEM. Treat Laravel and runtime logs as potentially sensitive. There is no application status page exposed without authentication. A passing startup check is not a substitute for workflow tests.

## Stop and remove startup

```powershell
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action Stop
pwsh -NoProfile -File resources/windows/Prism.ps1 -Action RemoveStartup
```

Finish user transactions and drain queues first. Stop requests terminate only the runner's owned PHP process trees, but can interrupt an active job; it is not a graceful deployment mechanism. Wait until Check reports runners false before restarting or moving the installation. Do not kill all PHP processes. RemoveStartup verifies the task's action before unregistering; it does not stop already running components or delete inventory records.

## Controlled repository updates

Updates intentionally remain developer-operated, not a professor button that blindly runs Git/migrations:

1. Review the approved release and current `git status`; stop if legitimate local changes overlap. Never reset or discard them automatically.
2. Arrange downtime, finish transactions, drain queues, and stop the local runners. Do not rebuild underneath a running browser workflow.
3. Back up PostgreSQL in custom format plus `.env`, private uploads, and deployment configuration into restricted storage. Verify the archive listing and periodically restore into a separate database. Copying Git is not a database backup.
4. Obtain the reviewed release without overwriting machine configuration. Use `composer install` and `npm ci`, not dependency updates. Review pending migrations and their data implications before applying them. Build assets with `npm run build`.
5. Run focused tests using isolated databases, then Check, Start, and authorized workflow smoke checks. Retain a recovery owner and backup; do not blindly roll back destructive migrations.

Automatic backup/restore, dependency installation, migration execution, and repository updates are **not** implemented by the startup script. These remain explicit developer tasks to avoid unintended changes to the professor's inventory.

## Verification

```powershell
php artisan test --compact tests/Feature/Console/LocalRuntimeCheckTest.php
pwsh -NoProfile -File tests/windows/PortableRuntime.Tests.ps1
```

The Windows integration test builds a separate application copy in a path containing spaces, uses a new SQLite database and `MAIL_MAILER=array`, and never copies the source `.env`, private uploads, or active PostgreSQL data. It validates configuration, readiness, duplicate starts, real synthetic queue-job processing, server crash recovery, default-off cleanup, environment preservation, occupied-port rejection, and stopping its own processes. Task registration/removal and partial-registration rollback use function doubles; no actual Windows tasks are installed by the test. The retained synthetic fixture is not the professor's actual laptop, a physical reboot test, PostgreSQL installation verification, or evidence of real Resend delivery.

### November 19, 2026 change note

Added the portable local runner and preflight; made destructive scheduled retention explicitly opt-in. Previous queue/security work remains uncommitted and preserved. See the email reliability report for executed results and remaining limitations.
