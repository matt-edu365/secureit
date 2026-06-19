# SecureIT Container Test Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Goal

Verify that the current SecureIT app in `app/` runs cleanly in a container and matches the repo's app-first direction before relying on wider GHCR and Proxmox deployment.

## Context

This test plan applies to the container-ready SecureIT application, not the legacy shared-host prototype.

Important distinction:
- `app/` is the future-facing SecureIT product surface
- `website/` and `deploy/maester/` are transitional legacy layers
- Maester remains the assessment engine, but the app container is the SecureIT presentation/runtime layer

## Pre-flight

Before testing:
- confirm `data/tenants.json` exists or is intentionally prepared from an example
- confirm at least one tenant bundle exists under `data/reports/<tenant-key>/latest/`
- confirm `latest/summary.json` exists for the test tenant
- lint the PHP app pages with `php -l` if PHP is available locally
- review `app/config.php` so the expected environment variables match the intended runtime

## Local container build

Build:

```bash
docker build -t secureit:dev .
```

## Local container run

Run:

```bash
docker run --rm -p 8088:80 \
  -e SECUREIT_APP_NAME="SecureIT" \
  -e SECUREIT_BASE_URL="https://secureit.ict365.ky" \
  -e SECUREIT_TENANTS_FILE="/var/www/data/tenants.json" \
  -e SECUREIT_REPORTS_ROOT="/var/www/data/reports" \
  -v $(pwd)/data:/var/www/data \
  secureit:dev
```

If testing canonical control runtime support too, consider supplying:

```bash
-e SECUREIT_CANONICAL_CONTROLS_FILE="/var/www/data/canonical-controls.json"
```

## Suggested test pages

Verify:
- `http://localhost:8088/`
- `http://localhost:8088/login.php`
- `http://localhost:8088/onboard.php`
- `http://localhost:8088/tenant.php?tenant=<tenant-key>`
- `http://localhost:8088/admin.php`

Use a real tenant key that exists in the mounted runtime data.

## Expected results

Expected minimum success state:
- SecureIT branding renders correctly
- dashboard and login pages load without fatal errors
- existing tenant metadata is readable from mounted `data/tenants.json`
- imported tenant summaries render from mounted `data/reports/`
- tenant detail view loads for a valid tenant
- onboarding can write to the mounted runtime path if that flow is enabled in the current environment

## Recommended deeper checks

If doing a fuller validation pass:
- confirm app behaviour when no tenant reports exist yet
- confirm app behaviour when a tenant exists but `summary.json` is missing
- test an imported bundle created through the workflow-to-app path
- verify the app does not assume the legacy `website/` or `deploy/maester/` paths
- verify any Key Vault-related pages or config surfaces degrade safely when Azure settings are absent

## Workflow-to-app bridge validation

A particularly useful test is:
1. generate or obtain a real `app-import/<tenant-key>/...` bundle
2. import it with `scripts/Import-AppReportBundle.ps1`
3. mount the resulting `data/` folder into the container
4. verify the app displays the imported tenant state correctly

That test gives better confidence than synthetic sample files alone.

## Next step after success

After container validation succeeds:
- publish or refresh the image in GHCR
- test the same mounted-data model on the target Docker or Proxmox runtime
- keep the app-first deployment path as the reference implementation

Current target image repository:
- `ghcr.io/matt-edu365/secureit`
