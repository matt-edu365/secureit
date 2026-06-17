# SecureIT Proxmox Deployment Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Goal

Publish the SecureIT app as a Docker image to GitHub Container Registry, then pull and run it on a Proxmox-hosted Docker environment or equivalent target.

## Current deployment picture

There are two deployment tracks in the repo right now:

### Transitional track
- shared-host prototype on `https://example.ict365.uk`
- legacy deploy bundle in `deploy/maester/`
- legacy prototype source in `website/`

### Target track
- SecureIT app in `app/`
- image built from `Dockerfile`
- image published to GHCR
- runtime backed by mounted external data
- planned product hostname `https://secureit.ict365.ky`

The Proxmox plan applies to the target track.

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

## Required mounted storage

Base mount:
- `./data:/var/www/data`

Inside that volume, expect at minimum:
- `tenants.json`
- `reports/<tenant-key>/latest/...`
- `reports/<tenant-key>/history/...`

Optional runtime file if used:
- `canonical-controls.json`

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
- `SECUREIT_CANONICAL_CONTROLS_FILE=/var/www/data/canonical-controls.json`
- `SECUREIT_AZURE_TENANT_ID=<app-tenant-id>`
- `SECUREIT_AZURE_CLIENT_ID=<secureit-app-client-id>`
- `SECUREIT_AZURE_CLIENT_SECRET=<secureit-app-client-secret>`
- `SECUREIT_KEY_VAULT_NAME=<key-vault-name>`

## Recommended deployment validation

Before treating the Proxmox deployment as authoritative:
1. validate the app locally in Docker using mounted runtime data
2. validate imported report bundles via the workflow-to-app bridge
3. confirm the app does not depend on legacy shared-host paths
4. confirm reverse-proxy and auth behaviour in front of the app
5. confirm runtime writes only affect the mounted data path, not the image filesystem

## Notes

- Use `example.ict365.uk` only as the prototype/dev host reference while the transition continues
- Keep SecureIT as the visible product identity
- Treat `deploy/maester/` as transitional until the app-first runtime fully replaces it
