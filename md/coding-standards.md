# Coding Standards & Web Development Best Practices

Read this before writing or reviewing any code.

## PHP / Laravel conventions

- **PSR-12** formatting, enforced via `./vendor/bin/pint` before every commit.
  Don't hand-format — run Pint and let it decide.
- **Naming:** singular model names (`Project`, not `Projects`), plural table
  names (`projects`), `snake_case` DB columns, `camelCase` PHP methods/variables,
  `StudlyCase` class names.
- **Type-hint everything** — method parameters, return types, property types.
  Laravel's ecosystem supports this fully; untyped code is a regression.
- **No logic in Blade views** beyond simple conditionals/loops over already-
  prepared data. If a view needs a computed value, compute it in the
  controller/service and pass it in — don't compute it in Blade.
- **Docblocks on Service methods** explaining *why*, not just *what* —
  especially for anything a future agent or teammate will need to trust
  without re-deriving your reasoning.

## Git workflow

- Branches: `main` (production-ready) + `develop` (integration) +
  `feature/<module-name>` (short-lived, one per feature).
- Merge to `develop` via pull request — even solo, this forces a re-read of
  your own diff before it lands.
- Commit messages: imperative mood, one logical change per commit
  (`Add resource allocation availability check`, not `fixes` or `wip`).
- Tag releases when deploying to the office server (`v1.0.0`, `v1.1.0`, …).
- `.env` is never committed. It's in `.gitignore` from the first commit.

## Database & schema

- Every schema change is a **migration**, checked into Git, with a real
  `down()` method. Nobody edits the production schema by hand.
- See `database.md` for entity-level conventions.

## Testing

- Every feature gets a corresponding `tests/Feature/*Test.php`. See
  `testing.md` for what's required per module.
- Run the full suite before merging a feature branch, not just the tests
  you think are relevant.

## Web development best practices (framework-agnostic)

**Performance**
- Eager-load relationships (`with()`) on any list view that would otherwise
  N+1 query — e.g. a project list pulling its resource allocations.
- Paginate every list/table view server-side (`paginate()`). Never load a
  full table into a Blade view.
- Index foreign keys and any column used in a frequent `WHERE`/`ORDER BY`
  (e.g. `project.status`, `resource_allocations.project_id`).
- Bundle/minify via Vite, not unbundled `<script>` tags.
- Cache expensive, rarely-changing reads (dashboard summary counts) with a
  sensible TTL rather than recomputing on every request.

**Responsive & cross-browser**
- Mobile-first, even though most usage is desktop — the system is expected
  to be reachable from mobile browsers per the original spec.
- Test on Chrome, Firefox, and Edge at minimum, plus one mobile browser.

**Accessibility (non-negotiable baseline, not a nice-to-have)**
- Semantic HTML first (`<table>`, `<button>`, `<nav>`) before reaching for ARIA.
- Every input has a real `<label>` — never placeholder-only.
- Status is always color **+** text, never color alone (colorblind users
  must be able to tell Completed/Ongoing/Pending apart).
- Visible keyboard focus states on every interactive element.

**Error handling & feedback**
- Custom 404/500 pages — never expose a raw stack trace or default
  framework error screen, in dev or prod.
- Destructive actions (deleting a resource entry, removing an employee
  record) require a confirmation step.
- Validation errors are specific ("Quantity must be greater than 0"), never
  generic ("Invalid input").
- Empty states and error states must look visibly different from each other.

**HTTP hygiene**
- Correct status codes: `200` success, `201` created, `422` validation
  failure, `403` authorization failure, `404` missing resource. Don't
  return `200` with an error buried in the response body.
- Any JSON endpoint added for Alpine.js-driven UI gets versioned under
  `/api/v1/...` from the start.
- Gzip/Brotli compression enabled at the Nginx level.

**Documentation**
- `README.md` at repo root: local setup steps, required `.env` variable
  *names* (never real secrets), how to run migrations/seeders, how to run tests.
- Tagged Git releases (or a lightweight `CHANGELOG.md`) so "what changed
  since the last checkpoint" is answerable at a glance.
