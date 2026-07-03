# Queue Operations

> **Companion docs:** [PRODUCTION_READINESS_PLAN.md](../../PRODUCTION_READINESS_PLAN.md) **P1.7**, [README.md](./README.md)

PUP PRISM uses Laravel's queue system for notifications, broadcasts, and other async work. Production requires a **persistent worker**; the database driver is the default (`QUEUE_CONNECTION=database`).

---

## 1. Queue topology

| Queue | Used by | Notes |
| ----- | ------- | ----- |
| `default` | General queued work | Default Laravel queue |
| `notifications` | `*Notification` classes implementing `ShouldQueue` | Mail, database, broadcast channels |
| `broadcast` | Laravel broadcast jobs | Real-time Reverb updates |

**Recommended worker command:**

```bash
php artisan queue:work --queue=default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600
```

Local development (`composer run dev`) uses `queue:listen` for faster feedback; production should use `queue:work` under Supervisor, systemd, Forge, or Laravel Cloud.

---

## 2. Required database tables

Ensure migrations have created:

- `jobs` — pending queue payloads
- `failed_jobs` — failed job audit (`QUEUE_FAILED_DRIVER=database-uuids`)

If missing:

```bash
php artisan make:queue-failed-table --no-interaction
php artisan migrate --force
```

---

## 3. Admin health monitoring

`GET /admin/health` (Admin role) exposes:

| Field | Meaning |
| ----- | ------- |
| `queue_connection` | Active driver (`database`, `redis`, etc.) |
| `failed_jobs_count` | Total rows in `failed_jobs` |
| `queue_operations.pending_jobs_count` | Jobs waiting in `jobs` |
| `queue_operations.pending_by_queue` | Breakdown by queue name |
| `queue_operations.reserved_jobs_count` | Jobs currently claimed by a worker |
| `queue_operations.possibly_stuck_jobs_count` | Reserved longer than `retry_after` |
| `queue_operations.oldest_pending_job_age_seconds` | Age of oldest pending job |
| `queue_operations.recent_failed_jobs` | Last five failures (uuid, queue, failed_at) |
| `queue_operations.recommended_worker_command` | Copy-paste worker invocation |
| `scheduler_last_runs` | Last successful scheduler command timestamps |

**Alerting suggestions:**

- `possibly_stuck_jobs_count > 0` for > 15 minutes
- `failed_jobs_count` increasing over 24 hours
- `oldest_pending_job_age_seconds > 300` during business hours
- Scheduler heartbeat `null` for any tracked command after expected run time

---

## 4. Operational workflows

### 4.1 Deployments

After each deploy:

```bash
php artisan queue:restart
```

Workers finish the current job, then exit and respawn with new code.

### 4.2 Inspect failed jobs

```bash
php artisan queue:failed
php artisan queue:failed --queue=notifications
```

### 4.3 Retry failed jobs

```bash
# Single job
php artisan queue:retry <uuid>

# All failed jobs (use cautiously — ensure idempotency)
php artisan queue:retry all
```

### 4.4 Flush failed jobs

```bash
php artisan queue:flush
```

Only after root cause is fixed and retries are not needed.

### 4.5 Clear stuck reserved jobs

If a worker crashed mid-job, reserved rows may block retries until `retry_after` expires. Investigate before manual deletion:

```sql
-- Example: inspect stuck jobs (PostgreSQL)
SELECT id, queue, reserved_at, attempts, created_at
FROM jobs
WHERE reserved_at IS NOT NULL
ORDER BY reserved_at ASC
LIMIT 20;
```

Prefer `php artisan queue:restart` and waiting for `retry_after`. Delete rows only when certain the worker is dead.

### 4.6 Dry-run operational data pruning

Failed jobs older than `RETENTION_FAILED_JOBS_DAYS` (default 30) are pruned weekly:

```bash
php artisan app:prune-operational-data --only=failed_jobs --dry-run
```

---

## 5. Notification job layer

All workflow notifications implement `ShouldQueue` and target the `notifications` queue with `$tries = 3`. There is no separate `app/Jobs` layer yet; notifications are the primary async unit. Heavy exports or batch processing should use dedicated job classes under `app/Jobs` when added.

**Idempotency:** Retries may duplicate emails if a job fails after send but before acknowledgment. Monitor `failed_jobs` and prefer safe, repeatable notification content.

---

## 6. Supervisor example

```ini
[program:pup-prism-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pup_prism/artisan queue:work --queue=default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pup_prism/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Scale `numprocs` with load. Database-backed queues add DB write pressure — see [REDIS_PRODUCTION_MIGRATION.md](./REDIS_PRODUCTION_MIGRATION.md) when pending job latency grows.

---

## 7. Troubleshooting

| Symptom | Check |
| ------- | ----- |
| Emails never arrive | Worker running? `jobs` table growing? `failed_jobs`? Resend API key? |
| Real-time updates missing | `broadcast` queue worker; Reverb process; `BROADCAST_CONNECTION` |
| Jobs retry forever | Exception in logs; fix code; `queue:retry` after deploy |
| Health shows stuck jobs | Restart workers; verify `DB_QUEUE_RETRY_AFTER` matches worker `--timeout` |

---

*Last updated: 2026-07-04*
