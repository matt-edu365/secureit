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

## Downloadable PDF report

- The supplied `SecureIT Example Report Template.docx` is the design authority for the customer PDF.
- PDF output is built from print-specific HTML and rendered with Dompdf inside the app container.
- Preserve the cover, executive summary, eight functional-area overview, remediation-first detail order, coverage-gap treatment, and compact passing-control index.
- The renderer must keep remote resource loading and embedded PHP disabled.

## Tenant overview and history graph

The current tenant overview graph behavior is deliberate and should be preserved unless Matt asks for a different interaction:

- initial render shows only the `Overall` line
- `Overall` is controlled by its own checkbox and is selected by default
- functional-area controls add/remove lines without a full page refresh
- each line/control uses an individual color
- functional areas with unavailable current scores are disabled and greyed out
- X-axis labels use report dates in `dd/MM` format
- the graph is rendered server-side as SVG, and JavaScript only toggles the relevant SVG group with `display`

Performance note:

- `app/tenant.php` hydrates history rows with resolved area data once and reuses that data for graph series and run history.
- Avoid reintroducing per-functional-area calls that resolve every historical report repeatedly; that was the source of a noticeable tenant overview slowdown.

## Current local-only files

The following files are present as local reference artifacts and should stay out of the repo unless Matt explicitly asks otherwise:

- `Overall-tenant-line-graph.jpg`
- `Report_Screenshot.jpg`
