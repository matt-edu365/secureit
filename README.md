# SecureIT

SecureIT is ICT365's multi-tenant Microsoft 365 security reporting and posture portal.

Repository:
- `https://github.com/matt-edu365/secureit`

## Current model

SecureIT now has one development and test path:
- build a Docker image from the repository
- run that image as the local or host test environment
- mount runtime data outside the image

There is no separate simulated or shared-host test environment.

## Core rule

- **Maester remains the assessment engine**
- **SecureIT is the product and runtime surface**
- **all test environments must be built as Docker images**

That keeps the codebase and runtime aligned and avoids drift between the local build and the deployed image.

## What lives where

```text
.github/
  workflows/
app/
config/
custom-tests/
data/
docs/
output/
scripts/
shared/
Dockerfile
docker-compose.yml
README.md
```

## Runtime surfaces

### `app/`
The SecureIT web application.

Purpose:
- customer-facing dashboard, login, portal, tenant, admin, onboarding, and Key Vault surfaces
- reads tenant metadata and report bundles from mounted runtime storage

### `shared/`
Shared PHP helpers used by both the app and any supported companion surfaces.

Purpose:
- keep common runtime logic in one place
- avoid divergence in scoring and runtime rules
- package shared dependencies into the Docker image

### `data/`
Runtime storage mounted into the container.

Expected uses:
- `tenants.json`
- `reports/<tenant-key>/...`
- `canonical-controls.json` if canonical scoring is enabled

Canonical controls are stored in the mounted `data/` volume, not in the image. The container seeds `data/canonical-controls.json` from the image copy on first boot, but a pre-existing volume file will stay in place across redeploys until it is explicitly refreshed. Use the diagnostics page to reset the file from the image seed if the live control count drifts from the current build.

## Local Docker workflow

Build and run the local test image from the repository root:

```bash
docker compose up -d --build --pull never
```

The local service is exposed on:
- `http://localhost:8088/`

The local container uses:
- `Dockerfile`
- `docker-compose.yml`
- mounted `data/`
- mounted `.local/` for localhost-only identity seed data (`fab@local` and `con@local`), if present
- `SECUREIT_ENTRA_*` environment variables for Entra sign-in testing, if set in the shell before `docker compose up`

## Report flow

SecureIT does not run Microsoft 365 assessments inside the app container. Maester remains the assessment engine; the app can render a downloadable customer PDF from the latest imported assessment data.

Typical flow:
1. GitHub Actions runs Maester
2. workflow output is produced under `output/<tenant-key>/...`
3. a bundle is prepared for app import
4. `scripts/Import-AppReportBundle.ps1` imports that into runtime storage
5. the app reads the imported bundle from `data/reports/<tenant-key>/...`

From a tenant overview, an authorised customer or administrator can download a branded PDF assessment. The PDF is rendered from a print-specific HTML template and includes a cover, executive summary, eight-area posture overview, prioritised remediation detail, coverage gaps, and a compact record of passing controls.

The onboarding flow also writes the customer application secret into Azure Key Vault so the live tenant setup stays aligned with the workflow and diagnostics paths.

## Diagnostics email tests

`app/diagnostics.php` includes plain text and HTML Graph mail tests that send from the shared mailbox and let you choose the recipient on the page. The routines are intended to be reused wherever email is wired into SecureIT, but attachment sending has not been tested yet.

## Report runs

Tenant overview pages can queue a single-tenant run of the `SecureIT Production` GitHub workflow when `SECUREIT_GITHUB_TOKEN` and the repository settings are configured in the environment. `SECUREIT_WORKFLOW_SYNC_TOKEN` remains the app-to-app bridge token used by the SecureIT workflow-sync endpoint. The workflow now also forwards the tenant report recipient to the import endpoint so the post-import email does not depend only on the stored tenant record. After the resulting bundle is imported back into SecureIT, the app sends the tenant's report recipient an HTML summary email using the same overview layout as the diagnostics page.

## Tenant overview trends

Tenant overview pages include an SVG trend graph for the latest ten stored reports.

Current behavior:
- the overview graph initially renders only the `Overall` line
- `Overall` has its own selected checkbox
- functional-area lines are toggled on and off locally in the browser, without a page refresh
- each line and control has a distinct color
- functional areas with unavailable current scores are greyed out and disabled
- the X axis uses each report date in `dd/MM` format
- report-history area data is resolved once per history row and reused by the graph and run-history table to avoid repeated scoring work

Functional-area views also show a single-area trend graph below the checks table and above run history. The eight functional-area cards are hidden while a functional-area view is active.

## Functional-area scoring

SecureIT uses canonical functional areas rather than raw duplicate framework checks.

The version 2 canonical contract requires every control to have a stable uppercase ID, exactly one declared functional area, one or more explicit evidence IDs, and a scoring weight of `1`. Only explicitly mapped evidence can affect a score.

Area and overall scores use the same calculation:
- pass = `1`
- partial = `0.5`
- fail = `0`
- not applicable, not run, skipped, unmapped, unknown, and error results are excluded from the denominator

Each resolved control also carries structured customer guidance: an issue description, security impact, recommended action, and ordered GUI, PowerShell, review, or verification steps. Failed and partially met controls render the complete guidance in tenant views and downloadable PDFs.

Key files:
- `config/canonical-controls.example.json`
- `shared/functional-areas.php`
- `app/control-details.php`
- `app/control-remediation.php`

## Deployment direction

Current target:
- Docker image built from `Dockerfile`
- image published to GHCR as `ghcr.io/matt-edu365/secureit`
- runtime on Docker or Proxmox-backed Docker host
- public hostname `https://secureit.ict365.ky`

Canonical controls follow the same pattern as tenant data: the image carries the seed copy, while `/var/www/data/canonical-controls.json` is the live runtime file. If the live control count does not match the build, overwrite the mounted file from the diagnostics reset action or replace the persistent volume copy before expecting the portal scores and emails to update.

## Working rule for future changes

Whenever you update SecureIT:
1. update the app/runtime code
2. update the shared helper if the rule is common
3. update the Docker image and local compose test path
4. update the docs that describe the runtime contract

That keeps the Docker-based test environment and the live deployment aligned.
