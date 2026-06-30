# SecureIT Handoff for Next Codex Agent

## Repository

- GitHub: `https://github.com/matt-edu365/secureit`

## What SecureIT is

SecureIT is ICT365's customer-facing multi-tenant Microsoft 365 security reporting portal.

Important framing:
- **Maester is the backend assessment engine**
- **SecureIT is the product, runtime, and branding layer**

## Current operating model

There is one supported test and development path:
1. build the Docker image from this repository
2. run the Docker image locally or on the target host
3. mount runtime data outside the image
4. import report bundles into the mounted data path

There is no separate shared-host web layer in the current stack.

## Files to read first

Read these before making architecture decisions:
- `README.md`
- `docs/repo-structure.md`
- `docs/build-plan.md`
- `docs/container-test-plan.md`
- `docs/keyvault-integration-plan.md`
- `docs/proxmox-deploy-plan.md`

Then inspect:
- `.github/workflows/maester-manual-run.yml`
- `.github/workflows/maester-weekly.yml`
- `scripts/Import-AppReportBundle.ps1`
- `app/config.php`
- `shared/functional-areas.php`

## Working assumptions

### Branding
- Use **SecureIT** in docs, UI copy, and customer-facing descriptions
- Use **Maester** only when referring to the underlying engine, raw reports, or technical dependencies

### Deployment
- Planned product host: `https://secureit.ict365.ky`
- Container registry target: `ghcr.io/matt-edu365/secureit`

### App runtime model
The app expects runtime data outside the image:
- tenants file: `/var/www/data/tenants.json`
- reports root: `/var/www/data/reports`
- shared runtime helper: `/var/www/shared/functional-areas.php`

### Workflow-to-app bridge
Current important bridge:
1. workflow generates `output/<tenant-key>/...`
2. workflow prepares an app-import bundle
3. the workflow posts the bundle to `report-import.php`
4. `report-import.php` imports into app runtime storage and can send the tenant's HTML report summary email

## Recommended priorities

1. Keep the Docker-based runtime reproducible
2. Keep shared runtime logic in `shared/`
3. Keep the app import contract aligned with workflow output
4. Continue customer-facing language cleanup toward SecureIT
5. Keep legacy engine references confined to Maester-specific workflow and engine paths

## Safe areas to work in

- `app/`
- `shared/`
- `scripts/Import-AppReportBundle.ps1`
- `.github/workflows/maester-manual-run.yml`
- `docs/`

## Practical next step

If continuing product cleanup:
1. inspect how `app/` reads imported report data
2. compare that against actual workflow output and app-import bundle shape
3. close any mismatches
4. document the app bundle contract clearly
