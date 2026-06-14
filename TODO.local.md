# SecureIT local TODOs

These notes are intentionally outside the published shared-host prototype surface.

## Tenant functional-area scoring

Goal: replace placeholder status badges and score placeholders on customer tenant pages with real calculated values.

### Investigate
- Find the actual Maester run output structure used by SecureIT for published tenant reports.
- Confirm whether per-test metadata already includes categories, tags, control families, workload/service labels, or any other grouping hints.
- Check whether the website-published `summary.json` / `results.json` artifacts include enough information to derive per-area scoring.

### If categorisation already exists
- Map existing result metadata into these 8 customer-facing functional areas:
  1. Identity & Access Management
  2. Email & Calendaring
  3. Collaboration & Communication
  4. Files, Intranet & Content Management
  5. Endpoint & Device Management
  6. Security Operations & Threat Protection
  7. Compliance, Governance & Data Protection
  8. Productivity, Automation & AI
- Define scoring rules for each area.
- Define badge thresholds for Healthy / Watch / Needs attention.

### If categorisation does not already exist
- Create a stable mapping layer from test names / IDs into the 8 functional areas.
- Store the mapping outside the published site source if it is only for internal build/reporting logic.
- Keep the customer-facing labels clean and business-readable.

### Output goal
- Per-area score
- Per-area status badge
- Deterministic, repeatable calculation from actual test results
- Reusable across all onboarded tenants

## GitHub workflow trigger from tenant page

Goal: allow customer/internal portal pages to trigger the existing manual Maester workflow safely.

### Requirements
- Secure server-side GitHub authentication path on the host
- Workflow dispatch handler in the app
- Button near `Open latest report`
- Confirmation / success / failure UX

### Constraint
- Do not add a fake button. Only wire it once secure dispatch is available.
