# Repository Structure

```text
SecureIt/
  .github/
    workflows/
      docker-publish.yml
      maester-weekly.yml
  app/
    config.php
    index.php
    lib.php
    onboard.php
    tenant.php
  config/
    .gitkeep
    tenants.example.json
    tenants.json
    tenants.schema-notes.md
  custom-tests/
    .gitkeep
  data/
    tenants.json
    reports/
  deploy/
    maester/
      ...legacy shared-host prototype bundle...
  docker/
    apache-site.conf
  docs/
    build-plan.md
    proxmox-deploy-plan.md
    repo-structure.md
    website-plan.md
  output/
    latest/
      .gitkeep
    history/
      .gitkeep
  scripts/
    Get-ResolvedTenantConfig.ps1
    Get-TenantConfig.ps1
    Invoke-MaesterRun.ps1
    New-SummaryJson.ps1
    Publish-WebsiteReport.ps1
  website/
    ...legacy shared-host prototype source...
  .gitignore
  Dockerfile
  docker-compose.yml
  README.md
```

## Purpose of each folder

- `.github/workflows`: CI for container publishing and Maester assessment runs
- `app`: the current SecureIt container-ready application
- `config`: tenant registry examples and runner-side config
- `custom-tests`: future custom Maester or Pester tests
- `data`: runtime tenant metadata and report storage for the app
- `deploy`: current shared-host deployment bundle used for `example.ict365.uk`
- `docker`: web-server config for the container image
- `docs`: architecture, deployment, and implementation notes
- `output`: runner-generated report artifacts, separated per tenant key
- `scripts`: PowerShell helpers for tenant resolution, running, summarising, and publishing
- `website`: legacy shared-host prototype source kept temporarily while the app is refactored

## Current direction

The `app/` folder is the future. The `website/` and `deploy/` folders are transitional and should be retired once the Docker-based SecureIt deployment replaces the shared-host prototype.
