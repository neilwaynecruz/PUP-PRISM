# Production Logging Strategy

> **Companion docs:** [PRODUCTION_READINESS_PLAN.md](../../PRODUCTION_READINESS_PLAN.md), [README.md](./README.md)

This document defines how PUP PRISM should log in production and how logs can be centralized for diagnostics and incident response.

---

## 1. Goals

- Capture actionable errors without logging secrets or PII unnecessarily
- Rotate logs to prevent disk exhaustion
- Forward logs to a central platform for search and alerting
- Complement optional Sentry error tracking (`SENTRY_LARAVEL_DSN`)

---

## 2. Recommended production `.env`

```dotenv
APP_DEBUG=false
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

| Variable | Purpose |
| -------- | ------- |
| `LOG_CHANNEL=stack` | Fan-out to multiple handlers |
| `LOG_STACK=daily,stderr` | Rotating local files + container/platform log collector |
| `LOG_LEVEL=warning` | Reduces noise; use `error` for very high traffic |
| `LOG_DAILY_DAYS=14` | Local retention before rotation deletion |

**Local development** keeps `LOG_LEVEL=debug` and `LOG_STACK=single` per `.env.example`.

---

## 3. Log locations

| Source | Path / stream |
| ------ | ------------- |
| Application | `storage/logs/laravel-YYYY-MM-DD.log` (daily driver) |
| Queue worker | `storage/logs/queue-worker.log` (Supervisor `stdout_logfile`) |
| Web server | Nginx/Apache access + error logs (institutional standard) |
| PHP-FPM | `/var/log/php*-fpm.log` or platform equivalent |
| Scheduler | Cron redirect or `schedule:run` logging wrapper |

Never commit `storage/logs/*` — paths are gitignored.

---

## 4. Centralization options

Choose one approach aligned with institutional infrastructure:

### Option A — Platform log agent (recommended for VMs)

Ship `storage/logs/*.log` and worker logs via **Fluent Bit**, **Filebeat**, or **Vector** to Elasticsearch, OpenSearch, Loki, or Splunk.

Example Filebeat input:

```yaml
- type: log
  enabled: true
  paths:
    - /var/www/pup_prism/storage/logs/laravel-*.log
    - /var/www/pup_prism/storage/logs/queue-worker.log
  fields:
    app: pup_prism
    env: production
```

### Option B — Container / PaaS stdout

Set `LOG_STACK=stderr` (or `daily,stderr`). The orchestrator (Kubernetes, Laravel Cloud, etc.) collects stderr. Ensure `LOG_LEVEL` is appropriate to avoid cost explosion.

### Option C — Syslog / Papertrail

Laravel supports `papertrail` and `syslog` channels in `config/logging.php`. Configure `LOG_PAPERTRAIL_URL` and `LOG_PAPERTRAIL_PORT` if using SolarWinds Papertrail or compatible UDP syslog.

### Option D — Sentry (errors only)

`SENTRY_LARAVEL_DSN` captures exceptions and performance traces. It does **not** replace request/access logging. Use alongside file or centralized application logs.

---

## 5. What to log (and avoid)

**Do log:**

- Unhandled exceptions (automatic)
- Failed queue jobs (Laravel `failed_jobs` table + worker stderr)
- Security events (audit log table + auth failures at `warning`/`error`)
- Scheduler failures (cron wrapper should log non-zero exit)

**Avoid logging:**

- Passwords, API keys, recovery codes, full session payloads
- Full request bodies with personal data unless required by policy
- Debug dumps in production (`APP_DEBUG` must stay `false`)

---

## 6. Correlation and incident response

1. Note incident time (Asia/Manila / `APP_TIMEZONE`)
2. Search centralized logs for `production.ERROR`
3. Cross-check `failed_jobs` and Sentry issue IDs
4. Use `audit_logs` for user-action accountability (retention per `RETENTION_AUDIT_LOGS_DAYS`)
5. Check `GET /admin/health` for queue and scheduler anomalies

---

## 7. Log retention alignment

| Layer | Default retention |
| ----- | ----------------- |
| Local Laravel daily logs | `LOG_DAILY_DAYS` (14) |
| Central log platform | Per institutional policy (often 90–365 days) |
| `audit_logs` table | `RETENTION_AUDIT_LOGS_DAYS` (365) — pruned weekly |

Application audit data outlives local log files by design. Do not rely on filesystem logs alone for compliance queries.

---

## 8. Operational commands

```bash
# Tail today's log
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Clear stale compiled caches (not a log purge)
php artisan optimize:clear
```

For disk emergencies, archive old `laravel-*.log` files to cold storage before deletion.

---

*Last updated: 2026-07-04*
