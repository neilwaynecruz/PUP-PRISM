# Next-Phase Implementation Plan

> Scope: This plan covers the **post-roadmap backlog** after the completed 10-feature roadmap in [`IMPLEMENTATION_TRACKING.md`](./IMPLEMENTATION_TRACKING.md).
> Source backlog: [`NEXT_PHASE_RECOMMENDATIONS.md`](./NEXT_PHASE_RECOMMENDATIONS.md)
> Tracking: [`NEXT_PHASE_TRACKING.md`](./NEXT_PHASE_TRACKING.md)
> Prompts: [`NEXT_PHASE_IMPLEMENTATION_PROMPTS.md`](./NEXT_PHASE_IMPLEMENTATION_PROMPTS.md)

## Executive Summary

The original roadmap hardened the system and completed the most urgent feature gaps. The next phase should **not** restart broad exploratory work. It should focus on seven tightly-scoped implementation epics that improve:

- **Operational safety**: offboarding enforcement, backup/recovery, queue and logging maturity
- **Enterprise governance**: unified audit trails, privileged-account controls, admin visibility
- **Inventory correctness**: cycle counts, adjustments, partial fulfillment, asset integrity rules
- **Operator productivity**: better dashboards, filters, search, reporting, and field workflows

These epics intentionally combine compatible work so implementation stays coherent and the prompts remain practical.

## Recommended Implementation Epics

### Epic 1: Access Lifecycle and Privileged Security

**Scope**
- Token and session revocation on user deactivation
- Mandatory 2FA for privileged roles
- Optional groundwork for finer-grained role/permission governance

**Why now**
- Offboarding is incomplete if old sessions or Sanctum tokens remain active
- Admin and Supply Head accounts should meet a higher security bar than standard users

**Recommended implementation**
- Revoke `personal_access_tokens` and invalidate active sessions during user deactivation
- Add active-user enforcement middleware for web/API requests
- Require confirmed 2FA for `Admin` and `Supply Head`, with a short grace period if needed
- Add audit entries for 2FA enable/disable and forced access revocation

**Likely files**
- `app/Http/Controllers/Admin/UserManagementController.php`
- `app/Providers/FortifyServiceProvider.php`
- `routes/api.php`
- `config/fortify.php`
- `app/Models/User.php`
- `resources/js/pages/settings/Security.vue`

**Priority:** Critical  
**Complexity:** Medium  
**Risk:** Medium

---

### Epic 2: Resilience, Recovery, and Platform Operations

**Scope**
- Backup, restore, and disaster recovery runbook
- Data retention and archival policy
- Queue operations maturity
- Redis + centralized logging + CI dependency security gates

**Why now**
- Production readiness is incomplete without tested recovery
- Long-term sustainability depends on retention, queue visibility, and infrastructure maturity

**Recommended implementation**
- Add markdown runbooks for backup/restore and recovery drills
- Define retention rules for `audit_logs`, `forecast_snapshots`, and notifications
- Add queue operations visibility and a broader Job layer for heavy async work
- Document or prepare Redis-backed queue/cache/session
- Add `composer audit` / `npm audit` or equivalent CI gates
- Improve logging strategy for production diagnostics

**Likely files**
- `README.md`
- `PRODUCTION_READINESS_PLAN.md`
- `routes/console.php`
- `app/Http/Controllers/Admin/HealthController.php`
- `config/cache.php`
- `config/queue.php`
- `config/session.php`
- `config/logging.php`
- `.github/workflows/*.yml`

**Priority:** Critical-High  
**Complexity:** High  
**Risk:** Medium

---

### Epic 3: Audit, Alerts, and Operations Control Center

**Scope**
- Unified audit coverage for high-risk actions
- Alerts management module
- Admin operations health UI
- Notification history page

**Why now**
- The system already records important events and generates alerts, but operator workflows are fragmented
- Admins need one place to monitor system health, alerts, and accountable actions

**Recommended implementation**
- Expand `AuditLogService` coverage to receiving, issuance, handover, and security-sensitive settings
- Build an alerts inbox with acknowledge/resolve/assign workflows
- Create an Admin health page backed by the existing health endpoint
- Add a full notification history page with filtering and pagination

**Likely files**
- `app/Services/AuditLogService.php`
- `app/Http/Controllers/Inventory/ReceivingController.php`
- `app/Http/Controllers/Inventory/HandoverController.php`
- `app/Http/Controllers/Admin/HealthController.php`
- `app/Models/InventoryAlert.php`
- `resources/js/pages/admin/`
- `resources/js/pages/notifications/`

**Priority:** High  
**Complexity:** Medium-High  
**Risk:** Low-Medium

---

### Epic 4: Inventory Integrity, Adjustments, and Fulfillment

**Scope**
- Asset integrity rules across booking and handover
- Stock adjustment workflow
- Cycle count workflow
- Partial requisition fulfillment and backorders

**Why now**
- This is the biggest remaining operational correctness gap
- Inventory systems become hard to trust if physical flow and recorded flow diverge

**Recommended implementation**
- Add booking/handover conflict validation for active bookings and pending transfers
- Add manual stock adjustments with reason codes and immutable history
- Add count sessions with variance approval
- Support line-level issued quantity, remaining quantity, and backordered requisition states

**Likely files**
- `app/Models/Booking.php`
- `app/Http/Controllers/Inventory/BookingController.php`
- `app/Http/Controllers/Inventory/HandoverController.php`
- `app/Services/Inventory/InventoryService.php`
- `app/Models/Requisition.php`
- `app/Models/RequisitionLine.php`
- `resources/js/pages/inventory/`

**Priority:** High  
**Complexity:** High  
**Risk:** Medium

---

### Epic 5: Master Data and Administrative Governance

**Scope**
- Organization and reference-data Admin CRUD
- Administrative governance improvements around production maintainability

**Why now**
- Departments, positions, categories, and origins should not depend on seeders or direct DB updates
- Admin autonomy reduces deployment friction and long-term support cost

**Recommended implementation**
- Add CRUD for departments, positions, categories, and origins
- Add safe validations for in-use references
- Add impact warnings before changing or deactivating referenced master data

**Likely files**
- `app/Models/Department.php`
- `app/Models/Position.php`
- `app/Models/Category.php`
- `app/Models/Origin.php`
- `app/Http/Controllers/Admin/`
- `resources/js/pages/admin/`

**Priority:** High  
**Complexity:** Medium  
**Risk:** Low-Medium

---

### Epic 6: Search, Dashboards, Reporting, and API Experience

**Scope**
- Cross-entity global search
- Real role-based dashboards with live metrics
- Requisitions filters
- Bookings filters
- Reporting expansion
- API guardrails and selective expansion

**Why now**
- These are high-frequency operator workflows
- Better visibility and search usually produce immediate user value

**Recommended implementation**
- Replace placeholder dashboard values with real metrics
- Build role-specific dashboards for Supply Head and Property Custodian
- Add search/status/date/requester filters to requisitions and bookings
- Add cross-entity search across products, assets, requisitions, POs, bookings, and users
- Expand reports for procurement, forecasting, and slow-moving stock
- Add API pagination caps and fill the most useful integration gaps

**Likely files**
- `resources/js/pages/Dashboard.vue`
- `app/Http/Controllers/DashboardController.php`
- `app/Services/DashboardStatsService.php`
- `app/Http/Controllers/Inventory/RequisitionController.php`
- `app/Http/Controllers/Inventory/BookingController.php`
- `resources/js/components/GlobalSearchDialog.vue`
- `app/Http/Controllers/Inventory/InventoryReportController.php`
- `routes/api.php`

**Priority:** High  
**Complexity:** Medium-High  
**Risk:** Low-Medium

---

### Epic 7: Mobile Field Operations

**Scope**
- Mobile-first operational screens
- PWA support
- Continuous QR scanning

**Why now**
- Receiving, handover, bookings, and field validation workflows benefit heavily from mobile-first design
- Current QR support is useful but can be made much more operationally efficient

**Recommended implementation**
- Add mobile card layouts and simplified operational flows
- Add manifest / installability / offline-safe patterns where appropriate
- Extend QR scanning to continuous/batch workflows with clear field feedback

**Likely files**
- `resources/js/pages/inventory/receiving/Index.vue`
- `resources/js/pages/inventory/handover/*.vue`
- `resources/js/pages/inventory/bookings/Index.vue`
- `resources/js/components/inventory/QrScannerDialog.vue`
- `resources/js/composables/useQrScanner.ts`
- `vite.config.ts`

**Priority:** Medium  
**Complexity:** Medium-High  
**Risk:** Medium

## Priority Roadmap

### Wave 1: Operational Safety

1. Epic 1 — Access Lifecycle and Privileged Security
2. Epic 2 — Resilience, Recovery, and Platform Operations

### Wave 2: Core Control and Data Integrity

3. Epic 3 — Audit, Alerts, and Operations Control Center
4. Epic 4 — Inventory Integrity, Adjustments, and Fulfillment
5. Epic 5 — Master Data and Administrative Governance

### Wave 3: Productivity and Visibility

6. Epic 6 — Search, Dashboards, Reporting, and API Experience

### Wave 4: Field Operations

7. Epic 7 — Mobile Field Operations

## Implementation Checklist

See [`NEXT_PHASE_TRACKING.md`](./NEXT_PHASE_TRACKING.md) for the live status table and completion log.

| Epic | Priority | Complexity | Risk | Status |
| ---- | -------- | ---------- | ---- | ------ |
| Access Lifecycle and Privileged Security | Critical | Medium | Medium | ✅ DONE |
| Resilience, Recovery, and Platform Operations | Critical-High | High | Medium | ✅ DONE |
| Audit, Alerts, and Operations Control Center | High | Medium-High | Low-Medium | ✅ DONE |
| Inventory Integrity, Adjustments, and Fulfillment | High | High | Medium | ✅ DONE |
| Master Data and Administrative Governance | High | Medium | Low-Medium | ✅ DONE |
| Search, Dashboards, Reporting, and API Experience | High | Medium-High | Low-Medium | ✅ DONE |
| Mobile Field Operations | Medium | Medium-High | Medium | ⬜ NOT STARTED |

## Final Summary

- **Total next-phase epics suggested:** 7
- **Total completed:** 6
- **Total not started:** 1
- **Total in progress:** 0
- **Total blocked:** 0
- **Overall completion percentage:** 86%

## Final Status

🚧 **NEXT PHASE STATUS: IN PROGRESS**

The original roadmap remains complete. This document starts the **next implementation cycle** for higher-order operational maturity, workflow completeness, and productivity improvements.
