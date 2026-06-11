# SecureIt Container Test Plan

## Goal

Verify that the refactored SecureIt app runs cleanly in a container before publishing the first image to GitHub Container Registry.

## Pre-flight

- Confirm `data/tenants.json` exists
- Confirm `data/reports/<tenant-key>/latest/summary.json` exists for sample tenants
- Confirm app pages lint successfully with `php -l`

## Local container test

Build:

```bash
docker build -t secureit:dev .
```

Run:

```bash
docker run --rm -p 8088:80 \
  -e SECUREIT_APP_NAME="SecureIt" \
  -e SECUREIT_BASE_URL="https://example.ict365.uk" \
  -e SECUREIT_TENANTS_FILE="/var/www/data/tenants.json" \
  -e SECUREIT_REPORTS_ROOT="/var/www/data/reports" \
  -v $(pwd)/data:/var/www/data \
  secureit:dev
```

Test:

- `http://localhost:8088/`
- `http://localhost:8088/onboard.php`
- `http://localhost:8088/tenant.php?tenant=contoso-prod`

## Expected result

- Dashboard loads with SecureIt branding
- Existing sample tenants appear
- Sample summaries render correctly
- Onboarding can write to mounted `data/tenants.json`
- New tenant folders are created under mounted `data/reports/`

## Next step after success

Push repo to GitHub and publish the first image to:

- `ghcr.io/mfletcher81/secureit`
