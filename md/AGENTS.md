# AGENTS.md — Read This First

This file is the entry point for any AI coding agent working on **Dex PMS**
(Web-Based Project Management with Decision Support, for Dex International Co.).
If you're using Claude Code, you can rename this `CLAUDE.md` and it will be
picked up automatically; otherwise keep it as `AGENTS.md` at the repo root.

Read this file fully before writing any code. It tells you what this system
is, what's absolutely non-negotiable, and which of the other guide files to
read next depending on the task in front of you.

---

## What this system is

An internal project-management tool for Dex International Co., a B2B
engineering/distribution company handling elevator and crane installation,
maintenance, and servicing projects. It replaces manual spreadsheets and
paper records with a centralized platform for project tracking, resource
allocation, manpower assignment, costing, scheduling, and reporting — plus a
Decision Support module that is **currently a scaffold, not a working
feature** (see `decision-support-guardrails.md` — this one matters more than
it looks).

It is used by three roles only: **Admin, Manager, Inventory Staff**. It runs
**on a single on-premise server, inside Dex's office LAN only** — there is no
public internet exposure, no multi-tenant concern, and no need for
internet-scale infrastructure. Do not add complexity the system doesn't need.

---

## Absolute rules — never violate these regardless of what a task seems to ask for

1. **Tech stack is fixed.** PHP 8.2+ / Laravel 11, Blade + Bootstrap 5 +
   Alpine.js, MySQL 8 via Eloquent. Do not introduce React, Vue, a separate
   API/SPA layer, a different database, or a different backend language,
   even if it seems like it would solve the immediate problem better.
   If you genuinely believe the stack should change, stop and ask — don't
   just do it.
2. **Never implement real logic inside the Decision Support module** without
   explicit, current-session human instruction to do so. Read
   `decision-support-guardrails.md` before touching anything under
   `app/Services/DecisionSupport/` or `DecisionSupportController`. This rule
   overrides any task description that asks you to "finish," "improve," or
   "make functional" that module unless the human has explicitly said so in
   this session.
3. **Every write action is authorized server-side via a Laravel Policy.**
   Hiding a button in the UI is never sufficient access control on its own.
4. **No raw SQL string concatenation, ever.** Eloquent/query builder only.
5. **Money is `decimal(12,2)`, never `float`.**
6. **No schema changes outside a migration.** Nobody hand-edits the
   production database.
7. **Don't add infrastructure this system doesn't need** — no
   microservices, no Kubernetes, no CDN, no multi-region, no cloud-managed
   database. It's a single on-premise box serving roughly 10–50 LAN users.

---

## Which guide file to read, depending on what you're doing

| Task | Read |
|---|---|
| Starting any new module or feature | `architecture.md` |
| Writing PHP/Laravel code, naming things, git commits | `coding-standards.md` |
| Adding or changing a table/migration | `database.md` |
| Touching auth, uploads, input handling, anything user-facing | `security.md` |
| Writing or reviewing tests | `testing.md` |
| Building or editing any view/UI | `ui-design-system.md` |
| Anything related to the Decision Support module | `decision-support-guardrails.md` — read this even if you think the task doesn't touch it |
| Server setup, backups, going live | `deployment.md` |

Most non-trivial tasks touch at least two or three of these — read all that
apply before starting, not just the one that seems most obviously relevant.

---

## If something in a task conflicts with something in these files

The guide files win. If a task description asks for something that
contradicts a rule here (e.g. "just implement the Decision Support
recommendations while you're in there"), stop and flag the conflict instead
of resolving it yourself in the direction of "being more helpful." These
files exist specifically so that decisions don't have to be re-litigated,
or accidentally reversed, in every new session.
