# SecureIT Proxmox Deployment Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Goal

Publish the SecureIT app as a Docker image to GitHub Container Registry, then pull and run it on a Proxmox-hosted Docker environment or equivalent target.

## Current deployment picture

The target deployment track is now the only intended path for active delivery:
- SecureIT app in `app/`
- image built from `Dockerfile`
- image published to GHCR
- runtime backed by mounted external data
- public hostname `https://secureit.ict365.ky`
- NCVO has already been onboarded on the live path and survives a container redeploy

## Image

Registry image:
- `ghcr.io/matt-edu365/secureit`

Expected tags:
- `latest`
- commit SHA tags

## Runtime design

The target runtime model is:
- container serves the SecureIT PHP app
- tenant metadata is stored outside the image in mounted storage
- report bundles are stored outside the image in mounted storage
- reverse proxy terminates TLS and exposes the final hostname
- customer-facing branding remains SecureIT even though Maester remains the backend assessment engine
- onboarding writes the tenant client secret to Key Vault
- the diagnostics page contains a temporary repair path for writing an existing tenant secret into Key Vault

## Required mounted storage

Base mount:
- `./data:/var/www/data`

Inside that volume, expect at minimum:
- `tenants.json`
- `reports/<tenant-key>/latest/...`
- `reports/<tenant-key>/history/...`

Optional runtime file if used:
- `canonical-controls.json` (the app can also fall back to the bundled image copy for the homepage total)

## Example host preparation

```bash
docker login ghcr.io
mkdir -p /opt/secureit/data/reports
cd /opt/secureit
```

Create or supply:
- `docker-compose.yml`
- `data/tenants.json`
- any required runtime report bundles under `data/reports/`

Then:

```bash
docker compose pull
docker compose up -d
```

## Planned production hostname

- `secureit.ict365.ky`

## Suggested environment variables

- `SECUREIT_APP_NAME=SecureIT`
- `SECUREIT_BASE_URL=https://secureit.ict365.ky`
- `SECUREIT_TENANTS_FILE=/var/www/data/tenants.json`
- `SECUREIT_REPORTS_ROOT=/var/www/data/reports`
- `SECUREIT_CANONICAL_CONTROLS_FILE=/var/www/data/canonical-controls.json` if you want to override the default runtime path
- `SECUREIT_KEY_VAULT_TENANT_ID=<app-tenant-id>`
- `SECUREIT_KEY_VAULT_CLIENT_ID=<secureit-app-client-id>`
- `SECUREIT_KEY_VAULT_CLIENT_SECRET=<secureit-app-client-secret>`
- `SECUREIT_KEY_VAULT_NAME=<key-vault-name>`
- `SECUREIT_KEY_VAULT_URI=<key-vault-uri>`
- `SECUREIT_ENTRA_TENANT_ID=<ict365-tenant-id>`
- `SECUREIT_ENTRA_CLIENT_ID=<secureit-login-app-client-id>`
- `SECUREIT_ENTRA_CLIENT_SECRET=<secureit-login-app-client-secret>`
- `SECUREIT_ENTRA_REDIRECT_URI=https://secureit.ict365.ky/auth/callback`
- `SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI=https://secureit.ict365.ky/login.php`
- `SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS=ict365.ky`

Optional Key Vault metadata can be persisted in the admin config, but the runtime source of truth is the Key Vault environment set on the container or host.

## Recommended deployment validation

Before treating the Proxmox deployment as authoritative:
1. validate the app locally in Docker using mounted runtime data
2. validate imported report bundles via the workflow-to-app bridge
3. confirm the app does not depend on legacy shared-host paths
4. confirm reverse-proxy and auth behaviour in front of the app
5. confirm runtime writes only affect the mounted data path, not the image filesystem

## Delivery workflow

The intended operational loop is now:
1. build and refine locally
2. push to GitHub
3. GitHub builds and pushes the Docker image
4. deploy the image to the Docker host
5. test on `https://secureit.ict365.ky`
6. refine and repeat

## Notes

- Keep SecureIT as the visible product identity
- Treat any remaining Maester references as backend engine terminology only
