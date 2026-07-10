# SecureIT 365Inspect profile

This folder holds the repo-owned integration layer for the 365Inspect inspector set.

Stage 1 adds the new `365Inspect-18` profile and a repo-owned mirror of the 26 non-duplicate 365Inspect inspectors under `inspectors/`.

Of those checks, 18 are now mapped into the SecureIT scoring model and included in the production run. The remaining checks stay in the raw bundle for now so they can be reviewed without distorting the portal posture.

The `support/Invoke-SecureIT365InspectInspector.ps1` helper loads those mirrored scripts from the local tree so the Pester layer can exercise them as real checks.

The next step is to expand the canonical control mappings so the portal can score and display the new checks alongside the existing SecureIT set.
