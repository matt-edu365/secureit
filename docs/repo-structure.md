# Repository Structure

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Top-level structure

```text
SecureIT/
  .github/
    workflows/
      azure-keyvault-smoke-test.yml
      azure-oidc-diagnostic.yml
      docker-publish.yml
      maester-manual-run.yml
      maester-weekly.yml
  app/
    admin.php
    config.php
    dashboard.php
    index.php
    keyvault.php
    lib.php
    login.php
    onboard.php
    portal.php
    tenant.php
  config/
    admin-config.example.json
    canonical-controls.example.json
    .gitkeep
    tenants.example.json
    tenants.json
    tenants.schema-notes.md
  custom-tests/
    .gitkeep
  data/
    reports/
    tenants.example.json
    tenants.json
  deploy/
    config.tenants.example.json
    config.tenants.json
    maester/
      .htaccess
      admin-config.example.json
      admin-config.json
      admin.php
      index.php
      onboard.php
      tenant.php
      tenants.example.json
      tenants.json
  docker/
    apache-site.conf
  docs/
    build-plan.md
    container-test-plan.md
    handoff-codex-agent.md
    keyvault-integration-plan.md
    proxmox-deploy-plan.md
    repo-structure.md
    website-plan.md
  output/
    history/
    latest/
  scripts/
    Get-ResolvedTenantConfig.ps1
    Get-TenantConfig.ps1
    Import-AppReportBundle.ps1
    Invoke-MaesterRun.ps1
    New-SummaryJson.ps1
    Publish-WebsiteReport.ps1
  website/
    _theme.php
    admin.php
    dashboard.php
    ICT365-logo-1.0.png
    ICT365-logo-official.svg
    index.php
    login.php
    onboard.php
    portal.php
    README.md
    tenant.php
  Dockerfile
  docker-compose.yml
  NOTES.local.md
  README.md
  TODO.local.md
```

## Purpose of each major folder

### `.github/workflows`
GitHub Actions workflows for:
- SecureIT container publishing to GHCR
- manual Maester execution
- retained legacy Maester workflow paths
- Azure OIDC diagnostics
- Azure Key Vault smoke testing

### `app`
The current container-ready SecureIT application.

This is the main future-facing product surface and should be treated as the primary application path.

### `config`
Tracked configuration examples and schema notes for:
- tenant registry
- admin config
- canonical SecureIT control mappings

Note: some runtime JSON files are present locally in this repo checkout, but they should be treated as environment/runtime state rather than product source.

### `custom-tests`
Placeholder for future SecureIT-specific or Maester-adjacent custom tests.

### `data`
Runtime-oriented application data.

Expected uses:
- tenant metadata
- imported tenant report bundles
- app-readable report storage under `data/reports/<tenant-key>/...`

### `deploy`
Transitional deployment material.

Includes:
- environment-specific tenant config variants
- `deploy/maester/`, the legacy shared-host deployment bundle for the current prototype

### `docker`
Container web-server configuration used by the Docker image.

### `docs`
Architecture, deployment, planning, and handoff notes.

### `output`
Runner-generated report artifacts from workflow or manual Maester runs.

### `scripts`
PowerShell helper scripts for:
- tenant config resolution
- Maester execution
- summary generation
- report publishing
- app report bundle import

### `website`
Legacy shared-host prototype source.

This remains useful while the shared-host prototype is still live, but it is no longer the preferred long-term application surface.

## Current direction

The repo currently contains three overlapping layers:

1. **Maester execution layer**
   - workflows and PowerShell scripts that run assessments and produce output

2. **Legacy prototype presentation layer**
   - `website/` and `deploy/maester/`
   - shared-host oriented
   - still useful for current prototype continuity

3. **Future SecureIT application layer**
   - `app/`
   - container-first
   - intended for GHCR + Docker + Proxmox deployment

## Branding and naming reality

This repository is partway through a Maester -> SecureIT adaptation.

That means:
- directory and workflow names may still contain `maester`
- customer-facing UI and docs should prefer `SecureIT`
- Maester should be described as the underlying engine rather than the product identity

## Practical recommendation

For new work, prefer touching these areas first unless there is a specific reason not to:
- `app/`
- `scripts/`
- `.github/workflows/maester-manual-run.yml`
- `docs/`

Treat these as transitional unless required:
- `website/`
- `deploy/maester/`
- `maester-weekly.yml`
