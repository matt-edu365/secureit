# SecureIT Build Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Objective

Continue evolving SecureIT from a Maester-based reporting prototype into a branded multi-tenant Microsoft 365 security portal with:
- repeatable tenant assessments
- SecureIT-branded customer-facing posture views
- canonical control mapping and functional-area scoring
- app-oriented report import and presentation
- container-based deployment as the long-term target

## Current reality

This project is beyond the original scaffold stage.

Already present:
- manual GitHub Actions Maester workflow with test-profile selection
- legacy weekly/manual workflow retained for compatibility
- container-ready SecureIT app in `app/`
- shared-host prototype path in `website/` and `deploy/maester/`
- app-import report bundle flow
- Azure OIDC diagnostics
- Azure Key Vault smoke-test workflow
- SecureIT branding adaptation underway across app and docs

## Core architectural principle

- **Maester is the assessment engine**
- **SecureIT is the product surface**

Build decisions should preserve traceability to raw Maester outputs while making the customer-facing experience clearly SecureIT.

## Build priorities from here

### Phase 1: Documentation and architecture alignment
1. Keep docs aligned with the real repo state
2. Explicitly document the Maester -> SecureIT branding transition
3. Reduce confusion between legacy prototype, current app, and future deployment target
4. Maintain a clear handoff path for follow-on agents

### Phase 2: App-first runtime consolidation
1. Treat `app/` as the main product surface
2. Keep `data/` as the writable runtime store for tenants and reports
3. Continue using `app-import/<tenant-key>/...` as the bridge from workflow output to app runtime
4. Tighten the contract between workflow artifacts and app report ingestion

### Phase 3: Canonical customer-facing scoring
1. Expand `config/canonical-controls.example.json`
2. Finalise the SecureIT functional area model
3. Map raw Maester findings into canonical SecureIT controls
4. Drive dashboard and tenant posture views from canonicalised data instead of duplicated raw framework checks

### Phase 4: Workflow rationalisation
1. Decide which workflow becomes the single authoritative run path
2. Prefer `maester-manual-run.yml` as the modern baseline unless a deliberate replacement is introduced
3. Review whether `maester-weekly.yml` should be retired, renamed, or rebuilt
4. Standardise artifact naming and report publication assumptions
5. Separate legacy prototype publishing concerns from app-first runtime import concerns where practical

### Phase 5: Deployment transition
1. Keep the shared-host prototype functional only as long as needed
2. Continue building toward Docker image publishing via GHCR
3. Use the Proxmox deployment model as the target runtime
4. Move customer-facing usage toward `app/` rather than the legacy prototype bundle

## Current workflow picture

### `maester-manual-run.yml`
This is the most current workflow path.

It already supports:
- tenant-specific manual runs
- certificate or client-secret auth
- Azure Key Vault secret retrieval for client-secret mode
- test-profile selection
- FTPS publication to the prototype environment
- preparation of app-import bundles
- artifact upload

### `maester-weekly.yml`
This is retained but explicitly legacy.

Use with caution until its role is clarified.

### `docker-publish.yml`
Manually publishes the SecureIT container image to:
- `ghcr.io/matt-edu365/secureit`

### Azure diagnostics
Useful support workflows:
- `azure-oidc-diagnostic.yml`
- `azure-keyvault-smoke-test.yml`

## Runtime design target

Long-term target:
- SecureIT app in `app/`
- Docker image from `Dockerfile`
- GHCR publication
- runtime data volume mounted at `/var/www/data`
- tenant reports stored in `/var/www/data/reports`
- tenant metadata stored in `/var/www/data/tenants.json`

## Branding rule for future work

When building or documenting new work:
- say **SecureIT** when describing the product, UI, portal, dashboard, onboarding, and customer experience
- say **Maester** when describing the underlying test engine, dependency, or raw report source
- avoid introducing fresh customer-facing references that make Maester sound like the product name

## Practical next steps

1. Review `maester-manual-run.yml` and decide whether it should be renamed or kept as-is for technical clarity
2. Confirm the intended single source of truth for tenant metadata across `config/`, `data/`, and `deploy/`
3. Expand canonical control mappings so the app can present real SecureIT scoring
4. Define the minimum viable production auth model for the app surface
5. Decide the retirement threshold for `website/` and `deploy/maester/`
6. Test the current app path end-to-end with imported report bundles

## Success condition

The repo should converge on a state where:
- Maester runs are reliable and traceable
- SecureIT branding is consistent and intentional
- the app can ingest and present reports cleanly
- deployment is container-first
- legacy prototype paths are clearly transitional rather than ambiguous
