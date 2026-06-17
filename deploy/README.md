# Legacy Shared-Host Deployment Bundle

## GitHub repository

- `https://github.com/matt-edu365/secureit`

This folder contains the temporary shared-host deployment material used to keep the SecureIT prototype live on `example.ict365.uk`.

## What it is

This is a transitional deployment path.

It exists so the legacy/shared-host prototype can keep running while the container-first SecureIT application continues to mature.

## What it is not

It is not the intended long-term deployment model.

The long-term direction is:
- SecureIT app in `app/`
- Docker image built from `Dockerfile`
- image published to GitHub Container Registry
- runtime deployment on Proxmox or equivalent Docker host

## Branding note: Maester -> SecureIT

This folder still contains legacy `maester` naming in places because it grew out of the original Maester prototype.

Interpret that naming as technical legacy, not product identity.

Customer-facing product identity should now be treated as:
- **SecureIT**

Underlying assessment/report engine:
- **Maester**

Do not rename compatibility-sensitive folders here casually unless the prototype deployment impact is understood first.

## Current contents

This area currently includes:
- shared-host deploy bundle pieces
- prototype tenant config variants
- `deploy/maester/` as the current shared-host-compatible app bundle

## Intended future state

Once the SecureIT app is running from Docker and the shared-host prototype is no longer needed:
- this deployment path should be retired or greatly reduced
- remaining useful artifacts should be migrated or documented elsewhere
