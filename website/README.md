# Legacy Shared-Host Prototype Source

## GitHub repository

- `https://github.com/matt-edu365/secureit`

This folder contains the original PHP prototype source from the earlier shared-host deployment model.

## Important

This is no longer the primary application surface.

The preferred future-facing SecureIT application lives in:
- `app/`

The current shared-host deployable bundle lives in:
- `deploy/maester/`

## Branding note: Maester -> SecureIT

This prototype grew out of the original Maester-based reporting effort.

That means some technical structure, assumptions, or older wording may still reflect that origin.

Current interpretation should be:
- **SecureIT** is the customer-facing product identity
- **Maester** is the underlying assessment/report engine

## Why this folder still exists

It is being kept temporarily so the live prototype can remain available while SecureIT is refactored toward:
- Docker
- GHCR
- Proxmox deployment
- the container-ready app in `app/`

## Intended future state

Once the Docker-based SecureIT deployment replaces the shared-host prototype, this folder should be removed or archived.

## Practical rule

Avoid doing major new feature work here unless it is necessary to preserve the current live prototype during the transition.

Prefer new product-facing work in:
- `app/`
- `scripts/`
- `.github/workflows/`
- `docs/`
