# Tenants Config Notes

`config/tenants.example.json` shows the intended structure for multi-tenant operation.

## Fields

- `id`: short safe tenant key used in paths and workflow selection
- `name`: human-friendly tenant name
- `tenantId`: Entra tenant ID
- `clientId`: app registration client ID for that tenant
- `authMode`: currently `certificate`
- `certificateSecretName`: name of the GitHub secret holding base64 PFX content
- `certificatePasswordSecretName`: optional GitHub secret name for the PFX password
- `reportBaseUrl`: public or protected base URL where this tenant's report is published
- `emailTo`: tenant-specific recipient or distribution list

## Recommended pattern

Use one app registration per tenant unless you have a deliberate reason to centralise. It keeps blast radius and permissions cleaner.

## Runtime note

GitHub Actions cannot dynamically dereference secret names from JSON at runtime with normal expressions. The practical first version is either:
- one workflow per tenant using environments, or
- a matrix built from repo variables plus environment-level secrets, or
- a prep script that writes a resolved per-tenant config from checked-in metadata plus env-provided secrets

This scaffold uses checked-in tenant metadata plus per-run environment variables to keep the logic understandable first.
