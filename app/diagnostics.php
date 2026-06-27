<?php
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/keyvault.php';
secureit_require_admin_access();

function secureit_diag_yes_no(bool $value): string {
    return $value ? 'yes' : 'no';
}

function secureit_diag_set(string $value): string {
    return trim($value) !== '' ? 'set' : 'not set';
}

function secureit_diag_path_line(string $label, string $path): string {
    $exists = file_exists($path);
    $isDir = is_dir($path);
    $probePath = $exists ? $path : dirname($path);
    $readable = is_readable($probePath);
    $writable = is_writable($probePath);

    return sprintf(
        '%s: %s | exists=%s | type=%s | readable=%s | writable=%s',
        $label,
        $path,
        secureit_diag_yes_no($exists),
        $isDir ? 'dir' : 'file',
        secureit_diag_yes_no($readable),
        secureit_diag_yes_no($writable)
    );
}

function secureit_diag_env_line(string $label, string $value, bool $redact = false): string {
    if ($redact) {
        return sprintf('%s: %s', $label, secureit_diag_set($value));
    }

    return sprintf('%s: %s', $label, trim($value) !== '' ? $value : '[not set]');
}

function secureit_diag_json_line(string $label, mixed $value): string {
    if ($value === null || $value === '' || $value === []) {
        return sprintf('%s: [not set]', $label);
    }

    if (is_bool($value)) {
        return sprintf('%s: %s', $label, secureit_diag_yes_no($value));
    }

    if (is_scalar($value)) {
        return sprintf('%s: %s', $label, (string) $value);
    }

    return sprintf('%s: %s', $label, json_encode($value, JSON_UNESCAPED_SLASHES));
}

function secureit_diag_tenant_registry_status(array $tenant): array {
    $requiredFields = [
        'id' => 'tenant key',
        'tenantId' => 'Microsoft 365 tenant ID',
        'clientId' => 'Microsoft 365 application ID',
        'authMode' => 'authentication mode',
        'reportBaseUrl' => 'report base URL',
    ];

    $present = [];
    $missing = [];
    foreach ($requiredFields as $field => $label) {
        $value = trim((string) ($tenant[$field] ?? ''));
        if ($value === '') {
            $missing[] = $label;
        } else {
            $present[] = $label;
        }
    }

    $authMode = strtolower(trim((string) ($tenant['authMode'] ?? '')));
    $secretFields = [];
    if ($authMode === 'client-secret') {
        $secretFields = [
            'clientSecretName' => 'client secret name',
        ];
    } elseif ($authMode === 'certificate') {
        $secretFields = [
            'certificateSecretName' => 'certificate secret name',
            'certificatePasswordSecretName' => 'certificate password secret name',
        ];
    } elseif ($authMode === '') {
        $missing[] = 'authentication mode';
    } else {
        $missing[] = 'authentication mode (unsupported: ' . $authMode . ')';
    }

    foreach ($secretFields as $field => $label) {
        $value = trim((string) ($tenant[$field] ?? ''));
        if ($value === '') {
            $missing[] = $label;
        } else {
            $present[] = $label;
        }
    }

    $tenantDomain = trim((string) ($tenant['tenantDomain'] ?? ''));
    $resolvedTenantDomain = '';
    $resolvedTenantDomainMessage = '';
    if ($tenantDomain === '') {
        $tenantId = trim((string) ($tenant['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $tenantDomainLookup = secureit_entra_resolve_tenant_domain($tenantId);
            if (!empty($tenantDomainLookup['ok'])) {
                $resolvedTenantDomain = trim((string) ($tenantDomainLookup['domain'] ?? ''));
                $resolvedTenantDomainMessage = trim((string) ($tenantDomainLookup['message'] ?? ''));
            } else {
                $resolvedTenantDomainMessage = trim((string) ($tenantDomainLookup['message'] ?? ''));
            }
        }
    } else {
        $resolvedTenantDomain = $tenantDomain;
        $resolvedTenantDomainMessage = 'Loaded from the tenant registry.';
    }

    $effectiveTenantDomain = $tenantDomain !== '' ? $tenantDomain : $resolvedTenantDomain;
    $tenantDomainSource = 'not available';
    $tenantDomainLookupStatus = 'not attempted';
    if ($tenantDomain !== '') {
        $tenantDomainSource = 'stored in tenant registry';
        $tenantDomainLookupStatus = 'not needed';
    } elseif ($resolvedTenantDomain !== '') {
        $tenantDomainSource = 'resolved from Microsoft Graph';
        $tenantDomainLookupStatus = 'succeeded';
    } elseif ($tenantId !== '') {
        $tenantDomainSource = 'lookup attempted';
        $tenantDomainLookupStatus = 'failed';
    }

    $emailTo = trim((string) ($tenant['emailTo'] ?? ''));
    $recommendedMissing = [];
    if ($emailTo === '') {
        $recommendedMissing[] = 'report recipient email';
    }
    if ($effectiveTenantDomain === '') {
        $recommendedMissing[] = 'tenant domain (optional, but useful for Exchange-specific runs)';
    }

    $tenantName = trim((string) ($tenant['name'] ?? ''));
    if ($tenantName === '') {
        $recommendedMissing[] = 'dashboard label';
    }

    return [
        'tenantKey' => trim((string) ($tenant['id'] ?? '')),
        'tenantName' => $tenantName,
        'authMode' => $authMode,
        'ready' => $missing === [],
        'missing' => array_values(array_unique($missing)),
        'present' => array_values(array_unique($present)),
        'recommendedMissing' => array_values(array_unique($recommendedMissing)),
        'clientSecretName' => trim((string) ($tenant['clientSecretName'] ?? '')),
        'certificateSecretName' => trim((string) ($tenant['certificateSecretName'] ?? '')),
        'certificatePasswordSecretName' => trim((string) ($tenant['certificatePasswordSecretName'] ?? '')),
        'tenantDomain' => $tenantDomain,
        'resolvedTenantDomain' => $resolvedTenantDomain,
        'effectiveTenantDomain' => $effectiveTenantDomain,
        'tenantDomainSource' => $tenantDomainSource,
        'tenantDomainLookupStatus' => $tenantDomainLookupStatus,
        'resolvedTenantDomainMessage' => $resolvedTenantDomainMessage,
        'reportBaseUrl' => trim((string) ($tenant['reportBaseUrl'] ?? '')),
        'emailTo' => $emailTo,
    ];
}

$config = secureit_config();
$dataRoot = dirname((string) ($config['tenants_file'] ?? '/var/www/data/tenants.json'));
$tenantsPath = (string) ($config['tenants_file'] ?? ($dataRoot . '/tenants.json'));
$adminConfigPath = $dataRoot . '/admin-config.json';
$canonicalControlsPath = (string) ($config['canonical_controls_file'] ?? ($dataRoot . '/canonical-controls.json'));

$phpExtensions = [
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl'),
];

$seedMessages = [];
$seedErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_runtime_files'])) {
    $createdFiles = [];

    if (!file_exists($tenantsPath)) {
        $dir = dirname($tenantsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($tenantsPath, json_encode(['tenants' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $createdFiles[] = 'tenants.json';
    }

    if (!file_exists($adminConfigPath)) {
        $adminPayload = [
            'azure' => [
                'keyVaultName' => trim((string) ($config['key_vault_name'] ?? '')),
                'keyVaultUri' => trim((string) ($config['key_vault_uri'] ?? '')),
                'certificateStorageMode' => 'key-vault',
            ],
            'notifications' => [
                'defaultFromName' => 'ICT365 SecureIT Reporting',
                'defaultReplyTo' => '',
            ],
            'reports' => [
                'baseSiteUrl' => trim((string) ($config['base_url'] ?? '')),
            ],
        ];
        $dir = dirname($adminConfigPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($adminConfigPath, json_encode($adminPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $createdFiles[] = 'admin-config.json';
    }

    if (!file_exists($canonicalControlsPath)) {
        $canonicalControlsSeed = '';
        foreach ([
            '/usr/local/share/secureit/canonical-controls.json',
            (string) ($config['canonical_controls_example_file'] ?? ''),
        ] as $sourcePath) {
            if (!$sourcePath || !file_exists($sourcePath)) {
                continue;
            }
            $canonicalControlsSeed = (string) file_get_contents($sourcePath);
            break;
        }

        if (trim($canonicalControlsSeed) !== '') {
            $dir = dirname($canonicalControlsPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($canonicalControlsPath, rtrim($canonicalControlsSeed) . PHP_EOL);
            $createdFiles[] = 'canonical-controls.json';
        } else {
            $seedErrors[] = 'canonical-controls.json could not be seeded because no source template was available.';
        }
    }

    if ($createdFiles !== []) {
        $seedMessages[] = 'Seeded runtime files successfully: ' . implode(', ', $createdFiles) . '.';
    } elseif ($seedErrors === []) {
        $seedMessages[] = 'No files needed seeding. All runtime files already exist.';
    }
}

$tenantsConfig = secureit_load_tenants();
$tenants = $tenantsConfig['tenants'] ?? [];
$adminConfig = file_exists($adminConfigPath) ? json_decode((string) file_get_contents($adminConfigPath), true) : [];
$adminConfig = is_array($adminConfig) ? $adminConfig : [];
$registryCheckMessages = [];
$registryCheckErrors = [];
$registryCheckTenantKey = '';
$registryCheckTargetTenant = null;
$registryCheckStatus = null;

if ($tenants !== []) {
    $registryCheckTenantKey = (string) ($tenants[0]['id'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_registry_tenant'])) {
    $registryCheckTenantKey = trim(strtolower((string) ($_POST['registry_tenant_key'] ?? '')));
    $registryCheckTargetTenant = $registryCheckTenantKey !== '' ? secureit_find_tenant($registryCheckTenantKey) : null;

    if (!$registryCheckTargetTenant) {
        $registryCheckErrors[] = 'Select a valid tenant before checking registry readiness.';
    } else {
        $registryCheckStatus = secureit_diag_tenant_registry_status($registryCheckTargetTenant);
        $registryCheckMessages[] = $registryCheckStatus['ready']
            ? 'This tenant already has the data needed for a weekly workflow.'
            : 'This tenant still needs additional registry data before a weekly workflow can be automated.';
    }
}

$registryStatuses = [];
foreach ($tenants as $tenantItem) {
    if (!is_array($tenantItem)) {
        continue;
    }
    $registryStatuses[] = secureit_diag_tenant_registry_status($tenantItem);
}

$registryReadyCount = count(array_filter($registryStatuses, static fn(array $status): bool => !empty($status['ready'])));
$registryMissingCount = count($registryStatuses) - $registryReadyCount;

$secretWriteMessages = [];
$secretWriteErrors = [];
$secretWriteTenantKey = '';
$secretWriteSecretName = '';
$secretWriteValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['write_tenant_secret'])) {
    $secretWriteTenantKey = trim(strtolower((string) ($_POST['secret_tenant_key'] ?? '')));
    $secretWriteSecretName = trim((string) ($_POST['secret_client_secret_name'] ?? ''));
    $secretWriteValue = (string) ($_POST['secret_value'] ?? '');
    $targetTenant = $secretWriteTenantKey !== '' ? secureit_find_tenant($secretWriteTenantKey) : null;

    if (!$targetTenant) {
        $secretWriteErrors[] = 'Select a valid tenant before writing a secret to Key Vault.';
    }

    if ($secretWriteSecretName === '' && is_array($targetTenant)) {
        $secretWriteSecretName = trim((string) ($targetTenant['clientSecretName'] ?? ''));
    }

    if ($secretWriteSecretName === '' && $secretWriteTenantKey !== '') {
        $secretWriteSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $secretWriteTenantKey));
    }

    if (trim($secretWriteValue) === '') {
        $secretWriteErrors[] = 'A client secret value is required to write to Azure Key Vault.';
    }

    if ($secretWriteErrors === []) {
        if (!secureit_keyvault_enabled()) {
            $secretWriteErrors[] = 'Azure Key Vault settings are not configured for this environment.';
        } else {
            try {
                secureit_keyvault_set_secret($secretWriteSecretName, $secretWriteValue);
                $tenantConfig = secureit_load_tenants();
                $tenantUpdated = false;
                foreach (($tenantConfig['tenants'] ?? []) as &$tenantItem) {
                    if (($tenantItem['id'] ?? '') !== $secretWriteTenantKey) {
                        continue;
                    }

                    if (($tenantItem['clientSecretName'] ?? '') !== $secretWriteSecretName) {
                        $tenantItem['clientSecretName'] = $secretWriteSecretName;
                        $tenantUpdated = true;
                    }
                    break;
                }
                unset($tenantItem);
                if ($tenantUpdated) {
                    secureit_save_tenants($tenantConfig);
                }
                $tenantLabel = (string) ($targetTenant['name'] ?? $secretWriteTenantKey);
                $secretWriteMessages[] = 'Stored the client secret for ' . $tenantLabel . ' in Azure Key Vault as ' . $secretWriteSecretName . '.';
                if ($tenantUpdated) {
                    $secretWriteMessages[] = 'Tenant metadata was updated to match the secret name.';
                }
                $secretWriteMessages[] = 'This updated the secret value only. The tenant record was not recreated.';
            } catch (Throwable $exception) {
                $secretWriteErrors[] = 'The secret could not be written to Azure Key Vault: ' . $exception->getMessage();
            }
        }
    }
}

if ($secretWriteTenantKey === '' && $tenants !== []) {
    $secretWriteTenantKey = (string) ($tenants[0]['id'] ?? '');
}

$secretWriteTargetTenant = $secretWriteTenantKey !== '' ? secureit_find_tenant($secretWriteTenantKey) : null;
if ($secretWriteSecretName === '' && is_array($secretWriteTargetTenant)) {
    $secretWriteSecretName = trim((string) ($secretWriteTargetTenant['clientSecretName'] ?? ''));
}
if ($secretWriteSecretName === '' && $secretWriteTenantKey !== '') {
    $secretWriteSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $secretWriteTenantKey));
}

$keyVaultEnabled = secureit_keyvault_enabled();
$keyVaultBaseUri = '[not configured]';
try {
    $keyVaultBaseUri = secureit_keyvault_base_uri();
} catch (Throwable $exception) {
    $keyVaultBaseUri = '[not configured]';
}

$rawLines = [];
$rawLines[] = 'SecureIT live diagnostics';
$rawLines[] = 'Generated: ' . date(DATE_ATOM);
$rawLines[] = '';
$rawLines[] = '[Runtime]';
$rawLines[] = secureit_diag_json_line('Request host', (string) ($_SERVER['HTTP_HOST'] ?? ''));
$rawLines[] = secureit_diag_json_line('Request URI', (string) ($_SERVER['REQUEST_URI'] ?? ''));
$rawLines[] = secureit_diag_json_line('PHP version', PHP_VERSION);
$rawLines[] = secureit_diag_json_line('PHP SAPI', PHP_SAPI);
$rawLines[] = 'Extensions: curl=' . secureit_diag_yes_no($phpExtensions['curl']) . ', json=' . secureit_diag_yes_no($phpExtensions['json']) . ', openssl=' . secureit_diag_yes_no($phpExtensions['openssl']);
$rawLines[] = secureit_diag_json_line('Base URL', (string) ($config['base_url'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Mounted data]';
$rawLines[] = secureit_diag_path_line('Data root', $dataRoot);
$rawLines[] = secureit_diag_path_line('Tenants file', (string) $config['tenants_file']);
$rawLines[] = 'Tenant count: ' . count($tenants);
$rawLines[] = secureit_diag_path_line('Reports root', (string) $config['reports_root']);
$rawLines[] = secureit_diag_path_line('Admin config', $adminConfigPath);
$rawLines[] = secureit_diag_path_line('Canonical controls', (string) $config['canonical_controls_file']);
$rawLines[] = secureit_diag_path_line('Local identity seeds', (string) ($config['identity_seeds_file'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Application registry]';
$rawLines[] = 'Registry source: mounted tenants.json';
$rawLines[] = 'Registry tenant count: ' . count($tenants);
$rawLines[] = 'Weekly workflow ready tenants: ' . $registryReadyCount;
$rawLines[] = 'Weekly workflow not ready tenants: ' . $registryMissingCount;
foreach ($registryStatuses as $status) {
    $rawLines[] = 'Tenant ' . ($status['tenantKey'] !== '' ? $status['tenantKey'] : '[unnamed]') . ': ready=' . secureit_diag_yes_no((bool) $status['ready']);
    $rawLines[] = '  Dashboard label: ' . ($status['tenantName'] !== '' ? $status['tenantName'] : '[not set]');
    $rawLines[] = '  Auth mode: ' . ($status['authMode'] !== '' ? $status['authMode'] : '[not set]');
    $rawLines[] = '  Stored tenant domain: ' . ($status['tenantDomain'] !== '' ? $status['tenantDomain'] : '[not set]');
    $rawLines[] = '  Resolved tenant domain: ' . ($status['resolvedTenantDomain'] !== '' ? $status['resolvedTenantDomain'] : '[not set]');
    $rawLines[] = '  Effective tenant domain: ' . ($status['effectiveTenantDomain'] !== '' ? $status['effectiveTenantDomain'] : '[not set]');
    $rawLines[] = '  Tenant domain source: ' . ($status['tenantDomainSource'] !== '' ? $status['tenantDomainSource'] : '[not available]');
    $rawLines[] = '  Tenant domain lookup status: ' . ($status['tenantDomainLookupStatus'] !== '' ? $status['tenantDomainLookupStatus'] : '[not available]');
    $rawLines[] = '  Domain lookup note: ' . ($status['resolvedTenantDomainMessage'] !== '' ? $status['resolvedTenantDomainMessage'] : '[not available]');
    $rawLines[] = '  Report base URL: ' . ($status['reportBaseUrl'] !== '' ? $status['reportBaseUrl'] : '[not set]');
    $rawLines[] = '  Secret reference: ' . (
        $status['authMode'] === 'client-secret'
            ? ($status['clientSecretName'] !== '' ? $status['clientSecretName'] : '[not set]')
            : ($status['authMode'] === 'certificate'
                ? (
                    $status['certificateSecretName'] !== '' ? $status['certificateSecretName'] : '[not set]'
                )
                : '[not set]')
    );
    $rawLines[] = '  Missing required fields: ' . (empty($status['missing']) ? '[none]' : implode(', ', $status['missing']));
    $rawLines[] = '  Recommended follow-up: ' . (empty($status['recommendedMissing']) ? '[none]' : implode(', ', $status['recommendedMissing']));
}
$rawLines[] = '';
$rawLines[] = '[Shared component / Key Vault]';
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_TENANT_ID', (string) ($config['key_vault_tenant_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_CLIENT_ID', (string) ($config['key_vault_client_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_CLIENT_SECRET', (string) ($config['key_vault_client_secret'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_NAME', (string) ($config['key_vault_name'] ?? ''));
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_URI', (string) ($config['key_vault_uri'] ?? ''));
$rawLines[] = 'Key Vault helper enabled: ' . secureit_diag_yes_no($keyVaultEnabled);
$rawLines[] = 'Key Vault base URI: ' . $keyVaultBaseUri;
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.keyVaultName', (string) ($adminConfig['azure']['keyVaultName'] ?? ''));
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.keyVaultUri', (string) ($adminConfig['azure']['keyVaultUri'] ?? ''));
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.certificateStorageMode', (string) ($adminConfig['azure']['certificateStorageMode'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Entra sign-in]';
$rawLines[] = secureit_diag_env_line('SECUREIT_ENTRA_CLIENT_ID', (string) ($config['entra_client_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_ENTRA_CLIENT_SECRET', (string) ($config['entra_client_secret'] ?? ''), true);
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_AUTHORITY', (string) ($config['entra_authority'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_REDIRECT_URI', (string) ($config['entra_redirect_uri'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI', (string) ($config['entra_post_logout_redirect_uri'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS', (string) ($config['entra_admin_email_domains'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_ALLOWED_TENANT_IDS', trim((string) ($config['entra_allowed_tenant_ids'] ?? '')) !== '' ? 'set' : 'not set');

$rawOutput = implode("\n", $rawLines) . "\n";
$rawMode = in_array(strtolower((string) ($_GET['format'] ?? '')), ['raw', 'text', 'plain'], true) || isset($_GET['raw']);

if ($rawMode) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $rawOutput;
    exit;
}

ob_start();
?>
<section class="section">
  <div class="section-heading">
    <h2 class="section-title" style="text-align:center; font-size:clamp(2rem, 4vw, 3.15rem);">Live diagnostics</h2>
    <p class="section-intro" style="max-width:960px; margin-left:auto; margin-right:auto; text-align:center;">Admin-only readiness checks for the live host before onboarding a customer. No secrets are printed. Use the raw text view if you want to copy and paste the output.</p>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Copy-friendly output</h3>
        <div class="muted">Open the raw text version to paste the results into chat or notes.</div>
      </div>
      <a class="textlink" href="diagnostics.php?format=raw">Open raw text</a>
    </div>
    <pre style="white-space:pre-wrap; margin:0;"><?php echo htmlspecialchars($rawOutput); ?></pre>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Seed runtime files</h3>
        <div class="muted">Create the mounted JSON files only if they are missing, then re-check after a redeploy.</div>
      </div>
    </div>

    <?php foreach ($seedErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($seedMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <form method="post">
      <button type="submit" name="seed_runtime_files" value="1">Create missing files</button>
      <p class="field-note" style="margin-top:10px;">This creates `tenants.json`, `admin-config.json`, and `canonical-controls.json` if they do not already exist. Re-run the diagnostics view afterwards to confirm the result.</p>
    </form>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Application registry readiness</h3>
        <div class="muted">Checks the live `tenants.json` registry for the fields needed to automate a weekly tenant workflow. If tenant domain is missing, SecureIT will try to resolve it from Microsoft Graph using the tenant ID.</div>
      </div>
    </div>

    <div class="stats-row" style="margin-bottom:14px;">
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) count($registryStatuses)); ?></strong><span>Total</span></div>
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $registryReadyCount); ?></strong><span>Ready</span></div>
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $registryMissingCount); ?></strong><span>Not ready</span></div>
    </div>

    <?php foreach ($registryCheckErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($registryCheckMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php if ($tenants === []): ?>
      <div class="empty-state">
        <strong>No tenants are available yet.</strong>
        <p class="muted" style="margin:8px 0 0;">Onboard at least one tenant before checking registry readiness.</p>
      </div>
    <?php else: ?>
      <form method="post" style="margin-bottom:16px;">
        <label for="registry_tenant_key">Tenant to inspect</label>
        <select id="registry_tenant_key" name="registry_tenant_key">
          <?php foreach ($tenants as $tenantItem): ?>
            <?php $tenantId = (string) ($tenantItem['id'] ?? ''); ?>
            <option value="<?php echo htmlspecialchars($tenantId); ?>"<?php echo $registryCheckTenantKey === $tenantId ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($tenantItem['name'] ?? $tenantId)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="field-note">Use this to confirm whether the live registry already contains the fields needed for a weekly GitHub workflow.</p>

        <button type="submit" name="inspect_registry_tenant" value="1">Check registry readiness</button>
      </form>

      <?php if ($registryCheckStatus): ?>
        <div class="empty-state" style="margin-bottom:0;">
          <strong><?php echo htmlspecialchars(($registryCheckStatus['tenantName'] !== '' ? $registryCheckStatus['tenantName'] : $registryCheckStatus['tenantKey']) . ' readiness: ' . ($registryCheckStatus['ready'] ? 'ready' : 'not ready')); ?></strong>
          <p class="muted" style="margin:8px 0 0;">
            <?php echo htmlspecialchars($registryCheckStatus['ready'] ? 'The selected tenant already has the fields needed for a scheduled run.' : 'The selected tenant still needs registry updates before the workflow can be scheduled cleanly.'); ?>
          </p>
          <div class="kv" style="margin-top:14px;">
            <div class="kv-row"><div class="kv-label">Tenant key</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantKey'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Dashboard label</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantName'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Stored tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Resolved tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedTenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Effective tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['effectiveTenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Tenant domain source</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomainSource'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Lookup status</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomainLookupStatus'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Domain lookup note</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedTenantDomainMessage'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Auth mode</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['authMode'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Secret reference</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['authMode'] === 'client-secret' ? ($registryCheckStatus['clientSecretName'] ?: '[not set]') : ($registryCheckStatus['authMode'] === 'certificate' ? ($registryCheckStatus['certificateSecretName'] ?: '[not set]') : '[not set]')); ?></div></div>
            <div class="kv-row"><div class="kv-label">Missing required fields</div><div class="kv-value"><?php echo htmlspecialchars(empty($registryCheckStatus['missing']) ? '[none]' : implode(', ', $registryCheckStatus['missing'])); ?></div></div>
            <div class="kv-row"><div class="kv-label">Recommended follow-up</div><div class="kv-value"><?php echo htmlspecialchars(empty($registryCheckStatus['recommendedMissing']) ? '[none]' : implode(', ', $registryCheckStatus['recommendedMissing'])); ?></div></div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Write tenant secret</h3>
        <div class="muted">Temporary admin tool for updating an existing tenant's Key Vault secret without deleting or recreating the tenant.</div>
      </div>
    </div>

    <?php foreach ($secretWriteErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($secretWriteMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php if ($tenants === []): ?>
      <div class="empty-state" style="margin-bottom:12px;">
        <strong>No tenants are available yet.</strong>
        <p class="muted" style="margin:8px 0 0;">Onboard at least one tenant before using this temporary Key Vault write tool.</p>
      </div>
    <?php else: ?>
      <form method="post">
        <label for="secret_tenant_key">Tenant</label>
        <select id="secret_tenant_key" name="secret_tenant_key">
          <?php foreach ($tenants as $tenantItem): ?>
            <?php $tenantId = (string) ($tenantItem['id'] ?? ''); ?>
            <option value="<?php echo htmlspecialchars($tenantId); ?>"<?php echo $secretWriteTenantKey === $tenantId ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($tenantItem['name'] ?? $tenantId)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="field-note">Select the tenant whose Key Vault secret you want to update.</p>

        <label for="secret_client_secret_name">Key Vault secret name</label>
        <input id="secret_client_secret_name" name="secret_client_secret_name" value="<?php echo htmlspecialchars($secretWriteSecretName); ?>" placeholder="AZURE-CLIENT-SECRET-NCVO">
        <p class="field-note">This should match the tenant's stored client secret name.</p>

        <label for="secret_value">Client secret value</label>
        <input id="secret_value" name="secret_value" type="password" autocomplete="new-password" placeholder="Paste the secret to store in Key Vault">
        <p class="field-note">The value is written to Azure Key Vault and is not saved in the app.</p>

        <button type="submit" name="write_tenant_secret" value="1">Write secret to Key Vault</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card panel">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">What to check first</h3>
        <div class="muted">The most important signals for shared component and onboarding readiness.</div>
      </div>
    </div>
    <ul style="margin:0 0 0 18px; padding:0; line-height:1.7; color:var(--eden);">
      <li>`/var/www/data` is writable and survives container recreates.</li>
      <li>`tenants.json`, `reports/`, and `admin-config.json` live under the mounted data volume.</li>
      <li>Key Vault environment values are set in the live stack, not saved inside the image.</li>
      <li>Entra client ID and secret are present before customer onboarding.</li>
      <li>Local identity seed data is not mounted on live.</li>
    </ul>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('Live Diagnostics - SecureIT', $content, [
    'pageTitle' => 'Live diagnostics',
    'pageIntro' => 'Check the mounted storage, Key Vault, and Entra readiness for the live host before onboarding a customer.',
    'eyebrow' => '',
    'heroBackground' => secureit_default_hero_background(),
    'heroTextAlign' => 'center',
    'navLinks' => [],
    'headerMenu' => [
        ['href' => 'dashboard.php', 'label' => 'Tenant overview dashboard'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'diagnostics.php', 'label' => 'Live diagnostics'],
    ],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'login.php', 'label' => 'Customer login'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Admin dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'diagnostics.php', 'label' => 'Live diagnostics'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
