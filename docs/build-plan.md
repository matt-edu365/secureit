# SecureIT Build Plan

## Objective

Build a multi-tenant Microsoft 365 security monitoring solution using Maester, with scheduled and manual runs, weekly email reporting, and a protected website dashboard for viewing results.

## Recommended architecture

- GitHub Actions runs Maester weekly and on demand
- Tenant metadata is stored in a tenant registry file
- Each tenant uses its own app-only access configuration
- Reports are generated as HTML, JSON, and a lightweight summary JSON per tenant
- Reports are uploaded to a protected folder on the target website under tenant-specific paths
- A small dashboard page on the website shows latest status and report history per tenant
- Weekly email sends a concise summary with a link to the latest tenant report

## Phases

### Phase 1: Local proof and tenant auth
1. Create Entra app registration for Maester automation
2. Prefer GitHub OIDC federation, use certificate auth if OIDC flow is awkward
3. Grant least-privilege Graph application permissions required by Maester
4. Admin-consent the permissions
5. On a trusted admin machine, install PowerShell 7 and Maester
6. Run a local proof test to validate auth and report generation

### Phase 2: GitHub repository and workflow
1. Create private GitHub repo for this project
2. Push this scaffold into the repo
3. Create `config/tenants.json` from the example file
4. Configure GitHub Actions secrets or environment values for each tenant:
   - tenant ID
   - client ID
   - certificate material if used
   - website deploy credentials
   - SMTP credentials if needed
5. Implement the GitHub Actions workflow with:
   - schedule trigger
   - workflow_dispatch trigger
   - matrix or targeted tenant selection
   - PowerShell setup
   - Maester install/import
   - tenant authentication
   - test run
   - report generation
   - artifact upload

### Phase 3: Report publishing
1. Standardise output structure:
   - output/<tenant-key>/latest/
   - output/<tenant-key>/history/YYYY-MM-DD/
2. Publish latest and history folders to the target website under tenant-specific paths
3. Use SFTP or FTPS upload first unless a custom API endpoint is preferred
4. Protect the report area with login/basic auth/IP restriction

### Phase 4: Dashboard and reporting
1. Create website dashboard page
2. Read summary JSON from tenant latest folders
3. Show:
   - tenant name
   - tenant key
   - last run time
   - pass/fail/skipped counts
   - link to latest report
   - report history
4. Add weekly summary email
5. Add previous-run comparison to highlight new failures and resolved issues

### Phase 5: Manual run UX
1. Start with GitHub's built-in Run workflow button and choose a tenant key
2. Later add website-side Run now button per tenant
3. Website trigger should call GitHub workflow_dispatch API, not execute Maester locally

## Recommended output structure

```text
output/
  tenant-a/
    latest/
      index.html
      results.json
      summary.json
    history/
      YYYY-MM-DD/
        timestamp/
          index.html
          results.json
          summary.json
  tenant-b/
    latest/
    history/
```

## Suggested website structure

```text
/secureit/
  index.php
  tenant-a/
    latest/
      index.html
      summary.json
    history/
      YYYY-MM-DD/
        timestamp/
          index.html
  tenant-b/
```

## Initial deliverables

- Private GitHub repo
- Working local proof run
- Working scheduled and manual GitHub Actions workflow
- Latest and historical reports published to website
- Protected dashboard page
- Weekly summary email

## Notes

Treat Maester reports as sensitive. Do not expose them publicly. Keep execution in GitHub Actions and use the website as the presentation and control layer.
