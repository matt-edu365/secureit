# SecureIT Handoff for Next Codex Agent

## Repository

- GitHub: `https://github.com/matt-edu365/secureit`
- Working repo path on NAS: `/home/matt/nas/workfiles/Work Projects/SecureIT`

## What SecureIT is

SecureIT is ICT365's customer-facing multi-tenant Microsoft 365 security reporting portal.

Important framing:
- **Maester is the underlying assessment engine**
- **SecureIT is the product, portal, and branding layer**

The repo started life as a Maester reporting prototype and is in an active transition toward a more polished SecureIT product.

## Current state summary

The repo currently contains three overlapping layers:

1. **Assessment engine layer**
   - GitHub Actions workflows
   - PowerShell scripts
   - raw and summarised report generation

2. **Legacy prototype layer**
   - `website/`
   - `deploy/maester/`
   - shared-host oriented, still useful while `example.ict365.uk` remains live

3. **Future product layer**
   - `app/`
   - container-first SecureIT application
   - intended long-term path for GHCR + Docker + Proxmox deployment

## Files to read first

Read these before making architecture decisions:
- `README.md`
- `docs/repo-structure.md`
- `docs/build-plan.md`
- `docs/website-plan.md`
- `docs/keyvault-integration-plan.md`
- `docs/proxmox-deploy-plan.md`

Then inspect:
- `.github/workflows/maester-manual-run.yml`
- `.github/workflows/maester-weekly.yml`
- `scripts/Import-AppReportBundle.ps1`
- `app/config.php`

## Current working assumptions

### Branding
- Use **SecureIT** in docs, UI copy, and customer-facing descriptions
- Use **Maester** only when referring to the underlying engine, raw reports, or technical dependencies
- Some old filenames and folder names still contain `maester`; treat those as legacy compatibility unless intentionally refactoring them

### Deployment
- Current prototype host: `https://example.ict365.uk`
- Planned product host: `https://secureit.ict365.ky`
- Container registry target: `ghcr.io/matt-edu365/secureit`

### App runtime model
The long-term app expects runtime data outside the image:
- tenants file: `/var/www/data/tenants.json`
- reports root: `/var/www/data/reports`

### Workflow-to-app bridge
Current important bridge:
1. workflow generates `output/<tenant-key>/...`
2. workflow can prepare `app-import/<tenant-key>/...`
3. `scripts/Import-AppReportBundle.ps1` imports into app runtime storage

This bridge matters. Avoid breaking it casually.

## Known repo tensions

These are the main areas of architectural tension right now:

1. **Legacy vs future app surface**
   - `website/` and `deploy/maester/` still exist and still matter operationally
   - `app/` is the preferred long-term path

2. **Workflow duplication / drift**
   - `maester-manual-run.yml` looks like the modern path
   - `maester-weekly.yml` is explicitly legacy but still present
   - naming and behaviour should probably be rationalised

3. **Config source-of-truth ambiguity**
   - tenant-related state exists across `config/`, `data/`, and `deploy/`
   - it is not fully settled which layer should own what permanently

4. **Branding adaptation still incomplete**
   - docs and UI are partly standardised
   - technical names still expose the Maester origin story

5. **Canonical control scoring is only scaffolded**
   - `config/canonical-controls.example.json` exists as a direction
   - likely still needs significant extension before the app can show mature SecureIT-native scoring

## Recommended priorities for the next agent

### High-value priorities
1. Clarify the single authoritative workflow path
2. Clarify the single authoritative tenant/config ownership model
3. Strengthen the app-import and app-read report contract
4. Continue customer-facing language cleanup toward SecureIT
5. Decide retirement criteria for `website/` and `deploy/maester/`

### Good safe areas to work in
- `docs/`
- `app/`
- `scripts/Import-AppReportBundle.ps1`
- `.github/workflows/maester-manual-run.yml`

### Areas to treat carefully
- `deploy/maester/`
- `website/`
- anything tied to the current prototype environment on `example.ict365.uk`
- workflow names or paths that may still be depended on externally

## If continuing the product cleanup

A sensible next engineering pass would be:
1. inspect how `app/` currently reads imported report data
2. compare that against actual workflow output and `app-import` bundle shape
3. close any mismatches
4. document the report bundle contract clearly
5. only then consider retiring parts of the legacy shared-host path

## If continuing the branding cleanup

Safe rule:
- keep technical references to Maester where they genuinely describe the engine
- remove Maester as a product label from docs, UI, headings, summaries, and customer-facing wording
- avoid broad mechanical renames that might break prototype deployment paths

## Final orientation

This repo is not messy by accident, it is mid-migration.

The right posture is:
- preserve working paths
- reduce ambiguity
- document reality
- move the centre of gravity toward `app/`
- keep Maester as the engine, not the brand
