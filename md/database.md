# Database Conventions

Read this before creating or modifying any migration or model.

## Rules for every table

- **Third normal form** for transactional tables — no repeating groups, no
  derived columns stored redundantly (compute totals in a service, don't
  store a stale copy unless it's explicitly a cache with an invalidation plan).
- Every table gets: `id` (unsigned bigint, auto-increment), `created_at` /
  `updated_at`, and `created_by` / `updated_by` foreign keys to `users`.
- **Soft deletes** (`deleted_at`) on `Project`, `Resource`, and `Employee` —
  this system's whole purpose is record-keeping; don't hard-delete
  operational history.
- **Status fields are enums or lookup tables, never free-text strings.**
  `project.status` is constrained to `pending | ongoing | completed`.
- **Foreign keys enforced at the DB level**, not just in application code.
- **Money fields are `decimal(12,2)`.** Never `float`, never a string.
- Index every foreign key and any column used in a frequent `WHERE` or
  `ORDER BY`.

## Core entities to plan around

`users`, `roles` (via spatie/laravel-permission), `projects`,
`project_status_history`, `resources` (materials/tools/equipment),
`resource_allocations`, `employees`, `manpower_assignments`, `project_costs`
(labor/equipment/material line items), `project_schedules`, and
`decision_support_requests` (see `decision-support-guardrails.md` — this
table is scaffolded now, not fully used yet).

## Migrations

- Every migration implements a real, working `down()` method. An empty
  `down()` is a bug, not a shortcut.
- One migration per logical schema change — don't bundle unrelated table
  changes into a single migration file.
- Seeders provide realistic demo data for every module — used for both
  development and defense/demo purposes. Don't seed obviously fake
  placeholder text ("asdf", "test123") into a system meant to look
  production-ready in a demo.

## Queries

- Eloquent / query builder only. No raw SQL string concatenation, ever —
  this is a security rule as much as a style rule (see `security.md`).
- Eager-load relationships on any list/index query to avoid N+1 queries —
  see `coding-standards.md` for the performance rationale.
