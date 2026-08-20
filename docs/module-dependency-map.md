# Module Dependency Map — Dex PMS
_Date: 2026-08-20_

This document maps which modules depend on which, so that development is
ordered to avoid building on foundations that do not yet exist.

---

## Legend

  [A] -> [B]  means: Module A must exist before Module B can be built
  [A] <-> [B] means: Modules A and B share data and must agree on a schema before either is finalized

---

## Layer 0 — Foundation (Build First)

These are not application modules; they are the prerequisites for everything.

  Laravel 11 project scaffold
    -> Vite configuration
    -> Bootstrap 5 + Alpine.js installed
    -> MySQL 8 database created
    -> .env configured (local dev)
    -> spatie/laravel-permission installed + migrated
    -> spatie/laravel-activitylog installed
    -> Laravel Pint installed
    -> Testing framework (Pest or PHPUnit) confirmed
    -> README.md created with setup instructions

Why first: Every module depends on the Laravel container, the DB connection,
the role system, and the activity logger.

---

## Layer 1 — Auth & User Management

  Depends on: Layer 0
  Blocks: Every other module (nothing works without auth)

  Artifacts:
    - users migration + User model
    - spatie role seeder (Admin, Manager, Inventory Staff)
    - Login controller + Form Request
    - Session configuration (HttpOnly, Secure, SameSite, idle timeout)
    - Login throttle middleware applied
    - Admin 2FA (package TBD)
    - UserPolicy
    - Auth feature tests (login success/fail, role-route blocking for all 3 roles)

  Notes:
  - The users table must exist before any other migration runs (created_by/updated_by FK)
  - All role route-blocking tests for other modules are written here, updated as routes are added

---

## Layer 2 — Project Management

  Depends on: Layer 1 (Auth — projects are owned by users)
  Blocks: Resource Allocation, Manpower Management, Project Costing,
          Scheduling, Decision Support (all attach to a project_id)

  Artifacts:
    - projects migration + Project model (with soft deletes)
    - project_status_history migration + ProjectStatusHistory model
    - ProjectController, StoreProjectRequest, UpdateProjectRequest
    - ProjectService (status transition logic)
    - ProjectPolicy
    - Project Observer (auto-write to project_status_history on status change)
    - Project Management feature tests (status transitions, history recording)

  Notes:
  - Status transition graph must be defined (open question OQ-3) before
    ProjectService can implement or test transitions
  - project_status_history observer is a clean side-effect use of the Observer
    pattern per architecture.md

---

## Layer 3A — Resource Allocation

  Depends on: Layer 1 (Auth), Layer 2 (projects)
  Shares schema with: Equipment Records (OQ-2 must be resolved)
  Blocks: Project Costing (material costs come from allocations),
          Decision Support (material suggestion reads resource stock)

  Artifacts:
    - resources migration + Resource model (with soft deletes)
    - resource_allocations migration + ResourceAllocation model
    - ResourceAllocationController, StoreResourceAllocationRequest
    - ResourceAllocationService (availability check, stock decrement)
    - ResourceAllocationPolicy
    - Resource Allocation feature tests (over-allocation blocked, stock decrements)

  Notes:
  - Equipment Records (OQ-2) must be resolved before this schema is finalized,
    to avoid a later migration that restructures the resources table

---

## Layer 3B — Manpower Management

  Depends on: Layer 1 (Auth), Layer 2 (projects)
  Blocks: Project Costing (labor costs reference employees),
          Decision Support (manpower suggestion reads employee records)

  Artifacts:
    - employees migration + Employee model (with soft deletes)
    - manpower_assignments migration + ManpowerAssignment model
    - ManpowerController, StoreManpowerAssignmentRequest
    - ManpowerService (availability check)
    - ManpowerPolicy
    - Manpower feature tests (availability state respected)

  Notes:
  - Employee availability state field type must be defined (OQ-4) before this
    migration is written
  - Philippine Data Privacy Act compliance: restrict to Manager role in Policy

---

## Layer 3C — Scheduling

  Depends on: Layer 1 (Auth), Layer 2 (projects)
  Blocks: Decision Support (timeline suggestion reads historical schedules)

  Artifacts:
    - project_schedules migration + ProjectSchedule model
    - SchedulingController, StoreProjectScheduleRequest
    - SchedulingService (duration/deadline logic)
    - SchedulingPolicy
    - Scheduling feature tests (deadline/duration calculations)

  Notes:
  - Duration estimation business rules must be defined (OQ-5) before
    SchedulingService can be implemented

---

## Layer 4 — Project Costing

  Depends on: Layer 2 (projects), Layer 3A (Resource Allocation — material costs),
              Layer 3B (Manpower — labor costs)
  No downstream module dependencies (for scaffold phase)

  Artifacts:
    - project_costs migration + ProjectCost model
    - CostingController, StoreProjectCostRequest
    - CostingService (total computation — must use bcmath, not float)
    - CostingPolicy
    - Costing unit tests (CostingServiceTest — multiple line items, zero, single)
    - Costing feature tests

  Notes:
  - cost_type values must be defined (OQ-11) before migration is written
  - Money arithmetic must never use PHP float — use integer cents or bcmath

---

## Layer 5A — Reports

  Depends on: Layer 2 (projects), Layer 3A, 3B, 3C (data to report on),
              Layer 4 (costing data)
  No downstream dependencies

  Artifacts:
    - ReportsController
    - ReportsService (query aggregation)
    - ReportsPolicy
    - Report views (format TBD — OQ-6)
    - Reports feature tests (no crash on empty project)

  Notes:
  - Export format must be decided (OQ-6) before ReportsController is built
  - If PDF: install barryvdh/laravel-dompdf or equivalent
  - If CSV: no extra package needed

---

## Layer 5B — Analytical Dashboard

  Depends on: Layer 2 (projects — status counts), Layer 4 (costing summary)
  No downstream dependencies

  Artifacts:
    - DashboardController (or merged into ReportsController — OQ-1)
    - Dashboard view (card-based summary per ui-design-system.md)
    - DashboardPolicy

  Notes:
  - Relationship to Reports module must be resolved (OQ-1) before a controller
    is created — if they are one module, creating two controllers is wasteful
  - Dashboard KPIs/metrics must be defined (OQ-6)
  - Cache expensive summary counts per coding-standards.md

---

## Layer 5C — Equipment Records

  Depends on: Layer 3A (Resource Allocation — resources table)
  Relationship to Resource Allocation: UNRESOLVED (OQ-2)

  Notes:
  - If Equipment Records is a sub-feature of Resource Allocation, no new
    controller or service is needed — only a filtered view of resources
  - If Equipment Records is a separate module, it needs its own Controller,
    Service, Policy, and views
  - This cannot be built until OQ-2 is resolved

---

## Layer 6 — Decision Support (Scaffold)

  Depends on: Layer 2 (projects — submission selects a project),
              Layer 3A (Resource Allocation — future material suggestion reads this),
              Layer 3B (Manpower — future manpower suggestion reads this),
              Layer 3C (Scheduling — future timeline suggestion reads this)

  Artifacts:
    - decision_support_requests migration
    - DecisionSupportController
    - DecisionSupportService (hardcoded placeholder return only)
    - DecisionSupportResult DTO
    - Strategies/ folder (empty — no strategy classes)
    - Decision support view (four "Not yet available" slots, styled)
    - DecisionSupportPolicy
    - Decision Support feature test (route returns placeholder without error)

  Notes:
  - No recommendation logic is implemented at this layer
  - STATUS must remain SCAFFOLD ONLY until explicitly changed in
    decision-support-guardrails.md by a human

---

## Cross-Module Shared Dependencies

  spatie/laravel-activitylog
    -> Logs status changes (Project Management)
    -> Logs costing entries (Project Costing)
    -> Logs resource allocations (Resource Allocation)
    -> Feeds the Reports and Dashboard modules as a data source

  spatie/laravel-permission
    -> Provides role checks used by every Policy class
    -> Must be installed before any Policy is written

  Laravel Policies
    -> One Policy per protected model
    -> Must exist before any controller write action is built

---

## Dependency Graph (Simplified)

  Layer 0: Foundation
    |
  Layer 1: Auth & User Management
    |
  Layer 2: Project Management
    |--Layer 3A: Resource Allocation
    |--Layer 3B: Manpower Management
    |--Layer 3C: Scheduling
         |
       Layer 4: Project Costing
         |
       Layer 5A: Reports
       Layer 5B: Analytical Dashboard
         |
       Layer 6: Decision Support (Scaffold)

  Layer 5C (Equipment Records) branches off Layer 3A
  — its position in the sequence depends on OQ-2.

---

## Open Questions Blocking Build Order

  OQ-1 (A1): Reports vs. Dashboard — one controller or two?
    Blocks: Layer 5A, 5B start

  OQ-2 (A2): Equipment Records — separate module or part of Resource Allocation?
    Blocks: Layer 3A schema finalization, Layer 5C

  OQ-3 (A3): Project status transition graph
    Blocks: Layer 2 ProjectService + tests

  OQ-4 (A4): Employee availability state field type
    Blocks: Layer 3B migration

  OQ-5 (A5): Scheduling duration estimation — manual or computed?
    Blocks: Layer 3C SchedulingService

  OQ-6 (A6 + M4 + M6): Reports export format, Dashboard KPIs
    Blocks: Layer 5A, 5B views

  OQ-7 (A7): Non-primary role access — read-only or zero?
    Blocks: All Policy classes

  OQ-8 (A8): Test framework — Pest or PHPUnit?
    Blocks: Layer 0, first test

  OQ-9 (A9): Dev environment — XAMPP or Laravel Sail?
    Blocks: Layer 0, README.md

  OQ-10 (A10): 2FA package
    Blocks: Layer 1 Auth module

  OQ-11 (A11): project_costs.cost_type — enum or lookup table?
    Blocks: Layer 4 migration
