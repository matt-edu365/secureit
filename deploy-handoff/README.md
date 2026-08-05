# SecureIT Deployment Handoff

Use this bundle to deploy SecureIT to the ICT365 Docker host.

## What is included
- `Dockerfile`
- `docker/apache-site.conf`
- `.dockerignore`
- `docker/secureit/portainer-stack.yaml`
- `docs/services/secureit.md`
- `docs/site-topology.drawio`
- `docs/hosts/docker-host-02.md`
- `infra/terraform/envs/prod/main.tf`

## Deploy steps
1. Build and publish the image from this repository to GHCR as `ghcr.io/matt-edu365/secureit:latest`.
2. Deploy the stack on `docker-host-02`.
3. Use `deploy-handoff/docker/secureit/portainer-stack.yaml` as the stack definition.
4. Bind host port `8089` to container port `80`.
5. Supply the Entra runtime variables in the stack or Portainer environment before deploying the stack.
6. Keep the persistent volume `secureit_data`.
7. Ensure `secureit.ict365.ky` is published through Cloudflare Tunnel to `http://192.168.36.40:8089`.
8. Add or update the Uptime Kuma monitor for `https://secureit.ict365.ky/`.
9. Verify `http://192.168.36.40:8089/` returns `200`.
10. Verify the public hostname works after DNS and tunnel propagation.

## Registry fallback
- The stack defaults to `ghcr.io/matt-edu365/secureit:latest`.
- If GHCR returns `unauthorized` on `docker-host-02`, set `SECUREIT_IMAGE` to a locally built image tag already present on the host and set `SECUREIT_PULL_POLICY=never` before reapplying the stack.
- Use the GHCR image again only after package read access is fixed for the host.

## Runtime notes
- Container image: `ghcr.io/matt-edu365/secureit:latest` by default, or a host-local override via `SECUREIT_IMAGE`
- Runtime data root: `/var/www/data`
- Tenant file: `/var/www/data/tenants.json`
- Reports root: `/var/www/data/reports`
- Canonical controls file: `/var/www/data/canonical-controls.json` (the stack default; startup seeds or refreshes it from the versioned image copy, and the application falls back to the image if the runtime file is missing or invalid)
- Entra sign-in requires `SECUREIT_ENTRA_*` variables in the live stack
- Required Entra stack variables:
  - `SECUREIT_ENTRA_CLIENT_ID`
  - `SECUREIT_ENTRA_CLIENT_SECRET`
  - `SECUREIT_ENTRA_AUTHORITY`
  - `SECUREIT_ENTRA_REDIRECT_URI`
  - `SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI`
  - `SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS`
- Optional Entra stack variables:
  - `SECUREIT_ENTRA_ALLOWED_TENANT_IDS`
- Workflow integration variables required by tenant discovery, report import, and the tenant-page run button:
  - `SECUREIT_WORKFLOW_SYNC_TOKEN`
  - `SECUREIT_GITHUB_REPOSITORY`
  - `SECUREIT_GITHUB_WORKFLOW_FILE`
  - `SECUREIT_GITHUB_WORKFLOW_REF`
  - `SECUREIT_GITHUB_TOKEN`
- The current `portainer-stack.yaml` does not yet forward those workflow variables; extend it before relying on those features in this deployment definition.

## Rollback
- Remove the SecureIT stack from the Docker host.
- Remove the Cloudflare route if it was published.
- Keep `secureit_data` unless data removal is intentional.
