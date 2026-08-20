# Security Checklist

Read this before touching authentication, authorization, file uploads, or
any user-facing input. This system holds real project costing and personnel
data in production — treat every item below as a requirement, not a suggestion.

Use this as an actual review checklist before merging a feature that touches
any of these areas.

## Authentication & session

- [ ] Passwords hashed with bcrypt (Laravel default) — never logged or
      stored in plaintext, even temporarily.
- [ ] Login route throttled (`throttle` middleware) against repeated
      failed attempts.
- [ ] Session cookies set `HttpOnly`, `Secure`, `SameSite=Lax`.
- [ ] Idle session timeout configured (30–60 min for an office tool).
- [ ] Admin role has the option of 2FA (not required for all roles, but
      available for the role that controls account management).

## Authorization

- [ ] Every controller write action is gated by a Laravel **Policy** —
      never rely on a hidden UI element as the only access control.
- [ ] Role boundaries match the spec exactly: Inventory Staff cannot reach
      Costing routes even by direct URL, not just via hidden nav links.
- [ ] Authorization is checked server-side on every request, not assumed
      from a client-side role flag.

## Input handling

- [ ] All input validated via a Form Request class — never trust
      client-side validation alone.
- [ ] Eloquent/query builder for all DB access. If raw SQL is ever
      unavoidable, it uses parameter binding — never string concatenation.
- [ ] Blade's default `{{ }}` escaping used on all user-supplied content —
      `{!! !!}` is never used on anything a user typed or uploaded.
- [ ] CSRF token (`@csrf`) present on every form; CSRF middleware never
      disabled.

## File uploads (blueprints, progress photos, documents)

- [ ] MIME type and extension validated server-side, not inferred from
      the filename alone.
- [ ] Uploaded files stored outside the public web root, or served through
      an authenticated route rather than a direct public URL.
- [ ] File size limits enforced.

## Database

- [ ] App connects with a dedicated MySQL user with only the privileges it
      needs — never `root`, never `DROP`/`GRANT` in production.
- [ ] MySQL bound to `localhost` only — port 3306 never exposed to the LAN
      or internet.
- [ ] DB password is strong, unique, and lives only in `.env`.

## Transport security

- [ ] HTTPS enabled even on the internal LAN, via an internal CA or
      self-signed cert trusted on office machines — plaintext credentials
      on an office network are still sniffable.

## Audit & accountability

- [ ] Status changes, costing entries, and resource allocations are logged
      via spatie/laravel-activitylog — both a security control and a
      Reports Module data source.

## Data privacy

- [ ] Manpower module (employee personal/skill data) access restricted to
      roles that need it, consistent with the Philippine Data Privacy Act
      (RA 10173) — don't over-collect fields the system doesn't use.

## Infrastructure

- [ ] OS, PHP, MySQL, and Composer dependencies kept patched.
- [ ] `composer audit` run periodically for known CVEs in dependencies.
- [ ] Server physically secured (locked room/cabinet) — this is a real
      threat vector for an on-premise box, not theoretical.
- [ ] UPS in place to prevent data corruption from ungraceful power loss.

## Backups

- [ ] Nightly encrypted backup of database + file storage.
- [ ] At least one backup copy stored **off** the office premises
      (encrypted external drive taken offsite, or encrypted cloud storage).
- [ ] Restore procedure tested periodically — an untested backup is a
      hope, not a plan.

## If a task conflicts with this file

Stop and flag it rather than resolving the conflict yourself in the
direction of "getting the feature working." A security shortcut that ships
a feature faster is not a reasonable trade in this system.
