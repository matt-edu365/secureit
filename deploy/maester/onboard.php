<?php
$tenantsPath = __DIR__ . '/tenants.json';
$examplePath = __DIR__ . '/tenants.json';
$baseSiteUrl = 'https://example.ict365.uk';
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
    $emailTo = trim($_POST['email_to'] ?? '');
    $reportBaseUrl = build_report_base_url($baseSiteUrl, $tenantKey);

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
    if (tenant_exists($config['tenants'], $tenantKey)) {
        $errors[] = 'That tenant key already exists.';
    }

    if (!$errors) {
        $tenant = [
            'id' => $tenantKey,
            'name' => $tenantName,
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'authMode' => 'certificate',
            'certificateSecretName' => 'AZURE_CLIENT_CERTIFICATE_B64_' . strtoupper(str_replace('-', '_', $tenantKey)),
            'certificatePasswordSecretName' => 'AZURE_CLIENT_CERTIFICATE_PASSWORD_' . strtoupper(str_replace('-', '_', $tenantKey)),
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
    input { width: 100%; padding: 0.7rem; margin-top: 0.35rem; border: 1px solid #d1d5db; border-radius: 8px; }
    input[readonly] { background: #f3f4f6; color: #4b5563; }
    button { margin-top: 1.25rem; padding: 0.8rem 1rem; background: #0b5fff; color: white; border: 0; border-radius: 8px; cursor: pointer; }
    pre { background: #111827; color: #f9fafb; padding: 1rem; border-radius: 10px; overflow: auto; }
    .muted { color: #6b7280; }
    .field-note { margin-top: 0.35rem; color: #6b7280; font-size: 0.92rem; font-style: italic; }
    .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    .success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    a { color: #0b5fff; text-decoration: none; }
  </style>
  <script>
    function updateReportBaseUrl() {
      const tenantKey = document.getElementById('tenant_key').value.trim().toLowerCase();
      const baseUrl = <?php echo json_encode($baseSiteUrl, JSON_UNESCAPED_SLASHES); ?>;
      document.getElementById('report_base_url').value = tenantKey ? `${baseUrl}/${encodeURIComponent(tenantKey)}` : '';
    }
    window.addEventListener('DOMContentLoaded', updateReportBaseUrl);
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

      <label for="tenant_id">Tenant ID</label>
      <input id="tenant_id" name="tenant_id" placeholder="00000000-0000-0000-0000-000000000000" required value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? ''); ?>">

      <label for="client_id">Client ID</label>
      <input id="client_id" name="client_id" placeholder="11111111-1111-1111-1111-111111111111" required value="<?php echo htmlspecialchars($_POST['client_id'] ?? ''); ?>">

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
