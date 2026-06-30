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
- `canonical-controls.json` if canonical scoring is enabled, although the app can also fall back to the bundled image copy for the homepage total

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

SecureIT does not generate reports inside the app container.

Typical flow:
1. GitHub Actions runs Maester
2. workflow output is produced under `output/<tenant-key>/...`
3. a bundle is prepared for app import
4. `scripts/Import-AppReportBundle.ps1` imports that into runtime storage
5. the app reads the imported bundle from `data/reports/<tenant-key>/...`

The onboarding flow also writes the customer application secret into Azure Key Vault so the live tenant setup stays aligned with the workflow and diagnostics paths.

## Diagnostics email tests

`app/diagnostics.php` includes plain text and HTML Graph mail tests that send from the shared mailbox and let you choose the recipient on the page. The routines are intended to be reused wherever email is wired into SecureIT, but attachment sending has not been tested yet.

## Report runs

Tenant overview pages can queue a single-tenant run of the `SecureIT Production` GitHub workflow when `SECUREIT_GITHUB_TOKEN` and the repository settings are configured in the environment. `SECUREIT_WORKFLOW_SYNC_TOKEN` remains the app-to-app bridge token used by the SecureIT workflow-sync endpoint. The workflow now also forwards the tenant report recipient to the import endpoint so the post-import email does not depend only on the stored tenant record. After the resulting bundle is imported back into SecureIT, the app sends the tenant's report recipient an HTML summary email using the same overview layout as the diagnostics page.

## Functional-area scoring

SecureIT uses canonical functional areas rather than raw duplicate framework checks.

Key files:
- `config/canonical-controls.example.json`
- `shared/functional-areas.php`

## Deployment direction

Current target:
- Docker image built from `Dockerfile`
- image published to GHCR as `ghcr.io/matt-edu365/secureit`
- runtime on Docker or Proxmox-backed Docker host
- public hostname `https://secureit.ict365.ky`

## Working rule for future changes

Whenever you update SecureIT:
1. update the app/runtime code
2. update the shared helper if the rule is common
3. update the Docker image and local compose test path
4. update the docs that describe the runtime contract

That keeps the Docker-based test environment and the live deployment aligned.
