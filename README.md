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
- mounted `.local/` for local identity seed data, if present

## Report flow

SecureIT does not generate reports inside the app container.

Typical flow:
1. GitHub Actions runs Maester
2. workflow output is produced under `output/<tenant-key>/...`
3. a bundle is prepared for app import
4. `scripts/Import-AppReportBundle.ps1` imports that into runtime storage
5. the app reads the imported bundle from `data/reports/<tenant-key>/...`

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
