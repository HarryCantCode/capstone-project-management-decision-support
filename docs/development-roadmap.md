# Development Roadmap — Dex PMS
_Date: 2026-08-20_

This roadmap is derived from the module dependency map and the architecture
audit. It sequences work so that nothing is built on an unready foundation.

IMPORTANT: Phases 0 and 1 cannot begin until all Open Questions in
open-questions.md marked as BLOCKS are answered.

---

## Phase 0 — Project Bootstrap
Goal: A running Laravel 11 application with correct tooling, no application
logic yet.

  Tasks:
  - Install Laravel 11 via Sail or XAMPP (decision OQ-9 required)
  - Configure Vite with Bootstrap 5 + Alpine.js entry points
  - Connect MySQL 8 database; configure .env
  - Install spatie/laravel-permission; publish and run its migrations
  - Install spatie/laravel-activitylog
  - Install Laravel Pint; configure .pint.json
  - Confirm test framework (Pest or PHPUnit — decision OQ-8 required)
  - Create git branch structure: main + develop + feature/auth
  - Write README.md with setup steps (required by coding-standards.md)
  - Configure git pre-commit hook to run Pint

  Definition of Done:
  - php artisan serve returns a 200 response
  - php artisan test passes (zero tests — that is expected at this point)
  - Pint runs cleanly on the scaffold files
  - README covers: local setup, .env variable names, migration command, test command

---

## Phase 1 — Auth & User Management
Goal: All three roles exist; login/logout works; route access is enforced.

  Branch: feature/auth

  Tasks:
  - users migration with all required columns (id, name, email, password,
    created_by, updated_by, timestamps, remember_token)
  - User model with role integration via spatie/laravel-permission
  - Role seeder: create Admin, Manager, Inventory Staff roles
  - Demo user seeder: one user per role with realistic (non-fake) data
  - Login controller, LoginRequest Form Request
  - Session configuration: HttpOnly, Secure, SameSite=Lax, idle timeout
  - Login throttle middleware
  - Admin 2FA implementation (decision OQ-10 required before this task)
  - UserPolicy
  - Sidebar layout with role-filtered navigation (role cannot see what it
    cannot access — per ui-design-system.md)
  - Auth feature tests:
      - Login succeeds with valid credentials
      - Login fails with invalid credentials
      - Each role is blocked from routes it should not reach (all three roles
        tested against all module route prefixes)

  Definition of Done:
  - All auth tests pass
  - A Manager cannot reach /costing/* by direct URL
  - An Inventory Staff cannot reach /projects/* by direct URL
  - Admin 2FA is accessible from account settings

---

## Phase 2 — Project Management
Goal: Projects can be created, edited, and status-tracked with a history log.

  Branch: feature/project-management

  Prerequisite: OQ-3 (status transition graph) answered before starting

  Tasks:
  - projects migration (status enum, soft deletes, all audit columns)
  - project_status_history migration
  - Project model (soft deletes, status cast, relationships)
  - ProjectStatusHistory model
  - ProjectObserver (writes history entry on status change)
  - ProjectController (index, create, store, show, edit, update, destroy)
  - StoreProjectRequest, UpdateProjectRequest
  - ProjectService (status transition validation, history recording)
  - ProjectPolicy
  - Blade views: project list (paginated), create/edit form, detail view
  - Status badge component (pill-shaped, color+text per design system)
  - Project Management feature tests:
      - Allowed status transitions succeed
      - Disallowed status transitions are rejected
      - Status history is recorded on every change

  Definition of Done:
  - All project tests pass
  - Full test suite passes
  - An Inventory Staff cannot create or edit a project

---

## Phase 3A — Resource Allocation
Goal: Resources (materials/tools/equipment) tracked; allocations to projects
      enforced against available stock.

  Branch: feature/resource-allocation

  Prerequisite: OQ-2 (Equipment Records scope) answered before finalizing schema

  Tasks:
  - resources migration (with soft deletes, quantity, condition field if
    Equipment Records is merged here)
  - resource_allocations migration
  - Resource model, ResourceAllocation model
  - ResourceAllocationController, StoreResourceAllocationRequest
  - ResourceAllocationService (availability check, stock decrement)
  - ResourceAllocationPolicy
  - Blade views: resource list, allocation form, project resource summary
  - Resource Allocation feature tests:
      - Over-allocation is blocked
      - Stock correctly decrements on confirmed allocation

  Definition of Done:
  - All resource allocation tests pass
  - Full test suite passes
  - Inventory Staff can allocate; Manager can allocate; Admin cannot if
    that is the decided permission (depends on OQ-7)

---

## Phase 3B — Manpower Management
Goal: Employee records exist; deployments respect availability.

  Branch: feature/manpower

  Prerequisite: OQ-4 (employee availability state) answered before migration

  Tasks:
  - employees migration (with soft deletes, skillset, availability field)
  - manpower_assignments migration
  - Employee model, ManpowerAssignment model
  - ManpowerController, StoreManpowerAssignmentRequest
  - ManpowerService (availability check)
  - ManpowerPolicy (restrict to Manager role)
  - Blade views: employee list, assignment form, project manpower summary
  - Manpower feature tests:
      - Assignment respects availability state
      - Inventory Staff cannot access employee records

  Definition of Done:
  - All manpower tests pass
  - Full test suite passes
  - Employee data is inaccessible to Inventory Staff by direct URL

---

## Phase 3C — Scheduling
Goal: Project schedules (start/end dates, duration) tracked per project.

  Branch: feature/scheduling

  Prerequisite: OQ-5 (duration estimation business rules) answered

  Tasks:
  - project_schedules migration
  - ProjectSchedule model
  - SchedulingController, StoreProjectScheduleRequest
  - SchedulingService (duration/deadline logic)
  - SchedulingPolicy (Admin only)
  - Blade views: schedule form, schedule display on project detail
  - Scheduling feature tests:
      - Deadline/duration calculations are correct
      - Manager and Inventory Staff cannot edit schedules

  Definition of Done:
  - All scheduling tests pass
  - Full test suite passes

---

## Phase 4 — Project Costing
Goal: Cost line items (labor/equipment/material) computed correctly; no
      float arithmetic.

  Branch: feature/costing

  Prerequisite: OQ-11 (cost_type) answered; Phase 3A and 3B complete

  Tasks:
  - project_costs migration (cost_type, amount as decimal(12,2))
  - ProjectCost model
  - CostingController, StoreProjectCostRequest
  - CostingService (total computation using bcmath or integer arithmetic)
  - CostingPolicy (Admin only)
  - Blade views: cost line-item form, cost summary table (Roboto Mono for
    numeric columns per design system)
  - Costing unit tests (CostingServiceTest):
      - Correct total across multiple line items
      - Correct total with zero items
      - Correct total with single item
  - Costing feature tests

  Definition of Done:
  - All costing tests pass (unit + feature)
  - Full test suite passes
  - Manager and Inventory Staff cannot access costing routes

---

## Phase 5 — Reports & Analytical Dashboard
Goal: Summary views for Admin and Manager; printable/exportable reports.

  Branch: feature/reports

  Prerequisite: OQ-1 (Reports vs. Dashboard architecture), OQ-6 (export
  format and dashboard KPIs), Phases 2–4 complete

  Tasks:
  - DashboardController (or merged with ReportsController — per OQ-1)
  - ReportsController
  - ReportsService (aggregate queries; eager-load to avoid N+1)
  - Dashboard view (card-based summaries, cached counts)
  - Report views (format per OQ-6 decision)
  - ReportsPolicy, DashboardPolicy
  - Reports feature tests:
      - Report generation does not error on an empty project

  Definition of Done:
  - All reports tests pass
  - Full test suite passes
  - Dashboard loads without N+1 queries

---

## Phase 5C — Equipment Records
Goal: Inventory Staff can track tool/material condition.

  Branch: feature/equipment-records

  Prerequisite: OQ-2 resolved; Phase 3A complete

  Tasks: Depends entirely on OQ-2 resolution.
  - If merged into Resource Allocation: add condition field to resources
    migration via a new migration, add a filtered view for Inventory Staff
  - If separate module: full Controller, Service, Model, Policy, views, tests

  Definition of Done: Defined once OQ-2 is resolved.

---

## Phase 6 — Decision Support Scaffold
Goal: The scaffold exists, looks intentional, and does not crash.

  Branch: feature/decision-support-scaffold

  Prerequisite: Phases 2, 3A, 3B, 3C complete (project + its data exist)

  Tasks:
  - decision_support_requests migration
  - DecisionSupportResult DTO (DecisionSupportResult.php)
  - DecisionSupportService::generateRecommendation() — hardcoded placeholder
    only (returns not_yet_implemented status and null suggestion fields)
  - Strategies/ folder created, empty
  - DecisionSupportController (project selection + submission route)
  - DecisionSupportPolicy
  - Decision support results view: four visible "Not yet available" slots,
    styled per design system — must look intentional, not broken
  - Decision Support feature test:
      - Route returns placeholder result without error

  Definition of Done:
  - Test passes
  - Full test suite passes
  - The view clearly communicates that this feature is coming — not that
    something is broken
  - STATUS in decision-support-guardrails.md remains SCAFFOLD ONLY

---

## Phase 7 — Hardening & Pre-Demo Polish
Goal: System is demo-ready, security checklist is complete, no loose ends.

  Branch: feature/hardening (or directly to develop)

  Tasks:
  - Run full security checklist from security.md
  - Custom 404 and 500 error pages
  - Empty states on all list views (actionable, per ui-design-system.md)
  - Confirmation dialogs on all destructive actions
  - Accessibility audit: keyboard focus states, label coverage, contrast
  - Verify WCAG AA contrast on status pills (OQ-12 / C2 from audit)
  - Gzip/Brotli enabled at Nginx level
  - composer audit for known CVEs
  - Nightly backup script (mysqldump + file storage, encrypted)
  - Health-check endpoint + cron alert script
  - Final full test suite run

  Definition of Done:
  - php artisan test — all tests pass
  - Security checklist in security.md — all boxes checked
  - Capstone/demo dry-run completed without errors

---

## Milestone Summary

| Phase | Module(s) | Depends On |
|---|---|---|
| 0 | Bootstrap | OQ-8, OQ-9 answers |
| 1 | Auth | Phase 0; OQ-10 answer |
| 2 | Project Management | Phase 1; OQ-3 answer |
| 3A | Resource Allocation | Phase 2; OQ-2 answer |
| 3B | Manpower Management | Phase 2; OQ-4 answer |
| 3C | Scheduling | Phase 2; OQ-5 answer |
| 4 | Project Costing | Phases 3A, 3B; OQ-11 answer |
| 5 | Reports + Dashboard | Phases 2–4; OQ-1, OQ-6 answers |
| 5C | Equipment Records | Phase 3A; OQ-2 answer |
| 6 | Decision Support Scaffold | Phases 2, 3A, 3B, 3C |
| 7 | Hardening | All prior phases |

---

## Git Branch Strategy

  main        <- production-ready tagged releases only
    |
  develop     <- integration branch; all feature PRs merge here
    |
  feature/auth
  feature/project-management
  feature/resource-allocation
  feature/manpower
  feature/scheduling
  feature/costing
  feature/reports
  feature/equipment-records
  feature/decision-support-scaffold
  feature/hardening

Commit message format: imperative mood, one logical change per commit.
  Good:  "Add project status transition validation to ProjectService"
  Bad:   "fixes" / "wip" / "updates"

Tag format on deploy: v1.0.0, v1.1.0, etc.
