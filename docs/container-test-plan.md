# SecureIT Container Test Plan

## Goal

Verify that SecureIT runs correctly only through the Docker image built from this repository.

## Rules

- Every test environment must be built as a Docker image.
- Do not test against a separate shared-host or simulated web stack.
- Keep `shared/functional-areas.php` inside the image.

## Pre-flight

Before testing:
- confirm `data/tenants.json` exists or is intentionally seeded
- confirm at least one tenant report bundle exists under `data/reports/<tenant-key>/latest/`
- confirm `latest/summary.json` exists for the test tenant
- lint the PHP app pages with `php -l` if PHP is available locally
- review `app/config.php` so the expected environment variables match the intended runtime

## Local Docker build

Build the image from the repository root:

```bash
docker compose up -d --build --pull never
```

The build must fail if the image does not contain:
- `/var/www/html/`
- `/var/www/shared/functional-areas.php`

## Local Docker run

The default local compose service exposes:
- `http://localhost:8088/`

Expected environment:
- `SECUREIT_APP_NAME=SecureIT`
- `SECUREIT_BASE_URL=https://secureit.ict365.ky`
- `SECUREIT_TENANTS_FILE=/var/www/data/tenants.json`
- `SECUREIT_REPORTS_ROOT=/var/www/data/reports`

If canonical scoring is enabled:
- `SECUREIT_CANONICAL_CONTROLS_FILE=/var/www/data/canonical-controls.json`

## Suggested pages

Verify:
- `http://localhost:8088/`
- `http://localhost:8088/login.php`
- `http://localhost:8088/auth/callback`
- `http://localhost:8088/auth/logout`
- `http://localhost:8088/onboard.php`
- `http://localhost:8088/tenant.php?tenant=<tenant-key>`
- `http://localhost:8088/admin.php`

Use a real tenant key from the mounted runtime data.

## Expected results

Minimum success state:
- SecureIT branding renders correctly
- login, callback, logout, and dashboard pages load without fatal errors
- tenant metadata is readable from mounted `data/tenants.json`
- imported tenant summaries render from mounted `data/reports/`
- tenant detail view loads for a valid tenant
- runtime writes stay inside mounted storage

## Deeper checks

If doing a fuller validation pass:
- confirm the app behaves sensibly when no tenant reports exist yet
- confirm the app behaves sensibly when a tenant exists but `summary.json` is missing
- test an imported bundle created through the workflow-to-app path
- verify the app does not assume any removed shared-host paths
- verify Key Vault-related pages degrade safely when Azure settings are absent

## Next step after success

After container validation succeeds:
- publish or refresh the image in GHCR
- test the same mounted-data model on the target Docker or Proxmox runtime
- keep the Docker-based app runtime as the reference implementation
