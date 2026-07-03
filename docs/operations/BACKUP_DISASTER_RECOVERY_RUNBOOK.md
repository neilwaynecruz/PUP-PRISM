# Backup, Restore, and Disaster Recovery Runbook

> **Stack:** Laravel 13 + PostgreSQL 14+  
> **Companion docs:** [PRODUCTION_READINESS_PLAN.md](../../PRODUCTION_READINESS_PLAN.md), [docs/operations/README.md](./README.md)

This runbook describes how to back up, restore, and recover PUP PRISM in production. It assumes PostgreSQL is the primary database (`DB_CONNECTION=pgsql`) and application files live on a Linux application server or managed platform.

---

## 1. Recovery objectives

| Metric | Recommended target | Notes |
| ------ | ------------------ | ----- |
| **RPO** (max acceptable data loss) | ≤ 24 hours | Daily logical backups; consider WAL archiving for stricter RPO |
| **RTO** (max acceptable downtime) | ≤ 4 hours | Depends on infra provisioning and restore drill familiarity |

Adjust targets with institutional IT policy. Document approved values in your deployment ticket or change record.

---

## 2. What to back up

### 2.1 PostgreSQL database (critical)

All transactional data: inventory, users, RBAC, audit logs, notifications, queue tables, sessions (if `SESSION_DRIVER=database`), and forecast snapshots.

### 2.2 Application storage (critical)

```text
storage/app/
```

Includes uploaded files, generated PDFs, and any user-facing assets not in source control.

### 2.3 Environment and secrets (critical)

- `.env` (or secret manager entries): `APP_KEY`, `DB_*`, `RESEND_API_KEY`, `REVERB_*`, `SENTRY_LARAVEL_DSN`
- **Never** commit production `.env` to git

### 2.4 Optional / reproducible

- `vendor/` and `node_modules/` — reinstall via Composer/npm
- Compiled caches (`bootstrap/cache/*`, `storage/framework/cache`) — regenerate with `php artisan optimize`
- Wayfinder-generated frontend routes — regenerated during `npm run build`

---

## 3. PostgreSQL backup procedures

### 3.1 Logical backup (recommended default)

Run on the database host or a backup runner with network access to PostgreSQL.

```bash
# Custom-format dump (supports parallel restore)
pg_dump \
  --format=custom \
  --no-owner \
  --file="/var/backups/pup_prism/pup_prism_$(date +%Y%m%d_%H%M%S).dump" \
  --dbname="postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
```

**Schedule:** Daily off-peak (e.g. 02:00 Asia/Manila), retain 30 daily + 12 monthly copies per institutional policy.

**Verify:** Weekly restore to a staging database (see §5).

### 3.2 Plain SQL backup (alternative)

```bash
pg_dump --no-owner --clean --if-exists \
  --file="/var/backups/pup_prism/pup_prism_$(date +%Y%m%d).sql" \
  "$DATABASE_URL"
```

Larger files; easier to inspect manually.

### 3.3 Filesystem backup for `storage/app`

```bash
tar -czf "/var/backups/pup_prism/storage_app_$(date +%Y%m%d).tar.gz" \
  -C /path/to/pup_prism/storage app
```

Run after the database dump or during a low-write window.

### 3.4 Managed hosting

If using Laravel Cloud, RDS, or institutional DBA-managed PostgreSQL, enable automated snapshots and point-in-time recovery per provider docs. Still run application-level restore drills.

---

## 4. Restore procedures

### 4.1 Restore PostgreSQL from custom-format dump

**Warning:** This overwrites the target database. Stop queue workers and put the app in maintenance mode first.

```bash
cd /path/to/pup_prism

php artisan down --retry=60 --refresh=15

# Stop workers (Supervisor example)
sudo supervisorctl stop pup-prism-worker:*

# Drop and recreate database (staging only unless disaster recovery)
dropdb --if-exists pup_prism_restore_test
createdb pup_prism_restore_test

pg_restore \
  --dbname=pup_prism_restore_test \
  --no-owner \
  --role=postgres \
  /var/backups/pup_prism/pup_prism_YYYYMMDD_HHMMSS.dump

# Point .env DB_DATABASE at restore target for validation, then:
php artisan migrate --force   # only if backup predates newer migrations
php artisan optimize:clear
php artisan optimize
```

Validate login, inventory counts, and a sample workflow before switching production traffic.

### 4.2 Restore `storage/app`

```bash
tar -xzf /var/backups/pup_prism/storage_app_YYYYMMDD.tar.gz -C /path/to/pup_prism/storage
chown -R www-data:www-data /path/to/pup_prism/storage
php artisan storage:link
```

### 4.3 Full application redeploy (code loss scenario)

1. Clone tagged release from git
2. Restore `.env` from secret store
3. `composer install --no-dev --optimize-autoloader`
4. `npm ci && npm run build`
5. Restore database (§4.1) and `storage/app` (§4.2)
6. `php artisan migrate --force`
7. `php artisan optimize`
8. Start queue worker and scheduler
9. `php artisan up`

---

## 5. Recovery drill checklist (quarterly)

Perform in **staging**, not production.

- [ ] Restore latest `pg_dump` to an isolated database
- [ ] Restore latest `storage/app` archive
- [ ] Boot application against restored data
- [ ] Log in as Admin (UAT or seeded staging account)
- [ ] Verify product stock totals match pre-backup spot check
- [ ] Submit and approve a test requisition (or cancel after validation)
- [ ] Confirm `GET /admin/health` returns `status: ok`
- [ ] Confirm queue worker processes a test notification job
- [ ] Record drill date, duration, and issues in change log

---

## 6. Disaster scenarios

| Scenario | Response |
| -------- | -------- |
| **Database corruption / accidental DROP** | Restore latest verified backup to new DB; update `DB_*`; maintenance window |
| **Application server loss** | Redeploy code from git tag; restore `.env`; attach to existing DB if intact |
| **Region / datacenter outage** | Fail over to warm standby or restore backup in secondary region per infra plan |
| **Ransomware / compromise** | Isolate hosts; restore from **pre-incident** backup; rotate all secrets (`APP_KEY` rotation invalidates sessions) |

---

## 7. Post-restore operations

```bash
php artisan queue:restart
php artisan optimize:clear && php artisan optimize
php artisan app:prune-operational-data --dry-run   # optional sanity check
```

Review `failed_jobs` after restore. Re-dispatch or flush stale jobs if workers were down during the incident.

---

## 8. Related application retention

High-growth tables are pruned automatically by `app:prune-operational-data` (see `config/retention.php`). Backups capture data **before** pruning removes eligible rows. Increase retention env vars if compliance requires longer audit history.

---

## 9. Escalation

1. On-call / system owner — assess scope
2. DBA or infrastructure — database restore
3. Application owner — validate business workflows post-restore
4. Security — if breach suspected, follow institutional incident response

---

*Last updated: 2026-07-04 — aligned with PUP PRISM PostgreSQL + Laravel 13 deployment model.*
