# SecureIT Web App Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Purpose

Provide a protected browser-based SecureIT experience for:
- tenant posture visibility
- onboarding and configuration
- report browsing and historical review
- eventually app-mediated workflow triggering

while keeping Maester itself as the underlying assessment engine rather than the customer-facing product.

## Current web surfaces

There are currently two overlapping web/application surfaces.

### 1. `app/`
The current SecureIT application.

Characteristics:
- container-ready PHP surface
- intended long-term product path
- reads from runtime storage under `data/`
- includes login, portal, dashboard, tenant, onboarding, admin, and Key Vault-related pages

This should be treated as the primary future-facing app.

### 2. `website/` plus `deploy/maester/`
The legacy shared-host prototype.

Characteristics:
- tied to the `example.ict365.uk` prototype environment
- still useful while the prototype remains live
- carries some older Maester-oriented naming and deployment assumptions

This should be treated as transitional.

## Branding adaptation

This project is actively moving from a Maester-branded prototype to a SecureIT product.

Implications for the web layer:
- customer-facing copy should prefer SecureIT language
- Maester should be referenced only when explaining the underlying report engine
- older filenames, workflow names, and deploy paths may still contain `maester`
- those technical names can remain temporarily where changing them would create deployment churn

## Planned product path

- App name: `SecureIT`
- Repository: `https://github.com/matt-edu365/secureit`
- Prototype host: `https://example.ict365.uk`
- Planned product host: `https://secureit.ict365.ky`
- Delivery model: Docker image from GHCR, deployed to Proxmox or equivalent Docker runtime

## Runtime paths for the app

The container-ready app expects runtime data outside the image.

Primary paths:
- tenant metadata file: `/var/www/data/tenants.json`
- reports root: `/var/www/data/reports`
- canonical control runtime file, if used: `/var/www/data/canonical-controls.json`

Expected structure:

```text
/var/www/data/
  tenants.json
  reports/
    tenant-a/
      latest/
        index.html
        summary.json
        results.json
      history/
        YYYY-MM-DD/
          timestamp/
            index.html
            summary.json
            results.json
```

## Report ingestion path

The app is not expected to generate Maester reports locally.

Preferred flow:
1. GitHub Actions runs Maester
2. workflow output is produced under `output/<tenant-key>/...`
3. a workflow or operator prepares `app-import/<tenant-key>/...`
4. `Import-AppReportBundle.ps1` imports that into the app runtime report store
5. the SecureIT app reads and presents the imported bundle

This is one of the most important bridges in the current architecture.

## Security expectations

- do not expose the portal publicly without authentication in front of it
- treat report output as sensitive tenant configuration and posture data
- keep writable data outside the container image
- prefer Key Vault backed secret handling where the current code path supports it
- avoid storing production secrets directly in Git-tracked source

## Functional-area scoring goal

The app should increasingly present SecureIT-native posture views instead of raw framework duplication.

Target experience:
- customer sees SecureIT functional areas and posture signals
- technical traceability still exists back to Maester-generated source data
- duplicate framework controls are collapsed into canonical SecureIT controls before customer-facing scoring

## Manual run model later

The preferred long-term pattern remains:
- SecureIT app initiates an authenticated backend request
- GitHub workflow dispatch performs the actual assessment
- results come back into app-readable storage

The app should remain a control and presentation layer, not a local Maester execution host.

## Practical near-term recommendation

When adding features, bias work toward:
- `app/`
- `scripts/Import-AppReportBundle.ps1`
- the workflow-to-app import contract
- SecureIT-native UI language

Only extend `website/` or `deploy/maester/` when needed to preserve the live prototype during the transition.
