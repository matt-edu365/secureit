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
- `canonical-controls.json` for the live canonical scoring catalog

The image contains a versioned seed at `/usr/local/share/secureit/canonical-controls.json`, while the active runtime copy normally lives at `/var/www/data/canonical-controls.json`. On container startup, the entrypoint creates the runtime copy when it is missing and refreshes it when the seed version differs. The loader reads a valid runtime catalog first and falls back to the image seed if the runtime file is missing or invalid. The diagnostics page can also reset the runtime copy manually.

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

From a tenant overview, an authorised customer or administrator can download a branded PDF assessment. The PDF is rendered from a print-specific HTML template and includes a cover, executive summary, seven-area posture overview, prioritised remediation detail, coverage gaps, and a compact record of passing controls.

The onboarding flow also writes the customer application secret into Azure Key Vault so the live tenant setup stays aligned with the workflow and diagnostics paths.

## Diagnostics email tests

`app/diagnostics.php` includes plain text and HTML Graph mail tests that send from the shared mailbox and let you choose the recipient on the page. The routines are intended to be reused wherever email is wired into SecureIT, but attachment sending has not been tested yet.

For runtime bottleneck checks, administrators can open `runtime-diagnostics.php`. It returns JSON with PHP/container limits, cgroup CPU/memory/PID limits, mounted-data disk space, a temporary mounted-volume read/write probe, report-tree inventory and timings. Add `?tenant=ncvo&score=1` to measure that tenant's report inventory and canonical scoring, or add `&graph=1` to measure Graph application-token acquisition without sending mail. Secret values are never returned.

## Report runs

Tenant overview pages can queue a single-tenant run of the `SecureIT Production` GitHub workflow when `SECUREIT_GITHUB_TOKEN` and the repository settings are configured in the environment. `SECUREIT_WORKFLOW_SYNC_TOKEN` remains the app-to-app bridge token used by the SecureIT workflow-sync endpoint. The workflow now also forwards the tenant report recipient to the import endpoint so the post-import email does not depend only on the stored tenant record. After the resulting bundle is imported back into SecureIT, the app sends the tenant's report recipient an HTML summary email using the same overview layout as the diagnostics page.

The production workflow reads its Maester `2.2.0` pin and required Graph application permissions from `config/maester-runtime.json`. SecureIT uses only the test suite bundled with that exact module version and fails closed if a production allowlist file is absent or the manifest permissions differ from `Get-MtGraphScope`. This prevents independently versioned tests and module functions from drifting apart. The generated report must contain `latest/embedded-summary.json`; the workflow refuses to publish or complete successfully if that file is missing or cannot be parsed.

Customer onboarding renders the permission list from the same runtime manifest. Before a client-secret tenant is saved, SecureIT requests a Graph application token with the supplied credentials and verifies that its application-role claims include every permission required by the pinned Maester runtime.

Canonical scoring inspects each test's execution evidence before accepting Maester/Pester's top-level result. Missing scopes, denied or failed Microsoft API requests, and non-assertion execution exceptions resolve to non-scoreable `Error`, not `Fail`. When the evidence names a missing scope, or the test family has a known least-privilege requirement, the tenant view and PDF identify the permission required to rerun it. Overall and functional-area summaries list Error and Skipped counts separately from security failures.

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

The seven functional-area cards are hidden while a functional-area view is active. Area views currently focus on the latest controls and run-history table; they do not render a separate trend card. Planned UI work will restore meaningful per-area line graphs while continuing to suppress empty, non-useful trend cards when no history can be plotted.

## Functional-area scoring

SecureIT uses canonical functional areas rather than raw duplicate framework checks.

The current version 3 catalog contains 95 entries across seven functional areas: 94 production controls plus `CONDITIONALACCESSWHATIF`, which remains catalogued as a separate to-do feature and is excluded from production scoring and totals. `SecureIT-Production-94` combines the retained `Maester-83` baseline controls with 18 production-selected 365Inspect checks.

The six upstream controls deliberately removed from production are `APPREGISTRATIONS`, `MTAPPREGISTRATIONOWNERSWITHOUTMFA`, `MTHIGHRISKAPPPERMISSIONS`, `XSPMDEVICES`, `XSPMPRIVILEGEDIDENTITIES`, and `MTMDIHEALTHISSUES`. They are long-running, preview, or unable to provide a dependable production result with the current integration. The application filters these IDs while loading any older mounted catalog so website, report, and completion-email totals remain on the 94-control production contract during deployment migration.

The version 3 canonical contract requires every control to have a stable uppercase ID, exactly one declared functional area, one or more explicit evidence IDs, and a scoring weight of `1`. Only explicitly mapped evidence can affect a score.

Mounted version 1 catalogs remain readable during deployment when they satisfy the same structural rules. If a mounted catalog is invalid, the loader tries the bundled image seed so a stale runtime file cannot take down customer login.

Area and overall scores use the same calculation:
- pass = `1`
- partial = `0.5`
- fail = `0`
- not applicable, not run, skipped, unmapped, unknown, and error results are excluded from the denominator

Each resolved control also carries structured customer guidance: an issue description, security impact, recommended action, and ordered GUI, PowerShell, review, or verification steps. Failed and partially met controls render the complete guidance in tenant views and downloadable PDFs.

Key files:
- `config/canonical-controls.example.json`
- `config/maester-runtime.json`
- `shared/functional-areas.php`
- `app/control-details.php`
- `app/control-remediation.php`

The report-summary parser is shared by the production runner and is covered by `tests/MaesterReportParsing.Tests.ps1`. It accepts Maester's embedded summary object regardless of the minified JavaScript variable name used by the generated HTML report.

## Deployment direction

Current target:
- Docker image built from `Dockerfile`
- image published to GHCR as `ghcr.io/matt-edu365/secureit`
- runtime on Docker or Proxmox-backed Docker host
- public hostname `https://secureit.ict365.ky`

Canonical controls follow the same mounted-data pattern as tenant data. The image carries the versioned seed, and `/var/www/data/canonical-controls.json` is the preferred live scoring source. A normal container start refreshes the mounted copy when its version differs from the image. Use the diagnostics reset action if a manual recovery or verification is needed.

## Working rule for future changes

Whenever you update SecureIT:
1. update the app/runtime code
2. update the shared helper if the rule is common
3. update the Docker image and local compose test path
4. update the docs that describe the runtime contract

That keeps the Docker-based test environment and the live deployment aligned.
