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
3. Use `docker/secureit/portainer-stack.yaml` as the stack definition.
4. Bind host port `8089` to container port `80`.
5. Keep the persistent volume `secureit_data`.
6. Publish `secureit.ict365.ky` through Cloudflare Tunnel to `http://192.168.36.40:8089`.
7. Add or update the Uptime Kuma monitor for `https://secureit.ict365.ky/`.
8. Verify `http://192.168.36.40:8089/` returns `200`.
9. Verify the public hostname works after DNS and tunnel propagation.

## Runtime notes
- Container image: `ghcr.io/matt-edu365/secureit:latest`
- Runtime data root: `/var/www/data`
- Tenant file: `/var/www/data/tenants.json`
- Reports root: `/var/www/data/reports`

## Rollback
- Remove the SecureIT stack from the Docker host.
- Remove the Cloudflare route if it was published.
- Keep `secureit_data` unless data removal is intentional.
