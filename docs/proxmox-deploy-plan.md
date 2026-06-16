# SecureIT Proxmox Deployment Plan

## Goal

Publish SecureIT as a Docker image to GitHub Container Registry, then pull and run it on a Proxmox Docker environment.

## Image

- Registry: `ghcr.io/matt-edu365/secureit`
- Tags: `latest` and commit SHA

## Runtime design

- Container serves the PHP app only
- Tenant metadata is stored outside the image in a mounted volume
- Report files are stored outside the image in a mounted volume
- Reverse proxy terminates TLS and exposes the final hostname

## Required mounted storage

- `./data:/var/www/data`

Inside that volume:
- `tenants.json`
- `reports/<tenant-key>/latest/...`
- `reports/<tenant-key>/history/...`

## Example deployment steps on Proxmox host

```bash
docker login ghcr.io
mkdir -p /opt/secureit/data/reports
cd /opt/secureit
```

Create `docker-compose.yml` and `data/tenants.json`, then:

```bash
docker compose pull
docker compose up -d
```

## Planned production hostname

- `secureit.ict365.ky`

## Environment variables for production

- `SECUREIT_APP_NAME=SecureIT`
- `SECUREIT_BASE_URL=https://secureit.ict365.ky`
- `SECUREIT_TENANTS_FILE=/var/www/data/tenants.json`
- `SECUREIT_REPORTS_ROOT=/var/www/data/reports`
- `SECUREIT_AZURE_TENANT_ID=<app-tenant-id>`
- `SECUREIT_AZURE_CLIENT_ID=<secureit-app-client-id>`
- `SECUREIT_AZURE_CLIENT_SECRET=<secureit-app-client-secret>`
- `SECUREIT_KEY_VAULT_NAME=<key-vault-name>`

## Notes

Use `example.ict365.uk` as the dev hostname for now, but keep the app branding as SecureIT so the later cut-over is trivial.
