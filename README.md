# SecureIT

SecureIT is ICT365's multi-tenant Microsoft 365 security reporting and posture portal, adapted from a Maester-driven reporting workflow into a customer-facing SecureIT product.

GitHub repository:
- `https://github.com/matt-edu365/secureit`

## What this repo is now

This repository is no longer just an initial scaffold.

It currently contains:
- GitHub Actions workflows for manual and legacy Maester runs
- a container-ready SecureIT PHP app in `app/`
- a legacy shared-host prototype and deployment bundle in `website/` and `deploy/maester/`
- PowerShell tooling for tenant resolution, Maester execution, summary generation, report publishing, and app-bundle import
- SecureIT-specific canonical control mapping examples and functional-area scoring scaffolding
- Azure OIDC and Azure Key Vault diagnostic workflows

## Product direction

SecureIT started as a Maester automation and report-publication prototype.

The current direction is:
- keep Maester as the underlying assessment engine
- adapt the presentation, structure, workflow, and customer experience into SecureIT
- move from raw Maester-oriented report handling toward SecureIT-branded tenant dashboards, onboarding, and customer-facing control areas
- keep traceability back to Maester output while avoiding exposing Maester internals directly as the product surface

In short:
- **Maester remains the engine**
- **SecureIT is the product and customer-facing layer**

## Branding adaptation: Maester -> SecureIT

The repo is in an active branding transition.

That means:
- report generation still comes from Maester
- older workflow names, deploy paths, and some historical docs still refer to Maester
- the UI, dashboard, portal wording, and customer-facing language are being standardised around SecureIT
- some legacy paths still use `maester` in filenames or directories for compatibility with the shared-host prototype

Current practical rule:
- use **SecureIT** in documentation, UI copy, customer-facing language, and architecture descriptions
- treat **Maester** as the underlying technical dependency or report engine where relevant
- do not rename legacy compatibility paths unless the deployment impact is understood first

## Current repository layout

```text
.github/
  workflows/
app/
config/
custom-tests/
data/
deploy/
  maester/
docker/
docs/
output/
scripts/
website/
Dockerfile
docker-compose.yml
README.md
```

## Main application surfaces

### 1. `app/`
The current SecureIT application.

Purpose:
- container-ready PHP app
- intended target for Docker, GHCR, and Proxmox deployment
- reads tenant metadata and report bundles from writable runtime storage
- includes dashboard, login, portal, tenant, admin, onboarding, and Key Vault-related surfaces

### 2. `website/`
Legacy shared-host prototype source.

Purpose:
- keeps the original shared-host prototype editable while the newer app evolves
- still useful for reference and compatibility while `example.ict365.uk` remains in play

### 3. `deploy/maester/`
Legacy deployable shared-host bundle.

Purpose:
- current deploy-target bundle for the prototype environment on `example.ict365.uk`
- transitional only, not the final product deployment model

## GitHub Actions workflows

Current workflows present in the repo:
- `docker-publish.yml`
  - manually builds and publishes the SecureIT container image to GHCR
- `maester-manual-run.yml`
  - the main modern manual-run workflow, supports certificate and client-secret auth, test profile selection, FTPS publish, and app-import bundle creation
- `maester-weekly.yml`
  - legacy weekly/manual workflow path still retained in the repo
- `azure-oidc-diagnostic.yml`
  - validates Azure OIDC login assumptions
- `azure-keyvault-smoke-test.yml`
  - tests Azure Key Vault secret retrieval via OIDC login

## Runtime and configuration model

Tracked example files are intended as templates.

Important tracked examples:
- `config/tenants.example.json`
- `config/canonical-controls.example.json`
- `data/tenants.example.json`
- `deploy/config.tenants.example.json`
- `deploy/maester/tenants.example.json`
- `deploy/maester/admin-config.example.json`
- `config/admin-config.example.json`

Typical runtime files:
- `config/tenants.json`
- `data/tenants.json`
- `deploy/config.tenants.json`
- `deploy/maester/tenants.json`
- `deploy/maester/admin-config.json`

These runtime files are environment-specific and should be treated carefully.

## App report import flow

The repo now supports a cleaner app-facing report import path.

Generated output can be prepared into:
- `app-import/<tenant-key>/...`

Then imported into app runtime storage with:

```powershell
pwsh ./scripts/Import-AppReportBundle.ps1 -TenantKey ict365 -SourcePath ./app-import/ict365
```

Default destination:
- `data/reports/<tenant-key>/...`

This is an important bridge between Maester-run output and the SecureIT app runtime.

## SecureIT functional areas and canonical controls

SecureIT is moving away from treating every raw Maester framework item as an independent customer-facing control.

Intended model:
- keep raw Maester output for traceability
- collapse duplicate framework checks into canonical SecureIT controls
- map those controls into SecureIT functional areas
- derive customer-facing posture from canonical SecureIT controls instead of duplicated raw test IDs

Tracked example mapping:
- `config/canonical-controls.example.json`

## Deployment direction

### Current prototype
- shared-host prototype at `https://example.ict365.uk`
- deploy bundle under `deploy/maester/`
- legacy website source under `website/`

### Planned product path
- SecureIT app in `app/`
- Docker image built from `Dockerfile`
- image published to GitHub Container Registry
- target image name: `ghcr.io/matt-edu365/secureit`
- intended runtime on Proxmox or equivalent Docker host
- planned product hostname: `https://secureit.ict365.ky`

## Current status summary

What is already true:
- the GitHub repo exists and is active
- SecureIT branding is already being applied across docs and app surfaces
- the app surface is more advanced than the legacy prototype
- manual Maester execution workflow exists and supports app-import bundle preparation
- Azure OIDC and Key Vault diagnostic workflows exist
- there is still technical and directory debt from the original Maester prototype

What still needs deliberate cleanup:
- reduce documentation drift
- decide when to retire `website/` and `deploy/maester/`
- align workflow naming and legacy labels with current SecureIT reality
- continue moving customer-facing language from Maester terminology to SecureIT terminology
- complete the handoff from prototype publication to app-first deployment

## Recommended working rule

Keep these aligned whenever practical:
1. local working copy
2. GitHub repo
3. prototype or target runtime environment

If they differ intentionally, document the drift clearly in a commit, note, or handoff.

## Handoff

For the next Codex-based agent, start with:
- `README.md`
- `docs/repo-structure.md`
- `docs/build-plan.md`
- `docs/website-plan.md`
- `docs/handoff-codex-agent.md`

That handoff doc is intended to be the continuation reference for the next agent.
