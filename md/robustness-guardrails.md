# System Robustness & Senior-Level Engineering Guardrails

**Target:** Dex PMS (Web-Based Project Management System with Decision Support)  
**Audience:** All engineers, AI coding agents, and technical reviewers.  
**Objective:** Bridge the gap between prototype/capstone code and senior-level production standards. Ensure zero data corruption, zero unauthenticated attack surfaces, bulletproof concurrency, and strict architectural discipline.

---

## The Senior Developer Standard (TL;DR)

1. **Every multi-row or multi-table mutation runs in an atomic `DB::transaction()`.** A failure halfway through must never leave orphaned or partially updated records.
2. **Never generate IDs or business codes with `count() + 1`.** Use atomic database sequences, locking, or query-retry loops to prevent race condition crashes.
3. **Never soft-delete or destroy user accounts on failed logins.** Account lockout must be time-based (`RateLimiter`), not destructive.
4. **Controllers are thin HTTP adapters (≤ 20 lines per action).** Zero business logic, zero raw multi-model updates, zero inline validation queries in controllers.
5. **Every mutating action is guarded by a Laravel Policy.** Never rely solely on route middleware strings (`role:Admin`) or hidden UI buttons.
6. **All sensitive tokens and secrets are encrypted at rest.** TOTP secrets, API credentials, and sensitive PII must use Laravel's `'encrypted'` model cast.
7. **Zero unauthenticated utility routes in production.** No `/run-migrations`, no debug test routes, no backdoors.

---

## 1. Concurrency, Race Conditions & Transaction Safety

### 1.1 Atomic Multi-Record Mutations (`DB::transaction`)
Any operation that updates more than one table, loops over relationships, or updates parent-child records must be wrapped in `DB::transaction()`.

* **Forbidden:**
  ```php
  // BAD: Partial failure leaves half the records updated
  $project->update(['status' => 'completed']);
  foreach ($project->personnel as $person) {
      $person->update(['project_id' => null]);
      ProjectPersonnelHistory::create([...]);
  }
  ```
* **Required:**
  ```php
  // GOOD: All-or-nothing rollback on any exception
  DB::transaction(function () use ($project, $user, $now) {
      $project->update(['status' => 'completed', 'updated_by' => $user->id]);

      foreach ($project->personnel()->lockForUpdate()->get() as $person) {
          $person->update(['project_id' => null, 'updated_by' => $user->id]);
          ProjectPersonnelHistory::where('project_id', $project->id)
              ->where('personnel_id', $person->id)
              ->whereNull('released_at')
              ->update(['released_at' => $now, 'released_by' => $user->id]);
      }
  });
  ```

### 1.2 Pessimistic Row Locking on Finite Resources
When reading a record to check availability before decrementing (e.g. inventory stock, equipment assignment, seat limits), you **must lock the row** using `lockForUpdate()` inside a transaction.
* Follow the gold-standard pattern established in `ResourceAllocationService`:
  ```php
  DB::transaction(function () use ($resourceId, $quantity) {
      $resource = Resource::query()->lockForUpdate()->findOrFail($resourceId);
      if ($resource->quantity_available < $quantity) {
          throw ValidationException::withMessages(['quantity' => 'Insufficient stock.']);
      }
      $resource->decrement('quantity_available', $quantity);
  });
  ```

### 1.3 Atomic Sequence & Code Generation (Eliminate `count() + 1`)
Using `Model::count() + 1` to generate codes (like `DEX-YYYY-NNN` or `EMP-YYYYXXX`) creates severe race conditions under concurrent requests.
* **The Rule:**
  - Sequence generation must be derived from the highest existing numeric identifier via an atomic query or database sequence table.
  - The database must enforce a `UNIQUE` index.
  - Creation logic must handle duplicate key exceptions (`QueryException`) with a deterministic retry mechanism (up to 3 attempts).

---

## 2. Security & Threat Modeling (Defense-in-Depth)

### 2.1 Zero Unauthenticated Maintenance Endpoints
* **Never** expose artisan commands, migrations, seeders, or debug endpoints via HTTP routes (e.g., `Route::get('/run-migrations')`).
* All schema migrations and maintenance routines must execute exclusively via CLI (`php artisan migrate`) or automated deployment pipelines.

### 2.2 Non-Destructive Account Lockout & Rate Limiting
* Failed login attempts must **never** call `$user->delete()`. An unauthenticated attacker knowing an email or account number can abuse this to permanently de-activate employee and administrator accounts (Denial of Service).
* **The Progressive Lockout Standard:**
  - **5 failed attempts:** Temporary account lockout for **5 minutes** (`locked_until = now()->addMinutes(5)`).
  - **7 failed attempts:** Extended temporary lockout for **30 minutes** (`locked_until = now()->addMinutes(30)`).
  - **Admin Activation Bypass:** Administrators can immediately unlock and restore access to any locked account at any time via the User Management panel (`POST account/users/{user}/unlock`), resetting `failed_login_attempts = 0` and `locked_until = null`.
  - Never clear the `RateLimiter` key when failed attempts exceed thresholds.

### 2.3 Cryptography & Sensitive Column Casting
* Any TOTP secret, OAuth token, or confidential third-party credential stored in the database must use the `'encrypted'` cast:
  ```php
  protected function casts(): array
  {
      return [
          'two_factor_secret' => 'encrypted',
          'password' => 'hashed',
      ];
  }
  ```
* Never log, flash into session messages, or print unmasked credentials or passwords.

### 2.4 Credential Provisioning & First-Login Reset
* Default passwords must never follow predictable patterns based on public information (e.g., `@` + `lastname` + `DEX` + `birthyear`).
* If an administrator creates an account on behalf of a user:
  1. Generate a cryptographically random temporary password (`Str::password(16)`).
  2. Set a flag `must_change_password = true`.
  3. Enforce a redirect to a password-change screen upon initial login before any application dashboard can be accessed.

---

## 3. Architecture & Separation of Concerns

```
┌────────────────────────────────────────────────────────┐
│                   HTTP Request                         │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│         Route Middleware (auth, role, 2fa)             │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│   Form Request (Validation & Authorization Gate)       │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│   Controller (Orchestrator: calls Service & returns)   │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│   Domain Service (Business Logic, Transactions, Events)│
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│   Eloquent Models / Database (Storage & Observers)     │
└────────────────────────────────────────────────────────┘
```

### 3.1 Strict Controller Responsibilities
Controllers must remain thin orchestrators. A controller method must **only**:
1. Check authorization via `$this->authorize('action', $model)`.
2. Extract validated data from a dedicated `FormRequest`.
3. Call the appropriate Domain Service.
4. Return an HTTP response, redirect, or view.

**Prohibited in Controllers:**
* Multi-table queries, raw updates, and loops modifying models.
* Direct transaction management (`DB::transaction`).
* Inline `$request->validate([...])` for complex write actions (use dedicated Form Request classes).

### 3.2 Domain Service Responsibilities
* All business rules live in `app/Services/`.
* If a domain has business logic across multiple controllers (e.g. assigning manpower from both Project view and Scheduling view), that logic **must** live in a service (e.g., `ProjectService` or `ManpowerService`), never duplicated in controllers.

### 3.3 Strict Policy Enforcement
* Every create, update, delete, transition, and allocate action must be authorized by a Laravel Policy:
  ```php
  $this->authorize('update', $project);
  $this->authorize('allocate', $resource);
  ```
* Controller middleware (`role:Admin`) is an outer perimeter check; Policies are the granular, unit-testable authorization layer. Both must be present.

### 3.4 Single Source of Truth for Audit Logging
* Do not duplicate audit logging across conflicting mechanisms.
* **Spatie Activitylog (`activity_log` table):** Primary system-wide audit trail for all model field updates, user actions, and security events.
* **Domain Lifecycle History (`project_status_histories`):** Reserved **strictly** for project status lifecycle transitions (`pending` → `ongoing` → `completed` → `delayed`). Do not pollute this table with arbitrary field updates (name changes, price edits).

---

## 4. Performance & Scalability (LAN / On-Premise Optimization)

### 4.1 N+1 Query Elimination
* Any controller method returning a list or paginated view must eager-load relationships:
  ```php
  $projects = Project::query()
      ->with(['creator', 'statusHistory.changedBy', 'personnel'])
      ->paginate(15);
  ```
* Use `Model::preventLazyLoading(! app()->isProduction())` in local development to automatically catch N+1 regressions during testing.

### 4.2 Database Indexing Guidelines
* Every foreign key column must have an index.
* Every column used in `where()`, `whereBetween()`, or `orderBy()` in search filters must be indexed (e.g., `status`, `project_code`, `incurred_date`).
* Composite indexes must match the query filter order (e.g., `['project_id', 'created_at']`).

---

## 5. Testing & Quality Assurance Standards

### 5.1 The Testing Pyramid
Every feature or refactoring must satisfy the following minimum test matrix:

| Test Type | Location | What Must Be Tested |
|---|---|---|
| **Unit Tests** | `tests/Unit/` | Domain calculations (budget remaining, spend percentage), string/code generators, state-machine transitions in isolation without hitting HTTP layers. |
| **Feature Tests** | `tests/Feature/` | Full HTTP request-response cycle, Form Request validation rules (pass/fail), unauthorized access (403), unauthenticated access (redirect to login). |
| **Concurrency / Integrity Tests** | `tests/Feature/` | Verifying that transactions roll back completely on exceptions and that stock cannot drop below zero under concurrent allocations. |

### 5.2 Test Requirements Before Merging
* Zero failing tests (`php artisan test` or `./vendor/bin/pest`).
* Strict PSR-12 formatting verified via `./vendor/bin/pint --test`.
* No dead, commented-out code, or temporary debug endpoints left in branch diffs.
* Mandatory execution of the output validation and refinement pipeline in [`.agents/skills/senior-dev-validator/SKILL.md`](file:///c:/Coding%20projects/Project%20mangement%20system%20-%20capstone/.agents/skills/senior-dev-validator/SKILL.md).

---

## 6. Senior Engineering Code Review Checklist

Before approving any pull request or declaring a module complete, verify every checkbox:

- [ ] **Transactions:** Are multi-row or multi-table updates wrapped in `DB::transaction()`?
- [ ] **Concurrency:** Are stock/resource checks guarded by `lockForUpdate()`?
- [ ] **Race Conditions:** Are unique codes/IDs generated without naive `count() + 1`?
- [ ] **Security:** Is the route free of unauthenticated backdoors (`/run-migrations`, debug routes)?
- [ ] **Account Safety:** Is account lockout non-destructive (no `$user->delete()`)?
- [ ] **Encryption:** Are TOTP secrets and tokens encrypted in the model cast?
- [ ] **Authorization:** Is there an explicit `$this->authorize(...)` call matching a Policy?
- [ ] **Validation:** Is input validated through a dedicated `FormRequest`?
- [ ] **Controller Thinness:** Is business logic kept inside a Service class?
- [ ] **Audit Trail:** Are model changes logged without duplicate or conflicting tables?
- [ ] **Performance:** Are all eager-loaded relationships free of N+1 queries?
- [ ] **Testing:** Are there both Unit tests (logic) and Feature tests (HTTP/Auth) covering the changes?
