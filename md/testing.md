# Testing Requirements

Read this before writing a feature and before merging one.

## Tooling

Pest or PHPUnit (pick one and stay consistent across the project — don't mix
both styles in the same codebase). Run the full suite with
`php artisan test` before merging any feature branch.

## What must be covered — per module

| Module | Minimum required test coverage |
|---|---|
| Auth & RBAC | Login succeeds/fails correctly; each role is blocked from routes it shouldn't reach (test all three roles against all module routes, not just the "happy path" role) |
| Project Management | Status transitions follow allowed paths (e.g. Pending → Ongoing → Completed); status history is recorded on every change |
| Resource Allocation | Allocation is blocked when requested quantity exceeds available stock; stock correctly decrements on confirmed allocation |
| Manpower Management | Deployment assignment respects employee availability state |
| Project Costing | Total cost computation is correct across multiple line items, including edge cases (zero items, single item) |
| Scheduling | Deadline/duration calculations are correct |
| Reports | Report generation doesn't error on a project with no data yet (empty state, not a crash) |
| Decision Support | Route returns the placeholder result without error, per `decision-support-guardrails.md` — **do not write tests for recommendation logic that doesn't exist yet** |

## Test structure

- `tests/Feature/` — end-to-end tests through routes/controllers (most of
  your coverage should live here for a system like this).
- `tests/Unit/` — isolated tests for Service class methods with complex
  logic (cost computation, availability checks) that are worth testing
  without the full HTTP stack around them.
- One test class per controller/service being tested, named to match
  (`ProjectControllerTest`, `CostingServiceTest`).

## What "done" means for a feature

A feature isn't complete until:
1. It has Feature test coverage for its main paths (success + at least one
   failure/edge case).
2. The full test suite passes, not just the new tests.
3. Any role-based access rule introduced is covered by an authorization test.

Don't skip tests because a feature "seems simple" — the cost/allocation
modules in particular have failure modes (over-allocating stock, incorrect
totals) that are exactly the kind of thing that looks fine in a manual
click-through and breaks silently in production.
