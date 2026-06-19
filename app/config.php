<?php
return [
    'app_name' => getenv('SECUREIT_APP_NAME') ?: 'SecureIT',
    'base_url' => rtrim(getenv('SECUREIT_BASE_URL') ?: 'https://secureit.ict365.ky', '/'),
    'tenants_file' => getenv('SECUREIT_TENANTS_FILE') ?: __DIR__ . '/../data/tenants.json',
    'reports_root' => getenv('SECUREIT_REPORTS_ROOT') ?: __DIR__ . '/../data/reports',
    'identity_seeds_file' => getenv('SECUREIT_IDENTITY_SEEDS_FILE') ?: (file_exists(__DIR__ . '/../.local/identity-seeds.json') ? __DIR__ . '/../.local/identity-seeds.json' : ''),
    'canonical_controls_file' => getenv('SECUREIT_CANONICAL_CONTROLS_FILE') ?: __DIR__ . '/../data/canonical-controls.json',
    'canonical_controls_example_file' => __DIR__ . '/../config/canonical-controls.example.json',
    'azure_tenant_id' => getenv('SECUREIT_AZURE_TENANT_ID') ?: '',
    'azure_client_id' => getenv('SECUREIT_AZURE_CLIENT_ID') ?: '',
    'azure_client_secret' => getenv('SECUREIT_AZURE_CLIENT_SECRET') ?: '',
    'key_vault_name' => getenv('SECUREIT_KEY_VAULT_NAME') ?: '',
    'key_vault_uri' => getenv('SECUREIT_KEY_VAULT_URI') ?: '',
];
