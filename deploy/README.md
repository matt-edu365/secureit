# Legacy Shared-Host Deployment Bundle

This folder contains the temporary shared-host deployment bundle for the live prototype at `example.ict365.uk`.

## Important

This is not the long-term deployment model.

The planned production model is:
- SecureIt app in `app/`
- Docker image built from `Dockerfile`
- Image published to GitHub Container Registry
- Pulled and run on Proxmox

## Why this folder still exists

It allows the prototype to stay live on shared hosting during the refactor.

## Intended future state

Once SecureIt is running from Docker on Proxmox, this folder should be retired.
