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

$config = secureit_config();
$tenantsConfig = secureit_load_tenants();
$tenants = $tenantsConfig['tenants'] ?? [];
$dataRoot = dirname((string) ($config['tenants_file'] ?? '/var/www/data/tenants.json'));
$adminConfigPath = $dataRoot . '/admin-config.json';
$adminConfig = file_exists($adminConfigPath) ? json_decode((string) file_get_contents($adminConfigPath), true) : [];
$adminConfig = is_array($adminConfig) ? $adminConfig : [];

$phpExtensions = [
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl'),
];

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
$rawLines[] = '[Shared component / Key Vault]';
$rawLines[] = secureit_diag_env_line('SECUREIT_AZURE_TENANT_ID', (string) ($config['azure_tenant_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_AZURE_CLIENT_ID', (string) ($config['azure_client_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_AZURE_CLIENT_SECRET', (string) ($config['azure_client_secret'] ?? ''), true);
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
