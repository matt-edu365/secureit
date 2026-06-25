# SecureIT Live Deployment Handoff

## Repository

- GitHub: `https://github.com/matt-edu365/secureit`
- Working path: `/home/matt/nas/workfiles/Work Projects/SecureIT`

## Goal

Deploy SecureIT as a live Docker-hosted application at:
- `https://secureit.ict365.ky`

The intended delivery loop is now:
1. build and refine locally
2. push to GitHub
3. GitHub builds and pushes the Docker image
4. deploy the image to the live Docker host
5. test on `https://secureit.ict365.ky`
6. refine and repeat

## Current runtime model

SecureIT is currently a PHP + Apache web application with mounted persistent JSON/report storage.

Important architecture rule:
- **Maester is the assessment engine**
- **SecureIT is the product and hosting surface**

The live container is not expected to run Maester locally.

## What the live container must do

The live container must be able to:
- run Apache
- execute the PHP application in `app/`
- serve static imported report files
- read and write mounted runtime data under `/var/www/data`

## What the live container does not need to do

The live container does not currently need to run:
- Maester
- PowerShell
- GitHub Actions runner
- Docker-in-Docker
- SMTP server
- FTPS service
- database server

## Required runtime services around the container

### 1. Docker host
A Docker-capable host must:
- pull `ghcr.io/matt-edu365/secureit`
- run the container
- mount persistent storage into `/var/www/data`
- restart the container automatically if needed

### 2. Reverse proxy / HTTPS termination
A reverse proxy or ingress layer must:
- present `https://secureit.ict365.ky`
- terminate TLS
- forward traffic to the SecureIT container
  - optionally enforce access restrictions while the production auth model is still being finalised

### 3. Persistent storage
Persistent mounted storage must exist for:
- `/var/www/data/tenants.json`
- `/var/www/data/reports/`

Likely additional runtime files:
- `/var/www/data/admin-config.json`
- `/var/www/data/canonical-controls.json` if canonical scoring is enabled later; this is optional because the app can fall back to the bundled image copy

## Minimum environment variables

Required minimum runtime variables:
- `SECUREIT_APP_NAME=SecureIT`
- `SECUREIT_BASE_URL=https://secureit.ict365.ky`
- `SECUREIT_TENANTS_FILE=/var/www/data/tenants.json`
- `SECUREIT_REPORTS_ROOT=/var/www/data/reports`

Likely later:
- `SECUREIT_CANONICAL_CONTROLS_FILE=/var/www/data/canonical-controls.json` if you want to override the default runtime path; otherwise it can be omitted
- `SECUREIT_AZURE_TENANT_ID=<app-tenant-id>`
- `SECUREIT_AZURE_CLIENT_ID=<secureit-app-client-id>`
- `SECUREIT_AZURE_CLIENT_SECRET=<secureit-app-client-secret>`
- `SECUREIT_KEY_VAULT_NAME=<key-vault-name>`
- or `SECUREIT_KEY_VAULT_URI=<vault-uri>`
- `SECUREIT_ENTRA_TENANT_ID=<ict365-or-common-authority>`
- `SECUREIT_ENTRA_CLIENT_ID=<secureit-login-app-client-id>`
- `SECUREIT_ENTRA_CLIENT_SECRET=<secureit-login-app-client-secret>`
- `SECUREIT_ENTRA_REDIRECT_URI=https://secureit.ict365.ky/auth/callback`
- `SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI=https://secureit.ict365.ky/login.php`
- `SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS=ict365.ky`

## Permission requirements

The mounted `/var/www/data` path must be writable by the web process inside the container.

At minimum, the app may need to:
- create directories
- save tenant metadata
- save admin config
- read imported report bundles

Check ownership and permissions for the effective Apache/PHP user, which is expected to be `www-data` in the current image.

## Bootstrap requirements for first live test

Before first meaningful validation:
1. create or seed `tenants.json`
2. ensure `reports/` exists
3. import at least one tenant report bundle under `data/reports/<tenant-key>/latest/`
4. ensure `summary.json` exists for that tenant

Without this, the app may still load, but there will be little useful content to verify.

## Workflow-to-runtime integration gap

One of the biggest remaining decisions is how workflow output reaches the live app storage.

Current available path:
1. GitHub workflow generates `output/<tenant-key>/...`
2. workflow prepares `app-import/<tenant-key>/...`
3. `scripts/Import-AppReportBundle.ps1` can import that bundle into app runtime storage

Still to decide:
- manual import by operator
- automated artifact deployment to the live host
- host-side sync/pull job

The next agent should treat this as a priority integration decision.

## Authentication warning

The current app now uses an Entra ID-backed login flow in the codebase, but production sign-in is only real once the live app registration, redirect URIs, and logout URLs are configured and tested end to end.
The localhost-only seed identities (`fab@local` and `con@local`) are development conveniences and must not be treated as a live deployment path.

Before real customer exposure, confirm the live tenant configuration and sign-in routing:
- Entra redirect URIs are registered for `/auth/callback`
- logout return URLs and front-channel logout URLs are registered
- admin and customer access rules work as intended
- the first customer tenant can sign in without seeing any other tenant
- local `.local/identity-seeds.json` data is not mounted or relied on in the production container
- `/var/www/data/canonical-controls.json` is optional for the homepage total; if present it is used first, otherwise the bundled image copy provides the count

Do not assume the fallback seed-based login path is the production auth model.

## Post-deploy validation checklist

After the first live deploy, verify:
- `https://secureit.ict365.ky` loads
- login page loads
- portal/dashboard pages render without fatal errors
- tenant page works for a seeded tenant
- report URLs resolve correctly under `secureit.ict365.ky`
- admin settings save successfully to mounted storage
- container restarts cleanly without losing runtime data

## Recommended priorities for the next Codex agent

1. confirm live Docker host deployment method
2. implement or document the GHCR-to-host deployment path
3. define how app-import bundles reach live runtime storage
4. validate mounted storage permissions and ownership
5. verify TLS and reverse-proxy behaviour for `secureit.ict365.ky`
6. decide short-term access control before public exposure
7. run an end-to-end live test with at least one real imported tenant bundle

## Relevant files to inspect next

- `Dockerfile`
- `docker-compose.yml`
- `docker/apache-site.conf`
- `app/config.php`
- `app/lib.php`
- `.github/workflows/docker-publish.yml`
- `.github/workflows/docker-deploy-handoff.yml`
- `.github/workflows/maester-manual-run.yml`
- `scripts/Import-AppReportBundle.ps1`
- `docs/proxmox-deploy-plan.md`
- `docs/handoff-codex-agent.md`
