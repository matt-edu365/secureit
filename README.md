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

Manual Maester runs currently support these test profiles:
- `graph-baseline` , the recommended standard onboarding baseline, focused on reliable Graph-first Entra, identity, compliance, configuration, SharePoint, and Intune-adjacent checks without requiring Exchange Online, Teams, Azure, or Azure DevOps connections
- `light` , a smaller Graph-first baseline for faster validation runs
- `exchange-online` , an advanced Exchange Online focused profile for EXO and Defender for Office style checks, which requires additional Exchange app auth and RBAC setup
- `full` , the full installed Maester test inventory

## Graph baseline policy

`graph-baseline` is intended to be the sane default onboarding profile for new tenants.

It keeps tests that are meaningful under app-only Microsoft Graph authentication, including conditionally useful tests that may skip because a tenant does not use a specific feature yet, for example:
- FIDO2 method checks when FIDO2 is not enabled
- Temporary Access Pass or SMS sign-in checks when those methods are not enabled
- Hybrid sync checks when on-premises sync is not configured
- Intune connector and Apple enrollment checks when those components are not present in the tenant

It excludes tests that were shown in tenant validation to be unreliable or structurally outside the Graph-first onboarding model.

### Excluded from graph-baseline

These tests are intentionally not part of the onboarding baseline because they require extra connections, produce known selector noise, or depend on deprecated settings.

- **Azure DevOps family**
  - Excluded because they require a separate Azure DevOps connection and otherwise produce guaranteed skips such as `Not connected to Azure DevOps`.
- **Teams meeting policy checks**
  - `MT.1037`
  - `MT.1042`
  - `MT.1046`
  - `MT.1047`
  - `MT.1048`
  - Excluded because they require a Teams connection and are not part of Graph-only onboarding.
- **Azure-connected checks**
  - `MT.1050`
  - `MT.1100`
  - Excluded because they require an Azure connection beyond the current Graph-first auth path.
- **AI agent security family**
  - `MT.1111`
  - `MT.1114`
  - `MT.1115`
  - `MT.1116`
  - `MT.1117`
  - `MT.1118`
  - `MT.1119`
  - `MT.1120`
  - `MT.1121`
  - `MT.1122`
  - Excluded because they were not reliable in tenant validation and are outside the current standard onboarding target.
- **Deprecated or unstable setting checks**
  - `CISA.MS.AAD.5.4`
    - Excluded because the referenced setting is no longer available for some tenants and produced a skip due to API or platform drift.
  - `EIDSCA.CP01`
    - Excluded because the underlying group-owner consent setting has been removed and replaced with team-owner consent behavior.

This exclusion list is based on validation against the current ICT365 tenant and should be revised only when SecureIt adds the corresponding connection model or Maester fixes the deprecated checks.

At this stage:
- there are no live tenants configured
- no production secrets or certificates are stored here
- sample tenant and report artifacts have been removed from version control
- local/runtime tenant files should be created from examples and kept out of Git

## Working alignment rule

For this project, keep the three environments aligned as work progresses:
- local working copy
- GitHub repository
- prototype environment at `example.ict365.uk`

Default workflow:
1. make changes locally
2. commit and push to GitHub
3. sync the prototype environment to match
4. verify there is no unexpected drift

If one environment intentionally differs for a short period, document it clearly in the commit, notes, or handoff.

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
