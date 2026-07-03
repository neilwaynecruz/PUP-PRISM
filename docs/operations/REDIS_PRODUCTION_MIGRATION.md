# Redis Production Migration Strategy

> **Companion docs:** [PRODUCTION_READINESS_PLAN.md](../../PRODUCTION_READINESS_PLAN.md), [QUEUE_OPERATIONS.md](./QUEUE_OPERATIONS.md)

PUP PRISM defaults to **database** drivers for cache, session, and queue in `.env.example`. This is appropriate for early production and small deployments. Migrate to **Redis** when you observe connection pool pressure, slow session writes, or queue latency under concurrent workers.

---

## 1. When to migrate

| Signal | Likely bottleneck |
| ------ | ----------------- |
| High `jobs` table row churn | Queue driver |
| Session table lock contention | Session driver |
| Dashboard cache queries slow | Cache store |
| Multiple queue workers on one DB | Queue + DB load |

Redis consolidates ephemeral state off PostgreSQL, leaving the database for transactional inventory data.

---

## 2. Target architecture

```text
┌─────────────┐     ┌──────────────┐     ┌────────────┐
│  Laravel    │────▶│    Redis     │     │ PostgreSQL │
│  App        │     │ cache/session│     │ inventory  │
│             │────▶│ queue        │     │ + audit    │
└─────────────┘     └──────────────┘     └────────────┘
```

**Recommended production `.env` values after migration:**

```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your-strong-password
REDIS_PORT=6379
```

Use TLS and managed Redis (ElastiCache, Redis Cloud, institutional cluster) in production. Set `REDIS_URL` if your provider supplies a connection string.

---

## 3. Migration phases

### Phase A — Cache only (lowest risk)

1. Provision Redis with persistence **disabled** for cache (acceptable data loss on restart).
2. Set `CACHE_STORE=redis`.
3. Deploy: `php artisan optimize:clear && php artisan optimize`.
4. Monitor cache hit rates and error logs for 48 hours.

**Rollback:** `CACHE_STORE=database`, clear config cache.

### Phase B — Sessions

1. Schedule a short maintenance window (sessions invalidate on driver change).
2. Set `SESSION_DRIVER=redis`.
3. Deploy during low traffic; users re-login once.
4. Remove reliance on `sessions` table for new traffic (table may remain for audit).

**Rollback:** `SESSION_DRIVER=database`; users re-login again.

### Phase C — Queue

1. **Drain** the database queue before cutover:

   ```bash
   # Run worker until jobs table is empty
   php artisan queue:work database --queue=default,notifications,broadcast --stop-when-empty
   ```

2. Set `QUEUE_CONNECTION=redis`.
3. Restart workers with the same `--queue=default,notifications,broadcast` flags.
4. Confirm new jobs land in Redis (`redis-cli KEYS *queues*` or Horizon if adopted later).

**Rollback:** Drain Redis queue if possible; revert `QUEUE_CONNECTION=database`; restart workers.

---

## 4. Scheduler heartbeats and Redis cache

`SchedulerHeartbeat` stores last-run timestamps in the **default cache store**. When cache migrates to Redis, heartbeats move automatically — no code change required.

Ensure Redis persistence (RDB/AOF) if scheduler SLA monitoring is critical, or accept that heartbeats reset on full Redis flush.

---

## 5. Configuration reference

Existing `config/cache.php`, `config/session.php`, and `config/queue.php` already define Redis connections. No application code changes are required for the migration itself.

| Setting | Config key | Default connection |
| ------- | ---------- | ------------------ |
| Cache | `CACHE_STORE=redis` | `config/database.php` → `redis.default` |
| Session | `SESSION_DRIVER=redis` | Same Redis connection |
| Queue | `QUEUE_CONNECTION=redis` | `REDIS_QUEUE_CONNECTION=default` |

Tune `REDIS_QUEUE_RETRY_AFTER` to match worker timeout (default 90 seconds).

---

## 6. Security

- Bind Redis to private network only; never expose port 6379 publicly.
- Set `REDIS_PASSWORD` (or ACL username/password via `REDIS_URL`).
- Use separate logical databases or key prefixes if sharing Redis with other apps (`CACHE_PREFIX`).

---

## 7. Verification checklist

- [ ] `php artisan tinker --execute 'Cache::put("redis_test", "ok", 60);'` returns ok
- [ ] Login persists across requests (session)
- [ ] Test notification delivers with worker on Redis queue
- [ ] `GET /admin/health` shows `queue_connection: redis`
- [ ] Load test: pending job latency acceptable under expected worker count

---

## 8. Future: Laravel Horizon

Horizon is **not** bundled today. Consider it when you need a dashboard for Redis queue metrics and balancing. Until then, use `GET /admin/health` and [QUEUE_OPERATIONS.md](./QUEUE_OPERATIONS.md).

---

*Last updated: 2026-07-04*
