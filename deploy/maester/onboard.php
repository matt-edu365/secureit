<?php
$tenantsPath = __DIR__ . '/tenants.json';
$examplePath = __DIR__ . '/tenants.example.json';
$adminConfigPath = __DIR__ . '/admin-config.json';
$adminConfig = file_exists($adminConfigPath) ? json_decode(file_get_contents($adminConfigPath), true) : [];
$baseSiteUrl = $adminConfig['reports']['baseSiteUrl'] ?? 'https://example.ict365.uk';
$messages = [];
$errors = [];
$example = file_exists($examplePath) ? json_decode(file_get_contents($examplePath), true) : ['tenants' => []];
$config = file_exists($tenantsPath) ? json_decode(file_get_contents($tenantsPath), true) : ['tenants' => []];
$config['tenants'] = $config['tenants'] ?? [];

function build_report_base_url(string $baseSiteUrl, string $tenantKey): string {
    $tenantKey = trim(strtolower($tenantKey));
    return rtrim($baseSiteUrl, '/') . '/' . rawurlencode($tenantKey);
}

function valid_tenant_key(string $tenantKey): bool {
    return (bool) preg_match('/^[a-z0-9-]+$/', $tenantKey);
}

function tenant_exists(array $tenants, string $tenantKey): bool {
    foreach ($tenants as $tenant) {
        if (($tenant['id'] ?? '') === $tenantKey) {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantKey = trim(strtolower($_POST['tenant_key'] ?? ''));
    $tenantName = trim($_POST['tenant_name'] ?? '');
    $tenantId = trim($_POST['tenant_id'] ?? '');
    $clientId = trim($_POST['client_id'] ?? '');
    $authMode = trim($_POST['auth_mode'] ?? 'client-secret');
    $clientSecretName = trim($_POST['client_secret_name'] ?? '');
    $emailTo = trim($_POST['email_to'] ?? '');
    $reportBaseUrl = build_report_base_url($baseSiteUrl, $tenantKey);

    if (!$clientSecretName && $tenantKey) {
        $clientSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $tenantKey));
    }

    if (!$tenantKey || !valid_tenant_key($tenantKey)) {
        $errors[] = 'Tenant key must contain only lowercase letters, numbers, and hyphens.';
    }
    if (!$tenantName) {
        $errors[] = 'Tenant name is required.';
    }
    if (!$tenantId) {
        $errors[] = 'Tenant ID is required.';
    }
    if (!$clientId) {
        $errors[] = 'Client ID is required.';
    }
    if (!$authMode) {
        $errors[] = 'Authentication method is required.';
    }
    if ($authMode === 'client-secret' && !$clientSecretName) {
        $errors[] = 'Client secret name is required for client secret authentication.';
    }
    if (tenant_exists($config['tenants'], $tenantKey)) {
        $errors[] = 'That tenant key already exists.';
    }

    if (!$errors) {
        $tenant = [
            'id' => $tenantKey,
            'name' => $tenantName,
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'authMode' => $authMode,
            'clientSecretName' => $authMode === 'client-secret' ? $clientSecretName : '',
            'reportBaseUrl' => $reportBaseUrl,
            'emailTo' => $emailTo,
        ];

        $config['tenants'][] = $tenant;
        file_put_contents($tenantsPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $tenantDir = __DIR__ . '/' . $tenantKey;
        @mkdir($tenantDir . '/latest', 0775, true);
        @mkdir($tenantDir . '/history', 0775, true);

        $messages[] = 'Tenant saved into the prototype successfully.';
        $messages[] = 'Report Base URL was generated automatically from the tenant key.';
        $messages[] = 'Suggested tenant JSON block:';
        $messages[] = json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $config = file_exists($tenantsPath) ? json_decode(file_get_contents($tenantsPath), true) : $config;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Onboarding - SecureIT</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #1f2937; background: #f8fafc; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; max-width: 900px; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input, select { width: 100%; max-width: 100%; box-sizing: border-box; padding: 0.7rem; margin-top: 0.35rem; border: 1px solid #d1d5db; border-radius: 8px; }
    input[readonly], input[disabled] { background: #f3f4f6; color: #4b5563; }
    button { margin-top: 1.25rem; padding: 0.8rem 1rem; background: #0b5fff; color: white; border: 0; border-radius: 8px; cursor: pointer; }
    pre { background: #111827; color: #f9fafb; padding: 1rem; border-radius: 10px; overflow: auto; }
    .muted { color: #6b7280; }
    .field-note { margin-top: 0.35rem; color: #6b7280; font-size: 0.84rem; font-style: italic; }
    .lock-row { display: flex; gap: 0.5rem; align-items: center; }
    .lock-row input { margin-top: 0.35rem; }
    .icon-button { margin-top: 0.35rem; padding: 0.7rem 0.9rem; min-width: 48px; background: #e5e7eb; color: #1f2937; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; }
    .icon-button:hover { background: #dbe1e8; }
    .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    .success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    a { color: #0b5fff; text-decoration: none; }
  </style>
  <script>
    function deriveSecretName(tenantKey) {
      if (!tenantKey) return '';
      return `AZURE-CLIENT-SECRET-${tenantKey.toUpperCase().replace(/_/g, '-').replace(/[^A-Z0-9-]/g, '-')}`;
    }

    function updateReportBaseUrl() {
      const tenantKey = document.getElementById('tenant_key').value.trim().toLowerCase();
      const baseUrl = <?php echo json_encode($baseSiteUrl, JSON_UNESCAPED_SLASHES); ?>;
      document.getElementById('report_base_url').value = tenantKey ? `${baseUrl}/${encodeURIComponent(tenantKey)}` : '';

      const secretInput = document.getElementById('client_secret_name');
      const secretHidden = document.getElementById('client_secret_name_hidden');
      if (secretInput.dataset.locked === 'true') {
        const derived = deriveSecretName(tenantKey);
        secretInput.value = derived;
        secretHidden.value = derived;
      } else {
        secretHidden.value = secretInput.value;
      }
    }

    function toggleSecretNameLock() {
      const input = document.getElementById('client_secret_name');
      const button = document.getElementById('client_secret_unlock');
      const locked = input.dataset.locked === 'true';
      if (locked) {
        input.dataset.locked = 'false';
        input.disabled = false;
        input.focus();
        button.textContent = '🔓';
        button.setAttribute('aria-label', 'Lock Key Vault client secret name field');
        button.title = 'Lock field';
      } else {
        input.dataset.locked = 'true';
        input.disabled = true;
        const derived = deriveSecretName(document.getElementById('tenant_key').value.trim().toLowerCase());
        input.value = derived;
        document.getElementById('client_secret_name_hidden').value = derived;
        button.textContent = '🔒';
        button.setAttribute('aria-label', 'Unlock Key Vault client secret name field');
        button.title = 'Unlock field';
      }
    }

    window.addEventListener('DOMContentLoaded', () => {
      const input = document.getElementById('client_secret_name');
      const hidden = document.getElementById('client_secret_name_hidden');
      if (input) {
        input.dataset.locked = 'true';
        input.disabled = true;
        hidden.value = input.value;
        input.addEventListener('input', () => { hidden.value = input.value; });
      }
      updateReportBaseUrl();
    });
  </script>
</head>
<body>
  <p><a href="index.php">← Back to dashboard</a></p>
  <div class="card">
    <h1>Customer Onboarding</h1>
    <p class="muted">Prototype onboarding flow that now saves tenant metadata locally in the prototype.</p>

    <?php foreach ($errors as $error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($messages): ?>
      <div class="success"><?php echo htmlspecialchars($messages[0]); ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="tenant_key">Tenant key</label>
      <input id="tenant_key" name="tenant_key" placeholder="contoso-prod" required oninput="updateReportBaseUrl()" value="<?php echo htmlspecialchars($_POST['tenant_key'] ?? ''); ?>">
      <p class="field-note">The tenant key is the internal short name used in URLs, folders, and configuration. It is chosen by the admin during setup and should stay simple, lowercase, and consistent.</p>

      <label for="tenant_name">Tenant name</label>
      <input id="tenant_name" name="tenant_name" placeholder="Contoso Production" required value="<?php echo htmlspecialchars($_POST['tenant_name'] ?? ''); ?>">
      <p class="field-note">The tenant name is the customer-facing display name shown in the dashboard and reports. It is also chosen by the admin during setup and can be more descriptive than the tenant key.</p>

      <label for="tenant_id">M365 Tenant ID</label>
      <input id="tenant_id" name="tenant_id" placeholder="00000000-0000-0000-0000-000000000000" required value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? ''); ?>">

      <label for="client_id">M365 Application ID</label>
      <input id="client_id" name="client_id" placeholder="11111111-1111-1111-1111-111111111111" required value="<?php echo htmlspecialchars($_POST['client_id'] ?? ''); ?>">
      <p class="field-note">The M365 Application ID is the client ID of the Entra app registration used to run SecureIT checks against this customer tenant. It is provided by the admin during setup.</p>

      <label for="auth_mode">Authentication method</label>
      <?php $authModeValue = $_POST['auth_mode'] ?? 'client-secret'; ?>
      <select id="auth_mode" name="auth_mode">
        <option value="client-secret"<?php echo $authModeValue === 'client-secret' ? ' selected' : ''; ?>>Client secret</option>
        <option value="certificate"<?php echo $authModeValue === 'certificate' ? ' selected' : ''; ?>>Certificate</option>
      </select>
      <p class="field-note">Choose how SecureIT will authenticate to this customer tenant. For your current design, client secret auth with Azure Key Vault storage is the default path.</p>

      <label for="client_secret_name">Key Vault client secret name</label>
      <div class="lock-row">
        <input id="client_secret_name" placeholder="AZURE-CLIENT-SECRET-EXAMPLE-TENANT" value="<?php echo htmlspecialchars($_POST['client_secret_name'] ?? ''); ?>">
        <input type="hidden" id="client_secret_name_hidden" name="client_secret_name" value="<?php echo htmlspecialchars($_POST['client_secret_name'] ?? ''); ?>">
        <button type="button" id="client_secret_unlock" class="icon-button" onclick="toggleSecretNameLock()" aria-label="Unlock Key Vault client secret name field" title="Unlock field">🔒</button>
      </div>
      <p class="field-note">By default this is derived automatically from the tenant key and kept locked. Use the padlock button if you need to override it manually.</p>

      <label for="email_to">Report email recipient</label>
      <input id="email_to" name="email_to" placeholder="security@example.com" value="<?php echo htmlspecialchars($_POST['email_to'] ?? ''); ?>">

      <label for="report_base_url">Report Base URL (auto-generated)</label>
      <input id="report_base_url" name="report_base_url" readonly>

      <button type="submit">Save customer</button>
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
