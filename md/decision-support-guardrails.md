# Decision Support Module — Guardrails

Read this before touching anything under `app/Services/DecisionSupport/`,
`DecisionSupportController`, `decision_support_requests`, or any
`decision-support/*` view — **even if the task in front of you doesn't
mention Decision Support directly.** This file exists because "finish the
module while you're in there" is exactly the kind of instruction that slips
past a task description without being an explicit human decision.

---

## STATUS: `SCAFFOLD ONLY — DO NOT IMPLEMENT RECOMMENDATION LOGIC`

This line is the source of truth. If it still says `SCAFFOLD ONLY`, no
recommendation, matching, scoring, or estimation logic gets written inside
this module, regardless of how the task is phrased. Only a human editing
this file to `APPROVED FOR IMPLEMENTATION` changes that — an agent should
never update this status line itself.

---

## What exists right now (the scaffold)

- `decision_support_requests` table: `project_id`, `input_summary`,
  `status` (`pending|processing|completed`), `output` (nullable JSON),
  `created_by`, timestamps.
- `DecisionSupportController` — route to select a project and submit it
  for analysis.
- `DecisionSupportService::generateRecommendation(Project $project): DecisionSupportResult`
  — returns a **hardcoded placeholder**:
  ```php
  new DecisionSupportResult(
      manpowerSuggestion: null,
      materialSuggestion: null,
      timelineSuggestion: null,
      operationalReadiness: null,
      status: 'not_yet_implemented',
  );
  ```
- A results view rendering each slot ("Manpower Suggestion," "Material
  Suggestion," "Timeline Suggestion," "Operational Readiness") in a visible
  "Not yet available" state, styled per `ui-design-system.md`. This must
  look intentional and finished, not broken.

## What's explicitly forbidden while STATUS is `SCAFFOLD ONLY`

- Any matching logic between project requirements and employee skillsets.
- Any comparison between estimated material needs and stock levels.
- Any timeline/duration estimation based on historical project data.
- Any "readiness" scoring logic.
- Populating the `output` JSON column with anything other than the
  placeholder shape above.
- Removing or softening the visible "Not yet available" state in the UI to
  make the feature look more complete than it is.

Small, well-intentioned steps toward "real" logic (e.g. "I'll just add
basic skill matching since it's easy") are exactly what this file is meant
to prevent. If a task seems to call for it, stop and flag the conflict
instead of proceeding.

---

## When STATUS is changed to `APPROVED FOR IMPLEMENTATION`

Use the following as the build spec. This is the same content as "Prompt B"
in the project's architecture blueprint — kept here so it lives next to the
guardrail it's gated behind.

**Required behavior:**
1. **Manpower suggestion** — match required skillset(s) (parsed from the
   submitted project summary) against `Employee` expertise/skillset and
   current availability. Return a ranked list with a one-line stated
   reason per suggestion (e.g. "Matches required electrical certification,
   currently unassigned").
2. **Material/resource suggestion** — derive estimated material
   requirements from the project's type/scope input, compare against
   current `Resource` stock levels, flag shortfalls by name and quantity short.
3. **Timeline suggestion** — estimate duration by comparing against
   completed historical projects of similar type; state which historical
   projects the estimate is based on.
4. **Operational readiness** — a single combined status ("Ready" /
   "Partially Ready — missing X" / "Not Ready") derived directly from 1–3.

**Architecture:**
- Each suggestion type is its own Strategy class
  (`ManpowerMatchStrategy`, `MaterialEstimationStrategy`,
  `TimelineEstimationStrategy`) behind a shared
  `DecisionSupportStrategyInterface`.
- `DecisionSupportService` orchestrates the three strategies and assembles
  the final `DecisionSupportResult` — it does not contain the
  matching/estimation logic itself.
- Persist the result to `decision_support_requests.output` as JSON; update
  `status` to `completed`.
- **Rule-based and explainable only — no black-box/ML model.** Every
  recommendation must trace back to a stated rule, since it needs to be
  defensible in a capstone panel and auditable by Dex's own staff.

**Required tests:**
- Fully available personnel + sufficient materials → "Ready".
- Manpower skillset shortfall → correct flag and reason.
- Insufficient material stock → correct shortfall reported.
- No comparable historical data for a project type → timeline suggestion
  degrades gracefully ("insufficient historical data"), never guesses silently.

**Non-goals even at this phase:**
- No external AI/ML API calls or third-party recommendation services.
- Don't change `DecisionSupportResult`'s public shape in a way that breaks
  the results view built during the scaffold phase.

---

## Status log

Record every status change here with a date, so the history of this
decision is visible to any future session:

- `SCAFFOLD ONLY` — set at initial build. Current.
