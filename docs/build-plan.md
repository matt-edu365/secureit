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
- canonical controls live in `/var/www/data/canonical-controls.json`; the versioned image seed is used to create or refresh that runtime copy and remains the loader fallback
- shared runtime helpers are baked into the image

## Workflow-to-app bridge

The assessment engine still runs separately from the app runtime:
1. GitHub Actions runs Maester
2. workflow output is prepared for app import
3. imported bundles are written into mounted runtime storage
4. the SecureIT app reads the imported bundle

The production publication contract requires both `latest/summary.json` and `latest/embedded-summary.json`. The latter contains the individual Maester evidence needed for canonical SecureIT control scoring; the production runner and workflow refuse to publish a summary-only bundle. The generic `report-import.php` endpoint currently validates `latest/summary.json` but relies on its authenticated producer to supply `latest/embedded-summary.json`.

## Extending test coverage

- Keep `APPREGISTRATIONS`, `MTAPPREGISTRATIONOWNERSWITHOUTMFA`, `MTHIGHRISKAPPPERMISSIONS`, `XSPMDEVICES`, `XSPMPRIVILEGEDIDENTITIES`, and `MTMDIHEALTHISSUES` outside the normal production contract. They were removed when production moved to 94 controls because they are long-running, preview, or unable to provide a dependable result with the current integration.
- If any removed control is reconsidered, first prove its runtime and evidence semantics in a separate opt-in profile. Reintroduce it to the canonical contract and production totals only after it produces a deterministic pass, fail, skip, or actionable error for supported tenants.
- Add service-aware onboarding for Exchange Online, Teams, Azure, SharePoint Online, Entra P2/Governance, Defender XDR/Exposure Management, Intune, and hybrid identity. Record which capabilities are available and keep unavailable licensed services outside the score denominator.
- Add each service connection only with its matching preflight: Exchange application permission and RBAC, Teams application authentication and reader role, Azure RBAC, and an explicit certificate-based SharePoint app-only option. Do not silently add high-privilege SharePoint access to client-secret onboarding.
- API failures, missing permissions, and non-assertion runner exceptions are now classified as non-scoreable `Error`, with required permissions shown where known. Continue separating missing service connections, unlicensed/not-applicable features, deliberate preview exclusions, and unsupported upstream tests instead of leaving them under generic non-scoreable explanations.
- For any future `Test-MtMdiHealthIssues.Tests.ps1` experiment, treat zero returned health issues as a pass only when the Defender for Identity request is known to have succeeded; permission, connection, and API failures must remain coverage gaps.

## Portal user-interface follow-ups

- [ ] **Restore line graphs in Functional Area views.** Reintroduce useful per-area score history once the view can plot meaningful historical results consistently.
- [ ] **Remove useless trend data cards in Functional Area views.** Do not render an empty parent card or the nested "No trend data yet" placeholder when an area has no plottable history.

## Success condition

The repo should converge on a state where:
- the app is Docker-first
- the runtime is reproducible from the repository
- no shared-host path is required to test SecureIT
- documentation describes the same stack the container runs
