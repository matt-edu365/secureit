# SecureIt

SecureIt is a multi-tenant Microsoft 365 security monitoring portal built around Maester.

It is intended to provide:
- Scheduled Maester security assessments via GitHub Actions
- Manual on-demand runs via GitHub Actions, with a web trigger planned later
- Multi-tenant execution driven by tenant registry configuration
- Static HTML and JSON report output per tenant
- Publication of reports to a protected web portal
- Weekly summary notifications per tenant
- A container-friendly web UI for dashboarding and tenant onboarding

## Current status

This repository is the early project scaffold.

At this stage:
- there are no live tenants configured
- no production secrets or certificates are stored here
- sample tenant and report artifacts have been removed from version control
- local/runtime tenant files should be created from examples and kept out of Git

## Planned architecture

- App name: SecureIt
- Dev URL: `https://example.ict365.uk`
- Planned production URL: `https://secureit.ict365.ky`
- Execution: GitHub Actions
- Secrets: Azure Key Vault (planned)
- Tenant authentication: Entra app registration with certificate auth first
- Report output: HTML + JSON + summary JSON
- App delivery: Docker image published to GitHub Container Registry and pulled by Proxmox
- UI: Containerised dashboard and onboarding portal
- Notifications: Weekly summary email

## Repository layout

```text
.github/
  workflows/
app/
config/
custom-tests/
data/
deploy/
docker/
docs/
output/
scripts/
website/
```

## Configuration approach

Tracked example files should be used as templates.

Available tracked templates:
- `config/tenants.example.json`
- `data/tenants.example.json`
- `deploy/config.tenants.example.json`
- `deploy/maester/tenants.example.json`

Expected local/runtime files include:
- `config/tenants.json`
- `data/tenants.json`
- `deploy/config.tenants.json`
- `deploy/maester/tenants.json`

Those runtime files are ignored so real tenant metadata, report locations, and environment-specific settings do not get committed by accident.

## Next sensible steps

- Add canonical `*.example.json` files for each tenant registry location
- Decide which single source of truth should own tenant definitions
- Wire GitHub Actions to publish generated reports into the deploy structure
- Replace prototype wording in the PHP UI once the real flow is settled
- Tighten onboarding so it writes only to the intended runtime location
