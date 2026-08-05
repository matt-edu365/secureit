# SecureIT

## Summary
- Environment: `prod`
- Runtime host: `docker-host-02` (`192.168.36.40`)
- Stack source of truth: `docker/secureit/portainer-stack.yaml`
- Compose project: `secureit`
- Local origin: `http://192.168.36.40:8089`
- Public hostname: `secureit.ict365.ky`

## Upstream
- Source repository: `https://github.com/matt-edu365/secureit`
- Runtime image: `ghcr.io/matt-edu365/secureit:latest`

## Runtime shape
- App container: `secureit`
- Persistent volume: `secureit_data`
- Runtime data root: `/var/www/data`
- Tenant metadata file: `/var/www/data/tenants.json`
- Report bundle root: `/var/www/data/reports`
- Canonical controls file: `/var/www/data/canonical-controls.json` (optional override via `SECUREIT_CANONICAL_CONTROLS_FILE`)
- Seed copy in image: `/usr/local/share/secureit/canonical-controls.json`
- The runtime file lives on the persistent `secureit_data` volume. The container creates it when missing and refreshes it when the image seed version differs. If no runtime version file exists, startup compares the catalog contents and refreshes a differing copy. The application uses a valid runtime catalog first and falls back to the image seed if necessary.
- Entra runtime variables must be supplied by the Portainer stack or host environment
- Key Vault runtime variables for shared component storage:
  - `SECUREIT_KEY_VAULT_TENANT_ID`
  - `SECUREIT_KEY_VAULT_CLIENT_ID`
  - `SECUREIT_KEY_VAULT_CLIENT_SECRET`
  - `SECUREIT_KEY_VAULT_NAME`
  - `SECUREIT_KEY_VAULT_URI`
- Required Entra stack variables:
  - `SECUREIT_ENTRA_CLIENT_ID`
  - `SECUREIT_ENTRA_CLIENT_SECRET`
  - `SECUREIT_ENTRA_AUTHORITY`
  - `SECUREIT_ENTRA_REDIRECT_URI`
  - `SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI`
  - `SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS`
- Optional Entra stack variables:
  - `SECUREIT_ENTRA_ALLOWED_TENANT_IDS`
- Workflow integration variables required for tenant discovery, report import, and tenant-page dispatch:
  - `SECUREIT_WORKFLOW_SYNC_TOKEN`
  - `SECUREIT_GITHUB_REPOSITORY`
  - `SECUREIT_GITHUB_WORKFLOW_FILE`
  - `SECUREIT_GITHUB_WORKFLOW_REF`
  - `SECUREIT_GITHUB_TOKEN`
- These workflow variables are part of the app contract but are not yet forwarded by the checked-in Portainer stack.
- Health check expectation: root path responds over HTTP on port `80`

## Deployment notes
- SecureIT is the app-first production runtime and should be deployed as a single tracked Portainer stack on the production Docker host.
- The host port is `8089` to avoid colliding with the existing `8088` binding used by the Temporal UI in the Postiz stack.
- Cloudflare Tunnel should keep `secureit.ict365.ky` routed to `http://192.168.36.40:8089`.
- Runtime data belongs on the Docker host volume, not inside the image.
- When the canonical control count looks stale after a new image deploy, first confirm that the container restarted with the new image and that the runtime and image version files agree. The diagnostics reset remains available as a manual repair path.
- The live container should not mount `.local/identity-seeds.json`; `fab@local` and `con@local` are localhost-only development identities.
- The deployment record still needs explicit approval evidence and a published monitor outcome to be considered fully compliant.
- If `ghcr.io/matt-edu365/secureit:latest` returns `unauthorized`, temporarily point `SECUREIT_IMAGE` at a host-local image tag and set `SECUREIT_PULL_POLICY=never` until registry access is fixed.

## Validation
- `docker compose ... config`: should pass on `docker-host-02`
- `docker compose ... up -d --build`: should pass on `docker-host-02`
- `GET http://127.0.0.1:8089/`: should return `200`
- `GET https://secureit.ict365.ky/`: should return `200`

## Runtime bootstrap or recovery tasks
- Add tenant records to `data/tenants.json` or the host-mounted runtime equivalent.
- Import published report bundles into `data/reports/<tenant-key>/...` as needed.
- Review admin config defaults if the runtime needs shared mail or reporting settings.
- Portal scoring validates and reads the mounted runtime catalog first. The homepage count reads the first decodable catalog containing controls. In the normal deployment both therefore use the mounted copy; the bundled image catalog is the seed and fallback.
- Confirm `SECUREIT_ENTRA_CLIENT_ID`, `SECUREIT_ENTRA_CLIENT_SECRET`, and the Entra redirect/logout URLs are present in the stack before exposing the public route.
- Do not put `SECUREIT_ENTRA_CLIENT_SECRET` in GitHub; enter it in Portainer or in a host-only env file instead.
- Add or update an Uptime Kuma monitor after the public hostname is live.

## Cloudflare handoff
- Hostname: `secureit.ict365.ky`
- Origin route: `http://192.168.36.40:8089`

## Rollback
- Remove the stack from the Docker host with the same deployment path used for apply.
- Remove the Cloudflare route if it was published.
- Keep `secureit_data` unless data removal is intentional.
- If control counts drift, compare the mounted catalog and version file with the image seed. The mounted valid catalog is used for portal scoring, while the image provides the versioned deployment baseline and fallback.
