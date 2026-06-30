# SecureIT local project notes

## Naming rule

Use **SecureIT** consistently across the project.

If `SecureIt`, `Secure IT`, or any other variant appears in code, content, labels, headings, config examples, or UI copy, treat that as a typo and correct it to `SecureIT` during future work unless Matt explicitly asks otherwise.

## Container alignment

Keep the Docker-based `app/` surface aligned with the shared runtime helpers and runtime data contract as changes continue.

The current intention is to keep the product surface and container runtime consistent in:
- naming
- portal flow
- onboarding model
- customer/admin UX structure
- security reporting terminology
- report bundle contract
- runtime storage paths

## Email wiring

The diagnostics mail routines are the reusable baseline for future email work.

- Plain text and HTML Graph mail sends are both working.
- Recipient selection is configurable from the diagnostics page.
- Attachment sending has not been tested yet, so treat that as the next validation step before using the helpers elsewhere.
- Tenant pages can now queue the `SecureIT Production` workflow when the GitHub dispatch token is configured.
- Imported report bundles now send an HTML summary email to the tenant's configured report recipient.
