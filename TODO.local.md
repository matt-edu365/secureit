# SecureIT local TODOs

These notes are intentionally outside the published Docker runtime surface.

## Tenant functional-area scoring

Status: largely done.

Current state:
- Customer tenant pages now render real functional-area scores instead of placeholders.
- The scoring logic is shared through `shared/functional-areas.php`.
- The app tenant page uses canonical control scoring from the imported runtime bundle.
- The legacy website surface that used placeholder cards has been removed.

Still worth revisiting:
- confirm whether any additional Maester metadata should refine canonical area mapping
- keep the scoring contract aligned with future report bundle changes
- decide whether the workflow output should surface the area scores directly in summary artifacts

## GitHub workflow trigger from tenant page

Goal: allow customer/internal portal pages to trigger the existing manual Maester workflow safely.

### Requirements
- Secure server-side GitHub authentication path on the host
- Workflow dispatch handler in the app
- Button near `Open latest report`
- Confirmation / success / failure UX

### Constraint
- Do not add a fake button. Only wire it once secure dispatch is available.

### Status
- Not implemented yet.
- Still needs a secure server-side GitHub auth path and workflow dispatch handling.

## Entra ID authentication

Goal: replace the current development login router with proper Microsoft Entra ID sign-in for both customer tenants and ICT365 administrators.

### Requirements
- Entra ID OpenID Connect sign-in for the SecureIT web app
- tenant-aware routing after sign-in
- admin-only access for internal pages
- secure session handling with token validation and logout
- onboarding guidance for customer tenant setup and consent

### Status
- Not implemented yet.
- Needs a concrete identity model, app registration, callback flow, and route-to-tenant mapping.

## Entra validation rollout

Phase 4 focus:
- validate one ICT365 admin sign-in from the `@ict365.ky` domain
- validate one customer sign-in mapped to a single tenant
- confirm admins can open customer tenant pages from the dashboard
- confirm customers cannot open any tenant except their own

Current rule:
- any successful Entra sign-in from the `@ict365.ky` domain should be treated as an admin session unless a more specific Entra app role overrides it later
