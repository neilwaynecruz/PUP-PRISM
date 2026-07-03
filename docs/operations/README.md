# Operations Runbooks

Production operations documentation for PUP PRISM. These runbooks complement [PRODUCTION_READINESS_PLAN.md](../../PRODUCTION_READINESS_PLAN.md) (server/runtime checklist) and focus on **recovery, retention, queues, Redis, and logging**.

| Document | Purpose |
| -------- | ------- |
| [BACKUP_DISASTER_RECOVERY_RUNBOOK.md](./BACKUP_DISASTER_RECOVERY_RUNBOOK.md) | PostgreSQL backup/restore and disaster-recovery drills |
| [QUEUE_OPERATIONS.md](./QUEUE_OPERATIONS.md) | Queue workers, monitoring, failed-job handling |
| [REDIS_PRODUCTION_MIGRATION.md](./REDIS_PRODUCTION_MIGRATION.md) | Migrating cache, session, and queue drivers to Redis |
| [PRODUCTION_LOGGING.md](./PRODUCTION_LOGGING.md) | Log channels, retention, and centralization strategy |

## Scheduled maintenance

The Laravel scheduler (`routes/console.php`) runs:

| Command | Schedule | Purpose |
| ------- | -------- | ------- |
| `app:generate-demand-forecasts` | Daily 01:30 | Demand forecasting snapshots |
| `app:inventory-generate-alerts` | Daily 02:00 | Low-stock and expiring-lot alerts |
| `app:send-notification-digests` | Daily 08:00 | Daily notification email digests |
| `trash:cleanup` | Daily | Permanently delete soft-deleted records (30-day default) |
| `app:prune-operational-data` | Weekly Sun 03:30 | Prune aged audit logs, forecast snapshots, notifications, failed jobs |

Retention defaults are configured in `config/retention.php` and overridable via `.env`.

## Admin health endpoint

`GET /admin/health` (Admin role) returns queue and scheduler metadata, including `queue_operations` for operational visibility. See [QUEUE_OPERATIONS.md](./QUEUE_OPERATIONS.md).
