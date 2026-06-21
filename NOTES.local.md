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
