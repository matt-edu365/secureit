# SecureIT Web App Plan

## Purpose

Provide a protected browser-based view of tenant security posture, onboarding, and report history, while Maester execution continues to run separately through GitHub Actions.

## Current state

Two web surfaces exist right now:

1. `app/`
   - the container-ready SecureIT application
   - intended for Docker, GHCR, and Proxmox deployment
   - reads tenants and reports from writable runtime storage

2. `website/` + `deploy/maester/`
   - the shared-host prototype currently published to `example.ict365.uk`
   - kept temporarily so the prototype stays live while the container app is prepared

## Planned production path

- App name: `SecureIT`
- Production hostname: `secureit.ict365.ky`
- Delivery model: Docker image from GitHub Container Registry, pulled by Proxmox

## Runtime paths for the app

The container-ready app expects:

- tenant metadata file: `/var/www/data/tenants.json`
- reports root: `/var/www/data/reports`

Example structure:

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

## Security

- Do not expose the portal publicly without auth in front of it
- Treat reports as sensitive tenant configuration data
- Keep writable data outside the container image
- Use Azure Key Vault for secret storage in the planned production design

## Manual run later

The app should not execute Maester locally.

Preferred pattern:
- SecureIT app triggers GitHub workflow dispatch through an authenticated backend flow
- GitHub Actions performs the actual Maester run
- Results are then published back into the app's report storage
