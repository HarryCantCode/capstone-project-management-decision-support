# Open Questions — Dex PMS
_Date: 2026-08-20_

These questions must be answered before the development phases they block
can begin. Questions are grouped by the phase they block.

DO NOT resolve these by assumption. Each answer must come from you (the
project owner), then be recorded below, then committed to Git.

---

## Blocking Phase 0 (Bootstrap)

### OQ-8 — Test Framework Choice

Specification (testing.md): "Pest or PHPUnit — pick one and stay consistent."
No choice is made in the spec.

Options:
  a) Pest — ships by default with Laravel 11, expressive syntax, modern;
     fewer lines of code for the same coverage
  b) PHPUnit — more verbose, industry-standard, XML-driven configuration

Impact: Every test file in the project is written in the chosen style.
Mixing styles is explicitly forbidden by testing.md.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

### OQ-9 — Local Development Environment

Specification (deployment.md): "Local dev: XAMPP or Laravel Sail."
No choice is made in the spec.

Options:
  a) Laravel Sail — Docker-based; consistent environment across machines;
     requires Docker Desktop installed on Windows; slightly more setup
  b) XAMPP — native PHP on Windows; simpler to start; less consistent
     between machines; no containerization

Impact: README.md setup instructions and all developer onboarding docs
depend on this choice.

Note: On Windows, Sail requires WSL2 + Docker Desktop. XAMPP works natively.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 1 (Auth)

### OQ-10 — 2FA Package for Admin Role

Specification (security.md): "Admin role has the option of 2FA."
No package is specified.

Options:
  a) pragmarx/google2fa-laravel — TOTP-based (Google Authenticator app)
  b) laravel/fortify — Laravel's own auth/2FA scaffold (more opinionated,
     but includes 2FA built in; may add more than needed for this system)
  c) antonioribeiro/google2fa — lower-level package; more manual wiring

Impact: Affects how the login flow is structured for Admin users.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 2 (Project Management)

### OQ-3 — Project Status Transition Graph

Specification (architecture.md, testing.md): Status values are
pending | ongoing | completed. testing.md requires testing that "status
transitions follow allowed paths" — but the allowed paths are not defined
in any specification file.

Please confirm which transitions are permitted:

  pending -> ongoing?    YES / NO
  ongoing -> completed?  YES / NO
  pending -> completed?  YES / NO  (skip ongoing — is this ever valid?)
  ongoing -> pending?    YES / NO  (rollback — e.g. project suspended?)
  completed -> ongoing?  YES / NO  (re-open — e.g. warranty work?)
  completed -> pending?  YES / NO

Impact: ProjectService transition validation and all status transition
tests depend on this answer.

YOUR ANSWER (mark each YES or NO):
  pending -> ongoing:   YES
  ongoing -> completed: YES
  pending -> completed: NO
  ongoing -> pending:   NO
  completed -> ongoing: NO
  completed -> pending: NO

NOTE: Only the two standard linear transitions are permitted.
RECORDED BY: Harry DATE: 2026-08-20

---

## Blocking Phase 3A (Resource Allocation Schema)

### OQ-2 — Equipment Records: Separate Module or Part of Resource Allocation?

Specification (architecture.md module table): Equipment Records is listed as
a separate module with responsibility "Tool/material condition tracking" for
Inventory Staff. However, it does not appear in the folder structure, and
Resource Allocation already covers "Material/tool/equipment availability and
allocation."

Options:
  a) Equipment Records is a filtered view of the resources table — no
     separate module. A condition field is added to resources; Inventory
     Staff sees a filtered view showing condition tracking. One controller,
     one service, one table.
  b) Equipment Records is a fully separate module — its own Controller,
     Service, Model (or extended model), Policy, views, and tests.

Impact: This determines whether the resources table needs a separate
restructuring migration later, and whether a new controller is needed.
Must be resolved before Phase 3A schema is finalized.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 3B (Manpower Management Schema)

### OQ-4 — Employee Availability State

Specification (testing.md): "Deployment assignment respects employee
availability state." The field itself is not defined in any spec.

Options:
  a) Boolean: is_available (true/false) — simple but cannot distinguish
     between "on leave," "assigned," or "on bench"
  b) Enum: available | assigned | on_leave | inactive — more granular;
     allows future filtering
  c) Derived: availability is computed from active manpower_assignments
     rather than stored — no explicit field; availability = employee has
     no active assignment on the requested dates

Impact: Determines the employees migration schema and ManpowerService logic.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 3C (Scheduling Service)

### OQ-5 — Scheduling: Duration Estimation — Manual or Computed?

Specification (architecture.md): Scheduling responsibility is "Duration
estimation, deadline tracking" for Admin. No business rules are defined.

Options:
  a) Manual entry only — Admin enters start date, end date, and/or
     estimated duration in days directly. The system stores and displays
     it but does not compute anything.
  b) Computed from project data — the system estimates duration based on
     something (scope, resource count, historical comparisons). However,
     historical comparison logic is explicitly part of the Decision Support
     module (timeline suggestion), which is STATUS: SCAFFOLD ONLY.

Note: If (b) is chosen, the computation logic cannot be implemented until
Decision Support is approved. This means Scheduling would be a partial
feature until then.

Recommended: Option (a) for now — manual entry — so Scheduling can be
completed independently of Decision Support.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 4 (Project Costing Schema)

### OQ-11 — project_costs.cost_type — Enum or Lookup Table?

Specification (database.md): "Status fields are enums or lookup tables,
never free-text strings." cost_type is not a status field per se, but the
same principle applies.

The specification lists labor, equipment, and material as the three cost
categories.

Options:
  a) Enum column — cost_type ENUM('labor','equipment','material')
     Pro: Simple; enforced at DB level; no join needed
     Con: Adding a new cost type requires a schema migration
  b) Lookup/reference table — cost_types(id, name, label)
     Pro: New cost types added without schema change
     Con: Adds a join on every costing query; more moving parts

Impact: Determines the project_costs migration schema.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking Phase 5 (Reports & Dashboard)

### OQ-1 — Reports vs. Analytical Dashboard: One Module or Two?

Specification (architecture.md): Both modules are listed in the module
table but serve different described purposes:
  - Reports: "Printable/downloadable summaries" — Admin + Manager
  - Analytical Dashboard: "Overview of ongoing/completed/upcoming projects" — Admin only

Options:
  a) Two separate modules — separate Controller, Service, Policy, and views
     for each. Reports = downloadable documents. Dashboard = screen-based
     summary with cards/charts.
  b) One module — a single ReportsController handles both the dashboard
     view and the printable/downloadable output. Different views, same
     controller and service.

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

### OQ-6A — Reports Export Format

Specification (architecture.md, testing.md): Reports are
"printable/downloadable summaries." Format not specified.

Options:
  a) HTML print view only — styled for printing via CSS print media query;
     no package required
  b) PDF download — requires a package (e.g. barryvdh/laravel-dompdf);
     more polished for a capstone demo
  c) CSV download — appropriate for data-heavy financial reports; no package
  d) Multiple formats — HTML print + PDF + CSV; most flexible; most effort

YOUR ANSWER: PDF download — via barryvdh/laravel-dompdf
RECORDED BY: Harry DATE: 2026-08-20

---

### OQ-6B — Analytical Dashboard KPIs

Specification (architecture.md): Dashboard shows "overview of
ongoing/completed/upcoming projects." No specific metrics defined.

Please confirm which of the following should appear on the dashboard,
or add your own:

  [ ] Count of projects by status (Pending / Ongoing / Completed)
  [ ] Projects with nearest upcoming deadlines
  [ ] Total budget committed across active projects
  [ ] Resource stock levels / low-stock alerts
  [ ] Recent activity log (last N status changes)
  [ ] Manpower utilization summary
  [ ] Other: _______________

YOUR ANSWER: _______________
RECORDED BY: _______________ DATE: _______________

---

## Blocking All Policy Classes

### OQ-7 — Non-Primary Role Access: Read-Only or Zero Access?

Specification (architecture.md module table): Each module lists "Primary
role(s)" but does not define what non-listed roles can do.

Example: Project Costing lists Admin as the primary role. Can a Manager
view (but not edit) costing data? Or is it a hard 403 for Manager on all
costing routes?

Please define access for each module's non-primary roles:

  Project Management (Admin + Manager primary):
    Inventory Staff: Read-only view? OR Hard 403?  ___

  Resource Allocation (Manager + Inventory Staff primary):
    Admin: Read-only view? OR Hard 403?  ___

  Manpower Management (Manager primary):
    Admin: Read-only view? OR Hard 403?  ___
    Inventory Staff: Hard 403 (assumed — employee data privacy)?  ___

  Project Costing (Admin primary):
    Manager: Read-only view? OR Hard 403?  ___
    Inventory Staff: Hard 403?  ___

  Scheduling (Admin primary):
    Manager: Read-only view? OR Hard 403?  ___
    Inventory Staff: Hard 403?  ___

  Equipment Records (Inventory Staff primary):
    Admin: Read-only view? OR Hard 403?  ___
    Manager: Read-only view? OR Hard 403?  ___

RECORDED BY: _______________ DATE: _______________

---

## Resolved Contradictions Requiring Your Sign-Off

### OQ-12 — WCAG AA Status Pill Colors (Contradiction C2)

Finding (architecture-audit.md Section 9.2): Two of the five specified
color tokens fail the WCAG AA contrast requirement (4.5:1 for normal text)
that the same specification (ui-design-system.md) mandates.

  Status: Ongoing  #C98A2C — ratio ~3.1:1 — FAILS
  Status: Pending  #8A8F98 — ratio ~3.4:1 — FAILS

Resolution options:
  a) Darken both hex values to meet 4.5:1:
       Ongoing:  #A06B0A (approximately — needs exact verification)
       Pending:  #5E6370 (approximately — needs exact verification)
  b) Keep the hex values as specified; use white text on a filled pill
     background instead of the background token
  c) Use border-only pill style: colored border + matching text on white
     background (no fill)

I will not change the token values without your explicit approval.

YOUR ANSWER: Darken the two failing hex values to meet 4.5:1 ratio.
  Approved corrected tokens:
    Status: Ongoing -> #8A5A00 (darkened from #C98A2C)
    Status: Pending -> #5E6370 (darkened from #8A8F98)
RECORDED BY: Harry DATE: 2026-08-20

---

## Answer Log

| OQ | Topic | Answer | Date | Answered By |
|---|---|---|---|---|
| OQ-8 | Test framework | Pest | 2026-08-20 | Harry |
| OQ-9 | Dev environment | Laravel Sail | 2026-08-20 | Harry |
| OQ-10 | 2FA package | pragmarx/google2fa-laravel | 2026-08-20 | Harry |
| OQ-3 | Status transition graph | pending->ongoing, ongoing->completed only | 2026-08-20 | Harry |
| OQ-2 | Equipment Records scope | Sub-feature of Resource Allocation | 2026-08-20 | Harry |
| OQ-4 | Employee availability state | Boolean is_available | 2026-08-20 | Harry |
| OQ-5 | Scheduling duration | Manual entry only | 2026-08-20 | Harry |
| OQ-11 | cost_type field type | Lookup table (cost_types) | 2026-08-20 | Harry |
| OQ-1 | Reports vs Dashboard | Two separate modules | 2026-08-20 | Harry |
| OQ-6A | Reports export format | PDF via barryvdh/laravel-dompdf | 2026-08-20 | Harry |
| OQ-6B | Dashboard KPIs | TBD at Phase 5B | 2026-08-20 | Harry |
| OQ-7 | Non-primary role access | Hard 403 everywhere | 2026-08-20 | Harry |
| OQ-12 | WCAG contrast resolution | Darken tokens: Ongoing #8A5A00, Pending #5E6370 | 2026-08-20 | Harry |
