# SecureIT

## Summary
- Environment: `prod`
- Runtime host: `docker-host-02` (`192.168.36.40`)
- Stack source of truth: `docker/secureit/portainer-stack.yaml`
- Compose project: `secureit`
- Local origin: `http://192.168.36.40:8089`
- Planned public hostname: `secureit.ict365.ky`

## Upstream
- Source repository: `https://github.com/matt-edu365/secureit`
- Runtime image: `ghcr.io/matt-edu365/secureit:latest`

## Runtime shape
- App container: `secureit`
- Persistent volume: `secureit_data`
- Runtime data root: `/var/www/data`
- Tenant metadata file: `/var/www/data/tenants.json`
- Report bundle root: `/var/www/data/reports`
- Health check expectation: root path responds over HTTP on port `80`

## Deployment notes
- SecureIT is the app-first production runtime and should be deployed as a single tracked Portainer stack on the production Docker host.
- The host port is `8089` to avoid colliding with the existing `8088` binding used by the Temporal UI in the Postiz stack.
- Cloudflare Tunnel should publish `secureit.ict365.ky` to `http://192.168.36.40:8089` when the route is added.
- Runtime data belongs on the Docker host volume, not inside the image.
- The deployment record still needs explicit approval evidence and a published monitor outcome to be considered fully compliant.
- If `ghcr.io/matt-edu365/secureit:latest` returns `unauthorized`, temporarily point `SECUREIT_IMAGE` at a host-local image tag and set `SECUREIT_PULL_POLICY=never` until registry access is fixed.

## Validation
- `docker compose ... config`: should pass on `docker-host-02`
- `docker compose ... up -d --build`: should pass on `docker-host-02`
- `GET http://127.0.0.1:8089/`: should return `200`
- `GET https://secureit.ict365.ky/`: should return `200` after the Cloudflare route is published

## First-login tasks
- Add tenant records to `data/tenants.json` or the host-mounted runtime equivalent.
- Import published report bundles into `data/reports/<tenant-key>/...` as needed.
- Review admin config defaults if the runtime needs shared mail or reporting settings.
- Add or update an Uptime Kuma monitor after the public hostname is live.

## Cloudflare handoff
- Hostname: `secureit.ict365.ky`
- Origin route: `http://192.168.36.40:8089`

## Rollback
- Remove the stack from the Docker host with the same deployment path used for apply.
- Remove the Cloudflare route if it was published.
- Keep `secureit_data` unless data removal is intentional.
