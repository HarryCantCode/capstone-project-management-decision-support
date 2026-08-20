# Architecture Audit — Dex PMS
_Audited against: md/AGENTS.md, md/architecture.md, md/coding-standards.md,
md/database.md, md/decision-support-guardrails.md, md/deployment.md,
md/security.md, md/testing.md, md/ui-design-system.md_
_Date: 2026-08-20_

---

## 1. Project Overview

| Item | Value |
|---|---|
| System name | Dex PMS (Web-Based Project Management with Decision Support) |
| Client | Dex International Co. — B2B elevator/crane engineering firm |
| Deployment | Single on-premise server, office LAN only |
| Users | ~10–50 concurrent; three roles: Admin, Manager, Inventory Staff |
| Stack | PHP 8.2+ / Laravel 11, Blade + Bootstrap 5 + Alpine.js, MySQL 8 |
| Architecture | Modular monolith |
| Dev environment | XAMPP or Laravel Sail |

---

## 2. Architecture Assessment

### 2.1 Overall Pattern

The specifications call for a modular monolith, which is correct for the
stated scale. The request flow is clearly defined:

    Route > Controller (thin) > Form Request (validation)
          > Service class (business logic) > Eloquent Model (data access)
          > Blade view (presentation)

This is sound and internally consistent. No contradiction found.

### 2.2 Folder Structure

The architecture.md folder map is well-defined and matches the Service-Layer
pattern. One gap: the Policies/ folder is listed as a directory under app/
but no policy file names are shown. Since policies are required for every
write action (absolute rule #3 in AGENTS.md), these will need to be
explicitly created for every module. This is a missing detail, not a
contradiction.

### 2.3 Module Count vs. Architecture Diagram Mismatch

CONTRADICTION FOUND (Low Severity)

The flowchart in architecture.md lists 8 modules (M1-M8), but the module
map table directly below it lists 10 modules:

| In flowchart | In module map table |
|---|---|
| Auth & RBAC | Auth & User Management |
| Project Management | Project Management |
| Resource Allocation | Resource Allocation |
| Manpower Management | Manpower Management |
| Project Costing | Project Costing |
| Scheduling | Scheduling |
| Reports | Reports |
| Decision Support | Analytical Dashboard (NOT in flowchart) |
| (missing) | Equipment Records (NOT in flowchart) |
| (missing) | Decision Support |

Analytical Dashboard and Equipment Records appear in the module table but are
absent from the flowchart. Their absence may be intentional (merged into other
modules) or an oversight. These require clarification before corresponding
controllers, services, or views are created.

### 2.4 Analytical Dashboard vs. Reports Module

AMBIGUITY FOUND

Both Reports (flowchart + module table) and Analytical Dashboard (module table
only) exist. Their responsibilities overlap:

- Reports: "Printable/downloadable summaries" for Admin, Manager.
- Analytical Dashboard: "Overview of ongoing/completed/upcoming projects" for Admin only.

It is unclear whether these are two separate pages/controllers, or whether the
Dashboard is a landing-page widget set and Reports is a printable-output system,
or whether they share a single controller with different views.

This must be decided before building either module.

### 2.5 Equipment Records Module

AMBIGUITY FOUND

Equipment Records is listed with responsibility "Tool/material condition
tracking" for Inventory Staff, but it does not appear in the architecture.md
folder structure, and no corresponding service class is mentioned. It may be a
sub-feature of Resource Allocation (which covers "Material/tool/equipment
availability and allocation") or a separate module. If separate, it needs its
own Controller, Service, Model(s), views, Policy, and tests.

---

## 3. Technology Stack Assessment

The stack is fixed per absolute rule #1 in AGENTS.md. No deviation is
permissible.

| Component | Specified | Assessment |
|---|---|---|
| Backend | PHP 8.2+ / Laravel 11 | Stable, well-supported, correct for scale |
| Templating | Blade | Correct for server-rendered monolith |
| CSS framework | Bootstrap 5 | Appropriate; aligns with mobile-first requirement |
| JS layer | Alpine.js | Lightweight; correct for progressive enhancement |
| Database | MySQL 8 via Eloquent | Correct; aligns with decimal(12,2) money requirement |
| Bundler | Vite | Explicitly required in coding-standards.md |
| Role/permission | spatie/laravel-permission | Specified in database.md |
| Activity log | spatie/laravel-activitylog | Specified in security.md |
| Code style | Laravel Pint | Specified in coding-standards.md |
| Testing | Pest OR PHPUnit | Decision required — see Section 8 |
| Dev environment | XAMPP OR Laravel Sail | Decision required — see Section 11 |
| 2FA package | Not specified | Security.md requires it for Admin; no package named |

---

## 4. System Modules Assessment

### 4.1 Auth & User Management
- Three fixed roles: Admin, Manager, Inventory Staff
- spatie/laravel-permission handles role storage
- 2FA available for Admin role — package unspecified
- Every write action requires a Laravel Policy

### 4.2 Project Management
- Status: pending | ongoing | completed (enum — not free text)
- project_status_history table tracks every status change
- Soft deletes required on Project model

AMBIGUITY: The allowed status transition graph is not defined anywhere.
Possible variants:
  - pending -> ongoing -> completed (strict linear)
  - pending -> completed (skip ongoing?)
  - ongoing -> pending (rollback?)
  - completed -> anything (re-open?)

testing.md requires testing that status transitions follow allowed paths, but
the allowed paths are not listed in any specification file.

### 4.3 Resource Allocation
- Covers materials, tools, and equipment
- Stock quantity tracking required
- Allocation blocked when quantity exceeds available stock
- Stock decrements on confirmed allocation

Potential overlap with Equipment Records module — see Section 2.5.

### 4.4 Manpower Management
- Employee records with soft deletes
- Philippine Data Privacy Act (RA 10173) applies
- Access restricted to Manager role only (per security.md)

MISSING: Employee "availability state" is mentioned in testing.md but not
defined. Is this a boolean field, an enum, or derived from active manpower
assignments?

### 4.5 Project Costing
- Line items: labor, equipment, material
- Money fields: decimal(12,2) always
- project_costs table

MISSING: cost_type values not enumerated. Are they an enum or a lookup table?

### 4.6 Scheduling
- Duration estimation and deadline tracking
- project_schedules table
- Admin access only

MISSING: Business rules for duration estimation are not defined. Is duration
manually entered by Admin, or computed from project data?

### 4.7 Reports
- Printable/downloadable summaries for Admin and Manager

MISSING: Export format not specified. Options: HTML print view, PDF (requires
a package such as barryvdh/laravel-dompdf), CSV download. Choice affects
packages required and security rules for downloads.

### 4.8 Analytical Dashboard
- Admin only
- Overview of project statuses
- Not in the flowchart or folder structure
- Metrics/KPIs beyond "overview" not defined

### 4.9 Equipment Records
- Inventory Staff only
- Condition tracking for tools and materials
- Not in folder structure or flowchart
- Relationship to Resource Allocation table undefined

### 4.10 Decision Support
- STATUS: SCAFFOLD ONLY
- Route, controller, service, DTO, and view created but return hardcoded placeholder
- Full implementation spec exists in decision-support-guardrails.md for when status changes

---

## 5. Database Architecture Assessment

### 5.1 Core Entity List (from database.md)

  users
  roles (via spatie/laravel-permission — uses its own table set)
  projects
  project_status_history
  resources (materials/tools/equipment)
  resource_allocations
  employees
  manpower_assignments
  project_costs
  project_schedules
  decision_support_requests

### 5.2 Table Coverage vs. Module List

| Module | Expected Table(s) | In Entity List? |
|---|---|---|
| Auth | users + spatie role tables | Yes |
| Project Management | projects, project_status_history | Yes |
| Resource Allocation | resources, resource_allocations | Yes |
| Manpower Management | employees, manpower_assignments | Yes |
| Project Costing | project_costs | Yes |
| Scheduling | project_schedules | Yes |
| Reports | No separate table — derived | N/A |
| Analytical Dashboard | No separate table — derived | N/A |
| Equipment Records | Unclear — possibly resources table | UNCLEAR |
| Decision Support | decision_support_requests | Yes |

### 5.3 Migration Ordering Dependencies

1. users table must be first — all other tables reference created_by/updated_by
2. spatie/laravel-permission tables must exist before role-checking code runs
3. resources before resource_allocations
4. projects before project_status_history, resource_allocations, manpower_assignments,
   project_costs, project_schedules, decision_support_requests
5. employees before manpower_assignments

### 5.4 Seeder FK Circular Dependency Risk

Every table requires created_by and updated_by foreign keys to users.
Seeders must create a seed user before any other seeder runs, or temporarily
disable foreign key constraints. This is a known technical risk.

### 5.5 Money Precision in PHP

database.md and AGENTS.md both require decimal(12,2). However, PHP's native
float arithmetic is imprecise. The CostingService must use integer math (cents),
bcmath, or a money library (e.g. brick/money) — not PHP floats — when doing
arithmetic before persisting to the DB.

---

## 6. Authentication & Authorization Assessment

### 6.1 Role Permission Map (Inferred)

| Module | Admin | Manager | Inventory Staff |
|---|---|---|---|
| Auth & User Management | Full | None | None |
| Project Management | Full | Full | None |
| Resource Allocation | ? | Full | Full |
| Manpower Management | ? | Full | None |
| Project Costing | Full | None | None |
| Scheduling | Full | None | None |
| Reports | Full | Full | None |
| Analytical Dashboard | Full | None | None |
| Equipment Records | ? | ? | Full |
| Decision Support | Full | Full | None |

AMBIGUITY: Non-primary roles marked with ? — do they have read-only access
or zero access? This must be defined before writing any Policy class.

### 6.2 2FA

security.md requires 2FA as an option for Admin. No package is specified.
Decision required before Auth module is built.

---

## 7. Security Requirements Assessment

All security.md items are clear and internally consistent.

| Requirement | Dependency |
|---|---|
| Session cookies HttpOnly + Secure + SameSite | Laravel config only |
| Login throttling | Laravel built-in throttle middleware |
| Admin 2FA | Package decision required |
| spatie/laravel-activitylog for audit | Package must be installed |
| HTTPS on internal LAN | Server setup — internal CA or self-signed cert |
| MySQL bound to localhost | Server configuration |
| File uploads outside web root | storage/app/private pattern |
| CSRF on all forms | Laravel built-in |
| Philippine Data Privacy Act | Restrict employee data to Manager role |

---

## 8. Testing Requirements Assessment

### 8.1 Framework Decision Required

testing.md states "Pest or PHPUnit — pick one and stay consistent."
No choice is made in the specifications.

- Pest: ships by default with Laravel 11, expressive syntax, modern
- PHPUnit: verbose, industry-standard, universally understood

Decision required before the first test is written.

### 8.2 Coverage Matrix

The testing matrix in testing.md maps one-to-one to all modules. No gaps found
in the matrix itself. The status transition coverage gap for Project Management
(see A3) is a downstream risk.

---

## 9. UI/UX Requirements Assessment

### 9.1 Design Tokens

Six color tokens, two type families (Inter + Roboto Mono). Fully specified.

### 9.2 WCAG AA Contrast Assessment

| Token | Hex | Ratio vs. Background | AA Normal Text (4.5:1)? |
|---|---|---|---|
| Ink | #1B1E23 | ~18:1 | Pass |
| Accent | #2C5F7C | ~5.1:1 | Pass |
| Status: Completed | #2E7D53 | ~5.4:1 | Pass |
| Status: Ongoing | #C98A2C | ~3.1:1 | FAIL |
| Status: Pending | #8A8F98 | ~3.4:1 | FAIL |

CONTRADICTION: ui-design-system.md requires WCAG AA compliance, but two of
the five specified status pill colors fail AA for normal-weight text. Since
status pills are typically small text, this is a real compliance gap that
requires a decision before UI implementation begins.

Resolution options:
  a) Darken the Ongoing and Pending hex values
  b) Use white text on the colored pill backgrounds (requires verifying white-on-color contrast)
  c) Use a border-only pill style with the specified color as border/text on white background

---

## 10. Deployment Requirements Assessment

Deployment spec is clear, internally consistent, and correctly scoped.

AMBIGUITY: "Local dev: XAMPP or Laravel Sail" — XAMPP uses native PHP; Sail
uses Docker. The choice affects developer setup instructions and the README.md.
This should be decided early.

---

## 11. Contradictions Summary

| ID | Files | Description | Severity |
|---|---|---|---|
| C1 | architecture.md | Flowchart (8 modules) vs. module table (10 modules): Analytical Dashboard and Equipment Records missing from flowchart | Low |
| C2 | ui-design-system.md, security.md | Two specified status pill colors fail the WCAG AA requirement that the same spec mandates | Medium |

---

## 12. Ambiguities Summary

| ID | Files | Description |
|---|---|---|
| A1 | architecture.md | Reports vs. Analytical Dashboard — one thing or two? |
| A2 | architecture.md, database.md | Equipment Records — separate module or part of Resource Allocation? |
| A3 | architecture.md | Project status transition graph not defined |
| A4 | database.md, testing.md | Employee availability state — field type undefined |
| A5 | architecture.md | Scheduling duration estimation — manual or computed? |
| A6 | architecture.md | Reports export format(s) not specified |
| A7 | architecture.md, security.md | Non-primary role access — read-only or zero access? |
| A8 | testing.md | Test framework — Pest or PHPUnit? |
| A9 | deployment.md | Dev environment — XAMPP or Laravel Sail? |
| A10 | security.md | 2FA package not specified |
| A11 | database.md | project_costs.cost_type — enum or lookup table? |

---

## 13. Missing Requirements Summary

| ID | Area | What Is Missing |
|---|---|---|
| M1 | All modules | Policy class list — one Policy per model, none named anywhere |
| M2 | Project Costing | Cost line-item type values not enumerated |
| M3 | Scheduling | Business rules for duration estimation not defined |
| M4 | Reports | Export format(s) not specified |
| M5 | Equipment Records | Scope, table structure, and relationship to resources undefined |
| M6 | Analytical Dashboard | KPIs and metrics beyond "overview" not defined |
| M7 | Auth | 2FA package not specified |
| M8 | Root | README.md required by coding-standards.md; does not exist yet |
| M9 | Dev workflow | Pint pre-commit hook required by coding-standards.md; setup not mentioned |

---

## 14. Technical Risks Summary

| ID | Risk | Likelihood | Impact |
|---|---|---|---|
| R1 | spatie/laravel-activitylog overhead on write-heavy tables | Low | Low |
| R2 | created_by/updated_by FK constraints cause seeder ordering issues | Medium | Medium |
| R3 | WCAG AA failure on Ongoing/Pending pill colors — visible at demo | High (known) | Medium |
| R4 | Decision Support scaffold must look finished, not broken | Medium | High |
| R5 | Equipment Records ambiguity may force refactoring of Resource Allocation | Medium | Medium |
| R6 | PHP float arithmetic used on money fields | Medium | High |
| R7 | File upload size limits not defined | Low | Medium |
| R8 | Status transition graph undefined — tests cannot be complete without it | High | High |
