# UI / UX Design System

Read this before building or editing any Blade view.

## Design direction

Clean, minimalist, grounded in what Dex actually does — elevators, cranes,
technical installation work, blueprints. A quiet, precise "technical
drawing" feel, not a generic SaaS dashboard template. Every element earns
its place; this is a tool people use all day, not a marketing page.

## Tokens — use exactly these

**Color**
| Token | Hex | Use |
|---|---|---|
| Background | `#F7F8FA` | Page background |
| Ink | `#1B1E23` | Body text |
| Accent | `#2C5F7C` | Primary actions, links |
| Status: Completed | `#2E7D53` | Status pill |
| Status: Ongoing | `#C98A2C` | Status pill |
| Status: Pending | `#8A8F98` | Status pill |

One accent color only. Don't add a second "brand" color for variety.

**Typography**
- UI/body text: Inter or the system sans-serif stack — chosen for density
  and readability in tables, not display flourish.
- Numeric/ID/cost data: a monospace face (e.g. Roboto Mono) — improves
  scanability of tabular numbers and project codes.

**Layout**
- Grid-based, generous whitespace.
- Card-based dashboard summaries; dense, right-aligned tables for
  financial/quantity data.
- Sidebar navigation grouped by module, filtered to the current user's role
  — a role that can't access a module doesn't see it rendered in the nav,
  full stop, not just visually deprioritized.
- Status badges: pill-shaped, colored per the table above, used
  consistently everywhere a project status appears.

## Interaction & content rules

- Buttons say exactly what they do: "Save Project," not "Submit."
- Empty states are actionable: "No projects yet — Create your first
  project," never a generic illustration with no next step.
- Validation errors appear inline, next to the field, in plain language —
  not only as a banner at the top of the page.
- Minimal animation — a subtle transition on modals/dropdowns is enough.
  Avoid anything that reads as decorative motion in a daily-use tool.
- Status is always color **+** text together, never color alone.

## Accessibility floor — non-negotiable

- Color contrast meets WCAG AA against the background token above.
- Every interactive element has a visible keyboard focus state.
- Every form field has a real `<label>`, never placeholder-only.
- Responsive down to tablet width at minimum.

## What to avoid

- Generic dashboard templates with irrelevant stock icons or illustrations.
- Unnecessary gradients, shadows, or decorative flourishes not called for
  by the tokens above.
- A second accent color, a second display typeface, or any "just this once"
  deviation from the token table — consistency across ~10 modules matters
  more than any single screen looking slightly nicer in isolation.
