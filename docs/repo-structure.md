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
    tenant.php
  config/
    admin-config.example.json
    canonical-controls.example.json
    tenants.example.json
    tenants.json
    tenants.schema-notes.md
  custom-tests/
  data/
    reports/
    tenants.example.json
    tenants.json
  docs/
    build-plan.md
    container-test-plan.md
    handoff-codex-agent.md
    keyvault-integration-plan.md
    live-deployment-handoff.md
    proxmox-deploy-plan.md
    repo-structure.md
  output/
    history/
    latest/
  scripts/
    Get-ResolvedTenantConfig.ps1
    Get-TenantConfig.ps1
    Import-AppReportBundle.ps1
    Invoke-MaesterRun.ps1
    New-SummaryJson.ps1
    seed-local-demo-data.sh
  shared/
    functional-areas.php
  Dockerfile
  docker-compose.yml
  README.md
```

## Purpose of each major area

### `.github/workflows`
Automation for:
- SecureIT image publishing
- manual Maester runs
- retained diagnostic workflows for Azure and Key Vault

### `app`
The primary SecureIT application.

### `config`
Tracked example configuration and schema notes.

### `custom-tests`
Placeholder for SecureIT-specific or Maester-adjacent tests.

### `data`
Runtime-mounted tenant and report data.

### `docs`
The current architecture, deployment, and handoff documentation.

### `output`
Workflow-generated report artifacts before they are imported into the app runtime.

### `scripts`
PowerShell and shell helpers for tenant config, Maester runs, summary generation, and report import.

### `shared`
Shared runtime helpers used by the app and any aligned companion surfaces.

## Current architecture

The repository is organised around three layers:

1. assessment engine and workflow automation
2. shared runtime helpers
3. Docker-based SecureIT application runtime

There is no separate shared-host or simulated web layer in the current stack.

## Practical rule

For new work, prefer:
- `app/`
- `shared/`
- `scripts/`
- `.github/workflows/`
- `docs/`

Treat any legacy filesystem names that remain for compatibility as implementation detail only.
