# SecureIT Live Deployment Handoff

## Repository

- GitHub: `https://github.com/matt-edu365/secureit`
- Working path: `/home/matt/nas/workfiles/Work Projects/SecureIT`

## Goal

Operate and update SecureIT as a live Docker-hosted application at:
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

NCVO has now been onboarded live, the tenant survives a container redeploy, and the onboarding flow writes the client secret into Key Vault.

## What the live container must do

The live container must be able to:
- run Apache
- execute the PHP application in `app/`
- serve static imported report files
- read and write mounted runtime data under `/var/www/data`
- preserve tenant records, admin config, and canonical controls across container recreation

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

Additional runtime files:
- `/var/www/data/admin-config.json`
- `/var/www/data/canonical-controls.json` for canonical scoring; container startup seeds or refreshes it from the versioned image copy, and the loader can fall back to the image if it is missing or invalid

The canonical control source of truth is `docker/secureit-assets/canonical-controls.json`; increment the adjacent `docker/secureit-assets/canonical-controls.version` file whenever the catalog contract changes. Published app versions then receive the new `cC` suffix automatically from the Docker workflow. Customer-facing control IDs use the `C0001` format and retain upstream identifiers in each control's `aliases` array.

## Minimum environment variables

Required minimum runtime variables:
- `SECUREIT_APP_NAME=SecureIT`
- `SECUREIT_BASE_URL=https://secureit.ict365.ky`
- `SECUREIT_TENANTS_FILE=/var/www/data/tenants.json`
- `SECUREIT_REPORTS_ROOT=/var/www/data/reports`

Current integration variables:
- `SECUREIT_CANONICAL_CONTROLS_FILE=/var/www/data/canonical-controls.json` is already the production-stack default and only needs to be set explicitly when overriding the image default
- `SECUREIT_KEY_VAULT_TENANT_ID=<app-tenant-id>`
- `SECUREIT_KEY_VAULT_CLIENT_ID=<secureit-app-client-id>`
- `SECUREIT_KEY_VAULT_CLIENT_SECRET=<secureit-app-client-secret>`
- `SECUREIT_KEY_VAULT_NAME=<key-vault-name>`
- or `SECUREIT_KEY_VAULT_URI=<vault-uri>`
- `SECUREIT_ENTRA_CLIENT_ID=<secureit-login-app-client-id>`
- `SECUREIT_ENTRA_CLIENT_SECRET=<secureit-login-app-client-secret>`
- `SECUREIT_ENTRA_REDIRECT_URI=https://secureit.ict365.ky/auth/callback`
- `SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI=https://secureit.ict365.ky/login.php`
- `SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS=ict365.ky`
- `SECUREIT_ENTRA_TENANT_ID=<ict365-tenant-id>` for app-only Graph features such as the diagnostics mail test
- `SECUREIT_GITHUB_REPOSITORY=<owner/repo>` for tenant-page workflow dispatch
- `SECUREIT_GITHUB_WORKFLOW_FILE=secureit-production.yml`
- `SECUREIT_GITHUB_WORKFLOW_REF=main`
- `SECUREIT_GITHUB_TOKEN=<fine-grained GitHub token>` for tenant-page workflow dispatch
- `SECUREIT_WORKFLOW_SYNC_TOKEN=<bridge token>` for workflow sync between GitHub Actions and SecureIT

The app also persists optional Key Vault metadata in `/var/www/data/admin-config.json` for display and future portability, but the runtime secret write path uses the Key Vault environment variables above.

## Permission requirements

The mounted `/var/www/data` path must be writable by the web process inside the container.

At minimum, the app may need to:
- create directories
- save tenant metadata
- save admin config
- read imported report bundles

Check ownership and permissions for the effective Apache/PHP user, which is expected to be `www-data` in the current image.

## Bootstrap or recovery requirements

Before a first deployment, or when rebuilding an empty runtime volume:
1. create or seed `tenants.json`
2. ensure `reports/` exists
3. import at least one tenant report bundle under `data/reports/<tenant-key>/latest/`
4. ensure `summary.json` exists for that tenant
5. ensure `embedded-summary.json` exists for that tenant so canonical control evidence is available

Without this, the app may still load, but there will be little useful content to verify.

The diagnostics page includes a temporary secret-write tool for existing tenants. Use it as a repair path, not as the normal onboarding flow.

## Workflow-to-runtime integration

The production workflow now publishes report bundles back into SecureIT through `report-import.php`, which means the workflow-to-app bridge is live rather than just theoretical.

Current available path:
1. GitHub workflow generates `output/<tenant-key>/...`
2. workflow prepares `app-import/<tenant-key>/...`
3. the workflow posts the bundle to `report-import.php`
4. `report-import.php` imports the bundle into app runtime storage and can send the tenant's HTML report summary email

The production workflow now validates the bundle before publication. `latest/summary.json` provides run totals and `latest/embedded-summary.json` provides the individual Maester evidence used by SecureIT canonical scoring. Missing embedded evidence causes the assessment/publish path to fail rather than importing a zero-evidence report.

Still to decide:
- whether the live host will also use a host-side sync/pull job or rely on the workflow push path alone
- whether manual import by operator should remain as a repair-only option
- whether any additional workflow completion summary should surface the imported report status in GitHub

The next agent should treat this as a priority integration decision.

## Authentication warning

The current app now uses an Entra ID-backed login flow in the codebase, but production sign-in is only real once the live app registration, redirect URIs, and logout URLs are configured and tested end to end.
The localhost-only seed identities (`fab@local` and `con@local`) are development conveniences and must not be treated as a live deployment path.

Before further customer exposure, and after authentication changes, confirm the live tenant configuration and sign-in routing:
- Entra redirect URIs are registered for `/auth/callback`
- logout return URLs and front-channel logout URLs are registered
- admin and customer access rules work as intended
- the first customer tenant can sign in without seeing any other tenant
- local `.local/identity-seeds.json` data is not mounted or relied on in the production container
- `/var/www/data/canonical-controls.json` should normally exist as the active catalog; the entrypoint synchronizes it from the versioned image seed, and the loader uses the bundled seed only when the runtime file is missing or invalid

Do not assume the fallback seed-based login path is the production auth model.

## Post-deploy validation checklist

After every live deploy, verify:
- `https://secureit.ict365.ky` loads
- login page loads
- portal/dashboard pages render without fatal errors
- tenant page works for a seeded tenant
- report URLs resolve correctly under `secureit.ict365.ky`
- admin settings save successfully to mounted storage
- container restarts cleanly without losing runtime data

## Recommended priorities for the next Codex agent

1. keep the tracked Portainer stack and GHCR-to-host deployment path aligned
2. add the workflow bridge and GitHub dispatch variables to the checked-in production stack before relying on those features there
3. revalidate the workflow-to-app report import path and email notification flow after deployment changes
4. validate mounted storage permissions and ownership
5. verify TLS and reverse-proxy behaviour for `secureit.ict365.ky`
6. revalidate customer/admin access isolation before broader exposure
7. run an end-to-end live test with at least one real imported tenant bundle

Current stack caveat: `deploy-handoff/docker/secureit/portainer-stack.yaml` does not yet forward `SECUREIT_WORKFLOW_SYNC_TOKEN` or the `SECUREIT_GITHUB_*` settings. The variables above describe the application contract, but the tracked production stack must be extended before tenant discovery, authenticated report import, or tenant-page workflow dispatch can be expected to work from that definition alone.

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
