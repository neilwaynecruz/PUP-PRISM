# Next-Phase Implementation Prompts

> These prompts are for the **post-roadmap backlog** after the completed 10-feature roadmap.
> Plan: [`NEXT_PHASE_IMPLEMENTATION_PLAN.md`](./NEXT_PHASE_IMPLEMENTATION_PLAN.md)
> Tracking: [`NEXT_PHASE_TRACKING.md`](./NEXT_PHASE_TRACKING.md)

## Documentation Update Requirements (All Prompts)

When you finish or stop work on any next-phase epic, you must update:

| File | What to update |
| ---- | -------------- |
| [NEXT_PHASE_TRACKING.md](./NEXT_PHASE_TRACKING.md) | Epic status, summary counts, completion log |
| [NEXT_PHASE_IMPLEMENTATION_PLAN.md](./NEXT_PHASE_IMPLEMENTATION_PLAN.md) | Implementation Checklist row to match tracking |
| [ACCURACY_REVIEW.md](./ACCURACY_REVIEW.md) | Only if the implementation invalidates or changes a prior accuracy note |

### Status Values

| Status | When to use |
| ------ | ----------- |
| `✅ DONE` | Fully implemented, tested, and verified |
| `🟡 IN PROGRESS` | Partially implemented; remaining work clearly listed |
| `⬜ NOT STARTED` | No meaningful work done |
| `⚠️ BLOCKED` | Cannot continue due to blocker or dependency |

### Required Tracking Rules

1. Update the epic row in `NEXT_PHASE_TRACKING.md`
2. Recalculate summary counts and completion percentage
3. Append or update an Epic Completion Log entry with tests and notes
4. Keep the original `IMPLEMENTATION_TRACKING.md` unchanged unless the work explicitly modifies the original roadmap state

---

## Prompt 1 — Access Lifecycle and Privileged Security

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Access Lifecycle and Privileged Security

Objective:
Make account offboarding immediate and enforce stronger authentication controls for privileged roles.

Context:
PUP PRISM already supports user deactivation, Fortify login security, Sanctum API tokens, and optional 2FA. The remaining gaps are: existing sessions and tokens may survive deactivation, and Admin / Supply Head access is not yet protected by mandatory 2FA.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create database fields, models, middleware, controllers, services, and frontend components only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Revoke all Sanctum personal access tokens when a user is deactivated.
- Invalidate active sessions for deactivated users.
- Add active-user enforcement middleware for authenticated web and API requests so inactive users cannot continue using existing sessions/tokens.
- Require confirmed 2FA for privileged roles (`Admin`, `Supply Head`) before accessing protected areas, with a practical UX flow.
- Add audit entries for deactivation, token/session revocation, and 2FA enable/disable if missing.
- Preserve non-privileged user flows unless explicitly required otherwise.
- Add or update Pest tests for deactivated-session behavior, deactivated-token behavior, and privileged-role 2FA enforcement.
- Run relevant backend tests and format changed PHP files with Pint.

Expected Output:
- Fully implemented epic or clearly partial implementation with remaining work listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes (commands and results)
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless it is fully implemented, tested, and verified.
- If incomplete, mark it as IN PROGRESS or BLOCKED in `NEXT_PHASE_TRACKING.md` and explain what remains.
```

---

## Prompt 2 — Resilience, Recovery, and Platform Operations

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Resilience, Recovery, and Platform Operations

Objective:
Strengthen production resilience with backup/recovery guidance, retention rules, queue operations maturity, and safer platform defaults.

Context:
PUP PRISM already has scheduled commands, queued notifications, and a health endpoint. The next maturity layer is operational resilience: documented recovery, data retention, queue/job visibility, and platform hardening for scale.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create markdown docs, configs, jobs, health endpoints, and support code only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Create a production-grade backup / restore / disaster recovery runbook in markdown, aligned with the current Laravel/PostgreSQL deployment model.
- Define and implement a retention strategy for high-growth tables such as audit logs, forecast snapshots, and notifications, where appropriate.
- Improve queue operations maturity: introduce a clearer job layer or queue-ops workflow if needed, and expose useful operational information in admin-facing monitoring.
- Prepare or document Redis migration strategy for cache/session/queue in production.
- Improve production logging guidance and centralization strategy.
- Add CI supply-chain guardrails such as `composer audit` and `npm audit` if appropriate for the repo’s CI structure.
- Keep deployment guidance aligned with existing `PRODUCTION_READINESS_PLAN.md` rather than duplicating or conflicting with it.
- Add or update tests only where code changes justify them; documentation-only subparts do not need artificial tests.

Expected Output:
- Implemented operational improvements and/or clear, production-ready runbooks
- Clean and maintainable code/config/docs
- No broken existing functionality
- Clear implementation summary
- List of modified or created files
- Testing notes where relevant
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless the implemented scope is complete and verified.
- If some parts are documentation-only and others are code, clearly separate what was implemented vs documented.
```

---

## Prompt 3 — Audit, Alerts, and Operations Control Center

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Audit, Alerts, and Operations Control Center

Objective:
Unify high-risk audit visibility and create operator-facing workflows for alerts, health, and notification history.

Context:
PUP PRISM already has `AuditLogService`, `InventoryAlert` generation, an admin health endpoint, and notification dropdown UX. The remaining problem is fragmentation: important actions are not fully audited, alerts are not triaged in a proper module, health is mostly backend-only, and notification history does not scale beyond the dropdown.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create controllers, models, policies, pages, services, and routes only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Expand audit coverage to include receiving, issuance, handover, and security-sensitive settings/actions where missing.
- Build an alerts management module with index/listing, filtering, acknowledge/resolve actions, and clear ownership/triage workflow.
- Add an Admin operations health UI that surfaces the existing backend health data in a practical dashboard.
- Add a full notification history page with filtering and pagination for heavy users.
- Ensure authorization is explicit and role-safe for all new admin/operator views.
- Reuse existing UI patterns and Wayfinder routing where appropriate.
- Add focused Pest tests for new controller behaviors and any sensitive audit/alert state transitions.

Expected Output:
- Fully implemented operator control-center features or clearly partial delivery with remaining items listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless audit coverage, alerts module, and the implemented UI surfaces are complete and verified.
```

---

## Prompt 4 — Inventory Integrity, Adjustments, and Fulfillment

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Inventory Integrity, Adjustments, and Fulfillment

Objective:
Improve real-world inventory correctness by enforcing stronger asset integrity rules, supporting stock adjustments/cycle counts, and allowing partial requisition fulfillment.

Context:
PUP PRISM already supports bookings, handovers, stock movements, requisitions, issuance, and forecasting. The remaining gap is operational realism: physical assets can be double-committed, adjustments/counts are incomplete, and requisitions assume all-or-nothing issuance.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update models, controllers, migrations, services, requests, policies, and pages only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Add stronger asset conflict checks across booking approval and handover initiation/verification workflows.
- Implement stock adjustment support with reason codes and auditability.
- Implement cycle count workflow and variance handling in a way that fits the existing inventory architecture.
- Add partial requisition fulfillment / backorder support with line-level remaining quantities and clear status behavior.
- Preserve the integrity of existing `StockMovement` history and existing completed workflows.
- Add migrations only where the domain model truly requires them.
- Add focused Pest coverage for booking/handover conflicts, adjustment behavior, cycle count behavior, and partial requisition issuance.

Expected Output:
- Fully implemented workflow improvements or clearly partial delivery with remaining gaps listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless the implemented business rules are exercised by tests and verified against existing workflows.
```

---

## Prompt 5 — Master Data and Administrative Governance

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Master Data and Administrative Governance

Objective:
Allow production-safe Admin management of departments, positions, categories, and origins without relying on seeders or direct database edits.

Context:
PUP PRISM already has Admin user management and uses master/reference data across provisioning and inventory flows. The missing capability is safe, self-service CRUD for organizational and reference entities.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create controllers, requests, policies, pages, and routes only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Add Admin CRUD for departments, positions, categories, and origins.
- Protect in-use records with safe validation and meaningful operator feedback.
- Add impact warnings before changing, deactivating, or deleting master data used elsewhere.
- Reuse existing admin page patterns and permissions model.
- Add focused tests for CRUD, authorization, and in-use validation constraints.

Expected Output:
- Fully implemented master-data admin capabilities or clearly partial delivery with remaining work listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless CRUD and integrity protections are both implemented and verified.
```

---

## Prompt 6 — Search, Dashboards, Reporting, and API Experience

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Search, Dashboards, Reporting, and API Experience

Objective:
Improve operator productivity with real role-based dashboards, stronger workflow filters, cross-entity search, expanded reporting, and safer API behavior.

Context:
PUP PRISM already has a dashboard, global search entry point, reports, and a Sanctum API. The next maturity step is better operator visibility and discoverability: replace placeholder metrics, tailor dashboards by role, add missing filters, support entity search, expand reports, and harden API contracts.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create controllers, services, routes, pages, and resources only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Replace placeholder or trust-reducing dashboard KPI content with real backend data.
- Build meaningful role-based dashboard variants for Supply Head and Property Custodian.
- Add filters/search/date/status UX for requisitions and bookings.
- Upgrade global search from navigation-only to cross-entity search across key inventory/admin entities.
- Expand reports for procurement, forecasting, slow-moving stock, or other high-value missing areas.
- Add API guardrails such as sensible `per_page` caps and close the most important resource/contract gaps.
- Reuse Wayfinder, existing layout patterns, and current report/export architecture where appropriate.
- Add focused tests for controller filtering, search endpoints, and API pagination validation as needed.

Expected Output:
- Fully implemented productivity improvements or clearly partial delivery with remaining work listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless search, dashboards, and the implemented filtering/reporting/API improvements are verified.
```

---

## Prompt 7 — Mobile Field Operations

```text
You are a Senior Full-Stack Developer and Software Architect.

Your task is to implement the following epic:

Mobile Field Operations

Objective:
Improve day-to-day field workflows with mobile-first screens, PWA support, and stronger QR-based operational flows.

Context:
PUP PRISM already includes QR scanning support and core receiving/handover/booking flows. The next step is making those workflows fast and reliable for touch-based, mobile, field-oriented usage.

Implementation Requirements:
1. Analyze the existing project structure before making changes.
2. Follow the current architecture, naming conventions, coding style, and folder structure.
3. Preserve all existing functionality.
4. Do not introduce breaking changes.
5. Implement the epic using clean, modular, reusable, and maintainable code.
6. Add proper validation, error handling, and security checks.
7. Optimize for performance and scalability.
8. Handle important edge cases.
9. Update or create frontend pages, scanner composables/components, and PWA-related config only when necessary.
10. Add useful comments only where the logic is complex.
11. Add or update tests when applicable.
12. Update documentation or inline usage notes when needed.
13. When finished or stopping, update docs/system-improvement/ per the Documentation Update Requirements in NEXT_PHASE_IMPLEMENTATION_PROMPTS.md.

Specific Epic Requirements:
- Improve mobile layouts for receiving, handover, bookings, forecasting, and relevant admin screens where needed.
- Add PWA foundations if appropriate for the stack and deployment model.
- Extend QR scanning toward continuous/batch operational flows with clear user feedback.
- Preserve desktop usability and existing scanner behavior while improving field ergonomics.
- Add or update frontend/browser tests where meaningful and practical.

Expected Output:
- Fully implemented field-operations improvements or clearly partial delivery with remaining work listed
- Clean and maintainable code
- No broken existing functionality
- Proper validation and error handling
- Clear implementation summary
- List of modified or created files
- Testing notes
- Any assumptions made
- Documentation updates completed in `NEXT_PHASE_TRACKING.md` and `NEXT_PHASE_IMPLEMENTATION_PLAN.md`

Important:
- Do not mark this epic as DONE unless the implemented mobile/PWA/scanner scope is verified and documented.
```

---

## Suggested Execution Order

1. Access Lifecycle and Privileged Security
2. Resilience, Recovery, and Platform Operations
3. Audit, Alerts, and Operations Control Center
4. Inventory Integrity, Adjustments, and Fulfillment
5. Master Data and Administrative Governance
6. Search, Dashboards, Reporting, and API Experience
7. Mobile Field Operations
