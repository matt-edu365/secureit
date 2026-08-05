# Tenants Config Notes

`config/tenants.example.json` shows the intended structure for multi-tenant operation.

## Fields

- `id`: short safe tenant key used in paths and workflow selection
- `name`: human-friendly dashboard label entered during onboarding
- `tenantId`: Entra tenant ID
- `clientId`: app registration client ID for that tenant
- `authMode`: currently `client-secret` in the live onboarding flow, with `certificate` still available in the workflow scaffolding
- `clientSecretName`: Azure Key Vault secret name used by client-secret authentication
- `certificateSecretName`: Azure Key Vault secret name holding base64 PFX content when certificate mode is used
- `certificatePasswordSecretName`: Azure Key Vault secret name holding the PFX password when certificate mode is used
- `reportBaseUrl`: public or protected base URL where this tenant's report is published
- `emailTo`: tenant-specific recipient or distribution list
- `tenantDomain`: optional onboarding-derived tenant domain from Microsoft Graph
- `m365TenantName`: optional onboarding-derived Microsoft 365 tenant display name from Microsoft Graph

## Recommended pattern

Use one app registration per tenant unless you have a deliberate reason to centralise. It keeps blast radius and permissions cleaner.

The onboarding page reads the pinned Maester version and Graph application-permission set from `config/maester-runtime.json`. For client-secret onboarding, SecureIT requests a Microsoft Graph application token with the submitted tenant ID, client ID, and secret, then refuses to save the tenant until every required application role is present in that token. Grant tenant-wide admin consent before submitting the form.

## Runtime note

The current production workflow obtains safe tenant metadata through the token-protected `workflow-sync.php` endpoint, builds a tenant matrix, and then retrieves the named authentication material from Azure Key Vault. Secret names may be stored in tenant metadata; secret values must not be stored there. The older checked-in configuration and environment-resolution scripts remain useful for local/manual scaffolding but are not the authoritative production discovery path.
