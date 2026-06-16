# SecureIT Azure Key Vault Integration Plan

## Goal

Allow the SecureIT app onboarding flow to store tenant certificate secrets in Azure Key Vault, while tenant metadata stores only secret references.

## App-side Azure settings

The app now supports these environment variables:

- `SECUREIT_AZURE_TENANT_ID`
- `SECUREIT_AZURE_CLIENT_ID`
- `SECUREIT_AZURE_CLIENT_SECRET`
- `SECUREIT_KEY_VAULT_NAME`
- `SECUREIT_KEY_VAULT_URI`

Use either `SECUREIT_KEY_VAULT_URI` or `SECUREIT_KEY_VAULT_NAME`.

## Onboarding flow

When a tenant is onboarded:

1. Operator enters tenant metadata
2. Operator pastes base64 PFX content
3. Operator optionally enters certificate password
4. App stores the secrets in Azure Key Vault
5. App stores only secret references in tenant metadata
6. App creates report folder structure

## Secret naming pattern

- `secureit-<tenant-key>-pfx`
- `secureit-<tenant-key>-pfx-password`

## Recommended Azure identity split

### SecureIT app identity
- Used by the running app
- Needs Key Vault secret write access (`set`, and optionally `get`)

### GitHub Actions identity
- Used by workflows
- Needs Key Vault secret read access (`get`)
- Should use OIDC federation

## Next follow-up

Update the Maester workflow and tenant resolution scripts so runner-side secret retrieval comes from Azure Key Vault instead of GitHub secret values.
