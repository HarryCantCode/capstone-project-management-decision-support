---
name: senior-dev-validator
description: >-
  Validates, tests, and refines code changes following senior software developer standards before reporting completion to the user.
  Enforces automated test runs in containers, database migration synchronization, transactional consistency audits, and guardrail compliance.
---

# Senior Developer Output Validation & Refinement Skill

This skill enforces a mandatory **Verification and Self-Refinement Gate** for any code changes, bug fixes, or feature additions in **Dex PMS**.

Senior software engineering standards dictate that code is **never delivered on assumptions**. Code must be executed, tested, checked against database states, and verified against system guardrails before presenting work to the user.

---

## The Verification & Refinement Lifecycle

Whenever you modify application code, migrations, or configurations, you must execute the following 5-stage verification pipeline:

```
┌────────────────────────────────────────────────────────┐
│ 1. Static & Schema Sanity Check                        │
│    - Model $fillable & casts() match migrations        │
│    - Policy & FormRequest alignment                    │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ 2. Database Migration Execution Check                  │
│    - Run `php artisan migrate` in Docker container     │
│    - Confirm all new schema columns are physically applied│
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ 3. Automated Test Suite Execution                      │
│    - Run Pest/PHPUnit tests inside the container       │
│    - Ensure 100% of affected feature tests PASS        │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ 4. Concurrency & Robustness Audit                      │
│    - Verify DB::transaction() on multi-row writes      │
│    - Check lockForUpdate() on finite resources         │
│    - Eliminate race conditions (no count() + 1)        │
└──────────────────────────┬─────────────────────────────┘
                           │
                           ▼
┌────────────────────────────────────────────────────────┐
│ 5. The Refinement Loop                                 │
│    - If ANY check fails: Fix root cause -> Re-test     │
│    - Never deliver until all checks are green          │
└────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Validation Procedure

### Step 1: Database Migration Synchronization
Whenever a new migration is created or altered:
1. **Execute the migration immediately in the running Docker container:**
   ```bash
   docker exec projectmangementsystem-capstone-laravel.test-1 php artisan migrate
   ```
2. Verify output confirms `DONE` without errors.
3. Check `php artisan migrate:status` to ensure no migrations are left pending (`Ran? NO`).

### Step 2: Automated Test Execution (Pest)
Never declare a task finished without running automated tests:
1. Run the specific test file covering the modified feature:
   ```bash
   docker exec projectmangementsystem-capstone-laravel.test-1 ./vendor/bin/pest tests/Feature/<TestFile>.php
   ```
2. If changing shared components (Auth, User, Projects, Resources), run related suites:
   ```bash
   docker exec projectmangementsystem-capstone-laravel.test-1 ./vendor/bin/pest tests/Feature/AuthTest.php
   docker exec projectmangementsystem-capstone-laravel.test-1 ./vendor/bin/pest tests/Feature/ProjectManagementTest.php
   ```
3. **If tests fail:**
   - Read the exact assertion error and stack trace.
   - Trace the issue back to domain logic, order of operations, or fixtures.
   - Refine the code.
   - **Re-run the test** until all assertions pass.

### Step 3: Guardrail Compliance Check (md/robustness-guardrails.md)
Inspect your diff against the repository's core guardrails:
* **Transactional Integrity:** Are multi-row or multi-table updates (e.g. cascading statuses, bulk reassignments) wrapped in `DB::transaction()`?
* **Pessimistic Locking:** Are inventory decrements or capacity allocations protected with `lockForUpdate()`?
* **Atomic IDs/Codes:** Are sequential numbers or project codes generated atomically with retry logic instead of `count() + 1`?
* **Defensive Security:**
  - Zero unauthenticated public routes (no `/run-migrations` or debug routes).
  - No destructive account lockouts (`$user->delete()` is forbidden on failed logins; use progressive timers).
  - TOTP 2FA secrets and sensitive tokens are cast to `'encrypted'` in Eloquent models.
  - Temporary passwords are cryptographically random seeds, requiring a first-login password rotation.
* **Architectural Boundaries:** Controllers remain thin orchestrators (≤ 20 lines per action); business logic lives in Domain Services (`app/Services/`).
* **Policy Authorization:** Mutating actions are checked via `$this->authorize('action', $model)`.

### Step 4: Code Style & Quality Check
Ensure code adheres to PSR-12 and Laravel standards:
```bash
docker exec projectmangementsystem-capstone-laravel.test-1 ./vendor/bin/pint --test
```
If formatting errors are flagged, run Pint to automatically format:
```bash
docker exec projectmangementsystem-capstone-laravel.test-1 ./vendor/bin/pint
```

---

## The Refinement Protocol: Handling Inconsistencies

When inconsistencies, edge cases, or test failures arise during validation:

1. **Distinguish Bug vs Outdated Expectation:**
   - If application code violates intended business rules, fix the application code.
   - If an existing test had hardcoded assumptions that conflict with the newly requested feature (e.g. testing for an old destructive lockout message when the user requested a 5-minute timer), update the test assertions to reflect the approved specification.
2. **Iterative Re-Verification:**
   - Apply fixes.
   - Re-run the tests.
   - Repeat until the suite passes cleanly without regressions.
3. **Document in Walkthrough:**
   - In your final response and `walkthrough.md`, explicitly state what tests were run, the commands executed, and the exact verification results.
