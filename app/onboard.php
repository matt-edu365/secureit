<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/keyvault.php';

$baseSiteUrl = secureit_config()['base_url'];
$messages = [];
$errors = [];
$config = secureit_load_tenants();
$config['tenants'] = $config['tenants'] ?? [];
$keyVaultEnabled = secureit_keyvault_enabled();
$appConfig = secureit_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantKey = trim(strtolower($_POST['tenant_key'] ?? ''));
    $tenantName = trim($_POST['tenant_name'] ?? '');
    $tenantId = trim($_POST['tenant_id'] ?? '');
    $clientId = trim($_POST['client_id'] ?? '');
    $emailTo = trim($_POST['email_to'] ?? '');
    $certificateBase64 = trim($_POST['certificate_base64'] ?? '');
    $certificatePassword = trim($_POST['certificate_password'] ?? '');
    $reportBaseUrl = secureit_build_report_base_url($tenantKey);
    $certificateSecretName = secureit_secret_name($tenantKey, 'pfx');
    $certificatePasswordSecretName = secureit_secret_name($tenantKey, 'pfx-password');

    if (!$tenantKey || !secureit_valid_tenant_key($tenantKey)) {
        $errors[] = 'Tenant key must contain only lowercase letters, numbers, and hyphens.';
    }
    if (!$tenantName) {
        $errors[] = 'Tenant name is required.';
    }
    if (!$tenantId || !secureit_guid_like($tenantId)) {
        $errors[] = 'Tenant ID is required and should look like a GUID.';
    }
    if (!$clientId || !secureit_guid_like($clientId)) {
        $errors[] = 'Client ID is required and should look like a GUID.';
    }
    if (!$certificateBase64) {
        $errors[] = 'Certificate Base64 content is required.';
    }
    if (secureit_tenant_exists($config['tenants'], $tenantKey)) {
        $errors[] = 'That tenant key already exists.';
    }
    if (!$keyVaultEnabled) {
        $errors[] = 'Azure Key Vault integration is not configured for this app environment yet.';
    }

    if (!$errors) {
        try {
            secureit_keyvault_set_secret($certificateSecretName, $certificateBase64);
            if ($certificatePassword !== '') {
                secureit_keyvault_set_secret($certificatePasswordSecretName, $certificatePassword);
            }

            $tenant = [
                'id' => $tenantKey,
                'name' => $tenantName,
                'tenantId' => $tenantId,
                'clientId' => $clientId,
                'authMode' => 'certificate',
                'keyVaultName' => $appConfig['key_vault_name'],
                'keyVaultUri' => secureit_keyvault_base_uri(),
                'certificateSecretName' => $certificateSecretName,
                'certificatePasswordSecretName' => $certificatePasswordSecretName,
                'reportBaseUrl' => $reportBaseUrl,
                'emailTo' => $emailTo,
            ];

            $config['tenants'][] = $tenant;
            secureit_save_tenants($config);

            $tenantDir = secureit_reports_root() . '/' . $tenantKey;
            @mkdir($tenantDir . '/latest', 0775, true);
            @mkdir($tenantDir . '/history', 0775, true);

            $messages[] = 'Tenant saved and certificate secrets stored in Azure Key Vault successfully.';
            $messages[] = 'Report Base URL was generated automatically from the tenant key.';
            $messages[] = 'Saved tenant JSON block:';
            $messages[] = json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $config = secureit_load_tenants();
        } catch (Throwable $e) {
            $errors[] = 'Key Vault write failed: ' . $e->getMessage();
        }
    }
}
$app = secureit_config();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Onboard Tenant - <?php echo htmlspecialchars($app['app_name']); ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #1f2937; background: #f8fafc; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; max-width: 980px; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input, textarea { width: 100%; padding: 0.7rem; margin-top: 0.35rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; }
    textarea { min-height: 140px; }
    input[readonly] { background: #f3f4f6; color: #4b5563; }
    button { margin-top: 1.25rem; padding: 0.8rem 1rem; background: #0b5fff; color: white; border: 0; border-radius: 8px; cursor: pointer; }
    pre { background: #111827; color: #f9fafb; padding: 1rem; border-radius: 10px; overflow: auto; }
    .muted { color: #6b7280; }
    .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    .success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    .info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    a { color: #0b5fff; text-decoration: none; }
  </style>
  <script>
    function updateReportBaseUrl() {
      const tenantKey = document.getElementById('tenant_key').value.trim().toLowerCase();
      const baseUrl = <?php echo json_encode($baseSiteUrl, JSON_UNESCAPED_SLASHES); ?>;
      document.getElementById('report_base_url').value = tenantKey ? `${baseUrl}/${encodeURIComponent(tenantKey)}` : '';
      document.getElementById('certificate_secret_name').value = tenantKey ? `secureit-${tenantKey}-pfx` : '';
      document.getElementById('certificate_password_secret_name').value = tenantKey ? `secureit-${tenantKey}-pfx-password` : '';
    }
    window.addEventListener('DOMContentLoaded', updateReportBaseUrl);
  </script>
</head>
<body>
  <p><a href="index.php">← Back to dashboard</a></p>
  <div class="card">
    <h1>Onboard tenant</h1>
    <p class="muted">Prototype onboarding flow that stores certificate secrets in Azure Key Vault and tenant metadata in the app data store.</p>

    <div class="info">
      <strong>Key Vault status:</strong>
      <?php if ($keyVaultEnabled): ?>
        Configured for <?php echo htmlspecialchars($appConfig['key_vault_uri'] ?: ('https://' . $appConfig['key_vault_name'] . '.vault.azure.net')); ?>
      <?php else: ?>
        Not configured yet in this app environment.
      <?php endif; ?>
    </div>

    <?php foreach ($errors as $error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($messages): ?>
      <div class="success"><?php echo htmlspecialchars($messages[0]); ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="tenant_key">Tenant key</label>
      <input id="tenant_key" name="tenant_key" placeholder="contoso-prod" required oninput="updateReportBaseUrl()" value="<?php echo htmlspecialchars($_POST['tenant_key'] ?? ''); ?>">

      <label for="tenant_name">Tenant name</label>
      <input id="tenant_name" name="tenant_name" placeholder="Contoso Production" required value="<?php echo htmlspecialchars($_POST['tenant_name'] ?? ''); ?>">

      <label for="tenant_id">Tenant ID</label>
      <input id="tenant_id" name="tenant_id" placeholder="00000000-0000-0000-0000-000000000000" required value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? ''); ?>">

      <label for="client_id">Client ID</label>
      <input id="client_id" name="client_id" placeholder="11111111-1111-1111-1111-111111111111" required value="<?php echo htmlspecialchars($_POST['client_id'] ?? ''); ?>">

      <label for="email_to">Report email recipient</label>
      <input id="email_to" name="email_to" placeholder="security@example.com" value="<?php echo htmlspecialchars($_POST['email_to'] ?? ''); ?>">

      <label for="report_base_url">Report Base URL (auto-generated)</label>
      <input id="report_base_url" name="report_base_url" readonly>

      <label for="certificate_secret_name">Certificate secret name (auto-generated)</label>
      <input id="certificate_secret_name" readonly>

      <label for="certificate_password_secret_name">Certificate password secret name (auto-generated)</label>
      <input id="certificate_password_secret_name" readonly>

      <label for="certificate_base64">Certificate Base64 (PFX content)</label>
      <textarea id="certificate_base64" name="certificate_base64" placeholder="Paste base64-encoded PFX content here" required><?php echo htmlspecialchars($_POST['certificate_base64'] ?? ''); ?></textarea>

      <label for="certificate_password">Certificate password</label>
      <input id="certificate_password" name="certificate_password" type="password" placeholder="Optional if your PFX has a password">

      <button type="submit">Save tenant and store secrets</button>
    </form>

    <?php if (count($messages) > 1): ?>
      <h2>Saved output</h2>
      <?php for ($i = 1; $i < count($messages) - 1; $i++): ?>
        <p><?php echo htmlspecialchars($messages[$i]); ?></p>
      <?php endfor; ?>
      <pre><?php echo htmlspecialchars($messages[count($messages) - 1]); ?></pre>
    <?php endif; ?>

    <h2>Current tenants</h2>
    <pre><?php echo htmlspecialchars(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
  </div>
</body>
</html>
