# Next-Phase Recommendations

> Scope: These recommendations are **beyond** the completed 10-feature roadmap in [`IMPLEMENTATION_TRACKING.md`](./IMPLEMENTATION_TRACKING.md).
> They are intended as the next backlog after the current feature roadmap, and they do **not** replace the still-open P1 deployment tasks in [`PRODUCTION_READINESS_PLAN.md`](../../PRODUCTION_READINESS_PLAN.md).
> Structured implementation assets:
> - Plan: [`NEXT_PHASE_IMPLEMENTATION_PLAN.md`](./NEXT_PHASE_IMPLEMENTATION_PLAN.md)
> - Prompts: [`NEXT_PHASE_IMPLEMENTATION_PROMPTS.md`](./NEXT_PHASE_IMPLEMENTATION_PROMPTS.md)
> - Tracking: [`NEXT_PHASE_TRACKING.md`](./NEXT_PHASE_TRACKING.md)

## Executive Summary

The original system-improvement roadmap is complete, but the system still has meaningful next-step opportunities in four areas:

1. **Operational safety**: backup/recovery, token/session revocation, active-user enforcement
2. **Enterprise controls**: stronger audit coverage, mandatory MFA for privileged users, alert triage
3. **Workflow completeness**: stock adjustments, partial fulfillment, reference-data admin tools
4. **User productivity**: real dashboards, better filters, cross-entity search, mobile-first operational flows

The highest-value next phase is not "more features for the sake of it." It is about making the system **safer to operate, easier to administer, and faster to use at scale**.

## Highest-Priority Next Work

### 1. Token and Session Revocation on User Deactivation

**Why it matters:** Deactivated users should lose access immediately. If sessions or Sanctum tokens remain active, offboarding is incomplete.

**Key areas:**
- `app/Http/Controllers/Admin/UserManagementController.php`
- `app/Providers/FortifyServiceProvider.php`
- `routes/api.php`

**Priority:** Critical

---

### 2. Backup, Restore, and Disaster Recovery Runbook

**Why it matters:** Inventory, audit, notification, and queue data are mission-critical. A production system is not truly ready without tested restore procedures.

**Recommended scope:**
- Nightly PostgreSQL backups
- Retention policy
- Off-site storage
- Restore drill checklist
- `storage/app/public` backup coverage if file-backed assets/signatures exist

**Priority:** Critical

---

### 3. Unified Audit Coverage for High-Risk Operations

**Why it matters:** Receiving, issuance, handovers, security-sensitive settings, and token lifecycle events should be visible in one audit story, not split across multiple tables or missing entirely.

**Key areas:**
- `app/Services/Inventory/InventoryService.php`
- `app/Http/Controllers/Inventory/ReceivingController.php`
- `app/Http/Controllers/Inventory/HandoverController.php`
- `app/Http/Controllers/Settings/ApiTokenController.php`

**Priority:** High

---

### 4. Asset Integrity Rules Across Booking and Handover

**Why it matters:** Physical assets should not be double-committed across overlapping bookings, pending transfers, or inconsistent lifecycle states.

**Recommended checks:**
- Prevent handover if there is an active approved booking conflict
- Prevent duplicate pending handovers for the same asset
- Tighten booking conflict logic for operational reality

**Priority:** High

---

### 5. Mandatory 2FA for Privileged Roles

**Why it matters:** Admin and Supply Head accounts are high-impact accounts. Optional MFA is weaker than most enterprise inventory environments require.

**Key areas:**
- `config/fortify.php`
- `app/Models/User.php`
- `resources/js/pages/settings/Security.vue`

**Priority:** High

---

### 6. Alerts Management Module

**Why it matters:** The system generates alerts, but there is no proper operator workflow to triage, acknowledge, resolve, assign, or audit them.

**Recommended scope:**
- Alerts inbox
- Filter by type, urgency, age, product
- Acknowledge / resolve actions
- Ownership / assignment
- SLA-style "stale alert" indicators

**Priority:** High

---

### 7. Organization and Reference Data Admin CRUD

**Why it matters:** Departments, positions, categories, and origins should not require seeders or DB access for maintenance in production.

**Key areas:**
- `app/Models/Department.php`
- `app/Models/Position.php`
- `app/Models/Category.php`
- `app/Models/Origin.php`

**Priority:** High

---

### 8. Stock Adjustment and Cycle Count Workflow

**Why it matters:** Real inventory operations need manual adjustment, variance reconciliation, and physical count support. The data model already implies these needs.

**Recommended scope:**
- Manual stock adjustment UI with reason codes
- Cycle count sessions
- Variance approval
- Immutable adjustment history

**Priority:** High

---

### 9. Partial Requisition Fulfillment and Backorders

**Why it matters:** Real issuing often cannot fulfill requested quantities in one pass. Without partial issue support, users are forced into inaccurate or awkward workarounds.

**Recommended scope:**
- Line-level fulfilled qty
- Remaining qty
- Backordered status
- Partial issue audit visibility

**Priority:** High

## High-Value UX and Productivity Improvements

### 10. Real Dashboard Metrics Instead of Placeholder KPI Content

Some dashboard widgets still show placeholder-style or simplified values. Replace all static or trust-reducing metrics with live backend values.

**Priority:** High

### 11. Role-Based Dashboards

Property Custodian and Supply Head need dashboards tailored to their daily work, not a lightly modified admin dashboard.

**Priority:** High

### 12. Requisitions Index Filters

Add search, status, requester, and date filters to the main requisitions list.

**Priority:** High

### 13. Bookings Index Filters

Add list and calendar filtering by status, requester, date range, and asset.

**Priority:** High

### 14. Cross-Entity Global Search

Upgrade global search from module navigation to true entity search across products, assets, requisitions, POs, bookings, and users.

**Priority:** High

### 15. Admin Operations Health UI

The backend health endpoint exists, but there is no proper admin-facing operations dashboard.

**Priority:** Medium

### 16. Notification History Page

The current dropdown is useful, but high-volume users need a searchable, paginated notification history page.

**Priority:** Medium

### 17. Mobile-First Operations Screens

Receiving, handover, bookings, forecasting, and admin screens should have stronger mobile card layouts and field-friendly interactions.

**Priority:** Medium

### 18. PWA and Continuous QR Scanning

If warehouse/floor usage is important, the next real multiplier is continuous scan flow, offline-friendly behavior, and touch-first scanning ergonomics.

**Priority:** Medium

## Scalability, Governance, and Long-Term Hardening

### 19. Data Retention and Archival Policy

`audit_logs`, `forecast_snapshots`, and `notifications` will grow continuously. Add retention, archival, or summarization rules before growth becomes a performance issue.

**Priority:** Medium

### 20. Queue Operations Dashboard and Job Layer

Notifications are queued, but the app still lacks a deeper job architecture and operational tooling for retries, failures, and queue visibility.

**Priority:** Medium

### 21. Reporting Expansion

Add exports/reports for:
- Purchase orders
- Suppliers
- Forecast results
- Slow-moving or dead stock
- Alert resolution history

**Priority:** Medium

### 22. API Guardrails and Expansion

Improve external integration maturity with:
- `per_page` upper bounds
- more complete resource surface
- webhook strategy if needed
- contract consistency checks

**Priority:** Medium

### 23. Redis for Production Cache / Session / Queue

Database-backed cache/session/queue is acceptable for small deployments, but Redis is the more scalable production path.

**Priority:** Medium

### 24. Centralized Logging and Ops Alerting

Move from passive health visibility to actionable operational alerting and centralized logs.

**Priority:** Medium

### 25. Granular Permission Model

Spatie permission infrastructure exists, but the app still behaves mostly role-first. Long term, move to finer permissions for separation of duties.

**Priority:** Medium

### 26. Supply-Chain and Dependency Security Gates

Add CI checks such as:
- `composer audit`
- `npm audit`
- dependency update automation

**Priority:** Medium

### 27. Content Security Policy and Browser Hardening

Current security headers are useful, but CSP and Permissions-Policy would further reduce frontend attack surface.

**Priority:** Medium

## Recommended Order

### Tier 1: Do Next

1. Token and session revocation on deactivation
2. Backup / restore / DR runbook
3. Unified audit coverage
4. Asset integrity rules
5. Mandatory 2FA for privileged users

### Tier 2: Highest Operational ROI

6. Alerts management module
7. Org/reference-data admin CRUD
8. Stock adjustment / cycle count
9. Partial requisition fulfillment
10. Real role-based dashboards

### Tier 3: Daily Productivity

11. Requisitions filters
12. Bookings filters
13. Cross-entity global search
14. Admin health UI
15. Notification history page

### Tier 4: Scale and Maturity

16. Data retention / archival
17. Queue ops dashboard
18. Reporting expansion
19. API expansion and guardrails
20. Redis + centralized logging + CI supply-chain security

## Notes

- These items are **additional recommendations**, not proof that the system is weak.
- The original 10-feature roadmap can remain marked complete.
- The open **P1 deployment tasks** remain important and should still be treated as prerequisites for production.
