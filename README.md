# SecureIt

SecureIt is a multi-tenant Microsoft 365 security monitoring portal built around Maester.

This project is intended to provide:
- Scheduled Maester security assessments via GitHub Actions
- Manual on-demand runs via GitHub Actions and later via a website trigger
- Multi-tenant config-driven execution
- Static HTML and JSON report output per tenant
- Publication of reports to a protected web portal
- Weekly summary email notifications per tenant
- A container-friendly web UI for dashboarding and tenant onboarding

## Planned architecture

- App name: SecureIt
- Dev URL: https://example.ict365.uk
- Planned production URL: https://secureit.ict365.ky
- Execution: GitHub Actions
- Secrets: Azure Key Vault (planned)
- Authentication to tenant: Entra app registration with certificate auth first
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
docs/
output/
scripts/
```

## Multi-tenant design

- Tenant metadata is stored in a registry file.
- Scheduled runs can iterate across multiple tenant keys.
- Manual runs can target a specific tenant key.
- Output is separated per tenant under `output/<tenant-key>/`.
- Tenant report base URLs follow the pattern `https://<host>/<tenant-key>`.

## Status

Prototype running on `example.ict365.uk`.
Refactor in progress toward a container-friendly SecureIt app deployable through GitHub Container Registry to a Proxmox Docker environment.
