# SecureIT Azure Key Vault Integration Plan

## GitHub repository

- `https://github.com/matt-edu365/secureit`

## Goal

Allow SecureIT to store or retrieve sensitive tenant-related values through Azure Key Vault while keeping the customer-facing product branded as SecureIT and keeping Maester-focused secret handling behind the scenes.

## Current reality

This repo already includes:
- app-side Azure and Key Vault environment variable support in `app/config.php`
- Azure OIDC diagnostic workflow
- Azure Key Vault smoke-test workflow
- manual Maester workflow support for Key Vault retrieval in client-secret mode

So this is not just a future idea anymore. It is partly implemented and still needs consolidation.

## App-side Key Vault settings

The app currently supports these environment variables:
- `SECUREIT_KEY_VAULT_TENANT_ID`
- `SECUREIT_KEY_VAULT_CLIENT_ID`
- `SECUREIT_KEY_VAULT_CLIENT_SECRET`
- `SECUREIT_KEY_VAULT_NAME`
- `SECUREIT_KEY_VAULT_URI`

Use either:
- `SECUREIT_KEY_VAULT_URI`, or
- `SECUREIT_KEY_VAULT_NAME`

## Intended SecureIT onboarding model

Preferred direction:
1. operator enters tenant metadata into SecureIT
2. operator provides secret material only through controlled admin/onboarding flow
3. SecureIT stores sensitive values in Azure Key Vault
4. SecureIT stores only secret references or safe metadata in tenant config
5. workflows and runtime components retrieve secrets only when needed

## Secret naming pattern

Suggested naming remains:
- `secureit-<tenant-key>-pfx`
- `secureit-<tenant-key>-pfx-password`
- `secureit-<tenant-key>-client-secret` where client-secret mode is used

If a different naming convention is chosen later, update workflow and onboarding docs together.

## Identity split recommendation

### SecureIT app identity
Used by the running SecureIT app.

Should typically have:
- Key Vault secret write access for onboarding flows where secret capture is part of the app
- optional secret read access where the app must validate or rehydrate references

### GitHub Actions identity
Used by workflows.

Should typically have:
- OIDC federation
- Key Vault secret read access for workflow-time retrieval
- no broader permissions than necessary

## Current workflow reality

The modern manual workflow already supports Key Vault retrieval for client-secret mode.

Relevant path:
- `.github/workflows/maester-manual-run.yml`

That means future work should focus less on proving the concept and more on standardising the secret ownership model across:
- app onboarding
- tenant config resolution
- workflow execution
- environment deployment

## Open design questions

These still need clearer decisions:
- should tenant metadata in `config/`, `data/`, and `deploy/` all reference the same Key Vault naming model?
- should the app ever retrieve secret values directly, or only write references and let workflows read them?
- should certificate and client-secret modes both be fully supported long-term, or should one become the preferred production standard?
- how much of the onboarding flow should live in the app versus external operator/admin process?

## Recommended next follow-up

1. compare `app/` onboarding expectations with actual workflow secret-resolution behaviour
2. document the single preferred secret-reference shape in tenant config
3. update tenant resolution scripts so secret retrieval paths are consistent
4. remove or reduce duplicated secret-source assumptions between GitHub secrets and Key Vault
5. keep SecureIT branding in customer/admin surfaces while treating Key Vault and Maester as backend concerns
