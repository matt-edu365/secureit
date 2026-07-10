# SecureIT 365Inspect profile

This folder holds the repo-owned integration layer for the 365Inspect inspector set.

Stage 1 adds the new `secureit-365inspect` profile and a repo-owned mirror of the 27 non-duplicate 365Inspect inspectors under `inspectors/`.

The `support/Invoke-SecureIT365InspectInspector.ps1` helper loads those mirrored scripts from the local tree so the Pester layer can exercise them as real checks.

The next step is to expand the canonical control mappings so the portal can score and display the new checks alongside the existing SecureIT set.
