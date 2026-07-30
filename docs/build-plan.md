# SecureIT Build Plan

## Objective

Keep SecureIT on a single Docker-based development and testing path so the code you build locally is the same shape you deploy.

## Operating rule

- build every test environment as a Docker image
- mount runtime data outside the image
- keep shared runtime logic in `shared/`
- keep the app runtime in `app/`

That avoids a second simulated stack and prevents test-only changes from drifting away from the deployed app.

## Current model

Already present:
- container-ready SecureIT app in `app/`
- shared runtime helpers in `shared/`
- Dockerfile-based build
- `docker-compose.yml` for local testing
- report import bridge into mounted runtime storage
- diagnostic workflows for Azure and Key Vault
- production report-summary parsing independent of Maester's minified JavaScript variable names
- pinned production Maester module and test-suite inputs

## Build priorities

1. Keep the Docker build and compose path working first
2. Keep shared runtime rules in one place
3. Keep the app and any companion surfaces aligned through the shared helper
4. Keep docs aligned with the Docker-only stack
5. Keep Maester as the backend engine, not the local web runtime
6. Reject incomplete report bundles before they reach the SecureIT portal

## Runtime contract

The container should assume:
- tenant metadata lives in `/var/www/data/tenants.json`
- reports live in `/var/www/data/reports`
- canonical controls, if used, live in `/var/www/data/canonical-controls.json`, but the app can fall back to the bundled image copy for the homepage total
- shared runtime helpers are baked into the image

## Workflow-to-app bridge

The assessment engine still runs separately from the app runtime:
1. GitHub Actions runs Maester
2. workflow output is prepared for app import
3. imported bundles are written into mounted runtime storage
4. the SecureIT app reads the imported bundle

The production bundle contract requires both `latest/summary.json` and `latest/embedded-summary.json`. The latter contains the individual Maester evidence needed for canonical SecureIT control scoring; a summary-only bundle must not be published.

## Success condition

The repo should converge on a state where:
- the app is Docker-first
- the runtime is reproducible from the repository
- no shared-host path is required to test SecureIT
- documentation describes the same stack the container runs
