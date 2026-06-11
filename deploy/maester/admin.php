<?php
$configPath = __DIR__ . '/admin-config.json';
$examplePath = __DIR__ . '/admin-config.example.json';
$messages = [];
$errors = [];
$example = file_exists($examplePath) ? json_decode(file_get_contents($examplePath), true) : [];
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
$config = is_array($config) ? $config : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = [
        'azure' => [
            'keyVaultName' => trim($_POST['key_vault_name'] ?? ''),
            'keyVaultUri' => trim($_POST['key_vault_uri'] ?? ''),
            'certificateStorageMode' => trim($_POST['certificate_storage_mode'] ?? 'key-vault'),
        ],
        'notifications' => [
            'defaultFromName' => trim($_POST['default_from_name'] ?? ''),
            'defaultReplyTo' => trim($_POST['default_reply_to'] ?? ''),
        ],
        'reports' => [
            'baseSiteUrl' => trim($_POST['base_site_url'] ?? 'https://example.ict365.uk'),
        ],
    ];

    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    $messages[] = 'Admin settings saved successfully.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Actions - SecureIT</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #1f2937; background: #f8fafc; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; max-width: 900px; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input, select { width: 100%; max-width: 100%; box-sizing: border-box; padding: 0.7rem; margin-top: 0.35rem; border: 1px solid #d1d5db; border-radius: 8px; }
    button { margin-top: 1.25rem; padding: 0.8rem 1rem; background: #0b5fff; color: white; border: 0; border-radius: 8px; cursor: pointer; }
    pre { background: #111827; color: #f9fafb; padding: 1rem; border-radius: 10px; overflow: auto; }
    .muted { color: #6b7280; }
    .field-note { margin-top: 0.35rem; color: #6b7280; font-size: 0.84rem; font-style: italic; }
    .success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem 1rem; border-radius: 10px; margin-top: 1rem; }
    a { color: #0b5fff; text-decoration: none; }
    .actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
    .section-title { margin-top: 1.5rem; }
  </style>
</head>
<body>
  <p><a href="index.php">← Back to dashboard</a></p>
  <div class="card">
    <h1>Admin Actions</h1>
    <p class="muted">Manage shared SecureIT settings here. Customer-specific values belong on the Customer Onboarding page.</p>
    <p class="field-note">Shared platform metadata entered here is stored in local runtime configuration for this environment. Sensitive secrets should stay in Azure Key Vault or another secure secret store, not in this page.</p>

    <?php if ($messages): ?>
      <div class="success"><?php echo htmlspecialchars($messages[0]); ?></div>
    <?php endif; ?>

    <form method="post">
      <h2 class="section-title">Shared Azure and secret settings</h2>

      <label for="key_vault_name">Azure Key Vault name</label>
      <input id="key_vault_name" name="key_vault_name" placeholder="secureit-prod-kv" value="<?php echo htmlspecialchars($config['azure']['keyVaultName'] ?? ''); ?>">
      <p class="field-note">Shared Key Vault name used to store tenant authentication secrets and related values for customer tenants.</p>

      <label for="key_vault_uri">Azure Key Vault URI</label>
      <input id="key_vault_uri" name="key_vault_uri" placeholder="https://secureit-prod-kv.vault.azure.net/" value="<?php echo htmlspecialchars($config['azure']['keyVaultUri'] ?? ''); ?>">
      <p class="field-note">Base URI for the shared Azure Key Vault used by SecureIT.</p>

      <label for="certificate_storage_mode">Certificate storage mode</label>
      <select id="certificate_storage_mode" name="certificate_storage_mode">
        <?php $mode = $config['azure']['certificateStorageMode'] ?? 'key-vault'; ?>
        <option value="key-vault"<?php echo $mode === 'key-vault' ? ' selected' : ''; ?>>Azure Key Vault</option>
        <option value="local"<?php echo $mode === 'local' ? ' selected' : ''; ?>>Local / manual storage</option>
      </select>
      <p class="field-note">Defines where tenant authentication secrets are expected to be managed by default.</p>

      <h2 class="section-title">Shared notification defaults</h2>

      <label for="default_from_name">Default report sender name</label>
      <input id="default_from_name" name="default_from_name" placeholder="SecureIT Reports" value="<?php echo htmlspecialchars($config['notifications']['defaultFromName'] ?? ''); ?>">
      <p class="field-note">Default sender display name for shared report and notification workflows.</p>

      <label for="default_reply_to">Default reply-to address</label>
      <input id="default_reply_to" name="default_reply_to" placeholder="security@example.com" value="<?php echo htmlspecialchars($config['notifications']['defaultReplyTo'] ?? ''); ?>">
      <p class="field-note">Shared reply-to address for report emails unless a tenant-specific process overrides it.</p>

      <h2 class="section-title">Shared reporting defaults</h2>

      <label for="base_site_url">Base site URL</label>
      <input id="base_site_url" name="base_site_url" placeholder="https://example.ict365.uk" value="<?php echo htmlspecialchars($config['reports']['baseSiteUrl'] ?? 'https://example.ict365.uk'); ?>">
      <p class="field-note">Used when customer-specific report URLs need to be derived from a common platform base address.</p>

      <button type="submit">Save admin settings</button>
    </form>

    <h2 class="section-title">Reference</h2>
    <p class="muted">Tracked example structure for shared admin settings:</p>
    <pre><?php echo htmlspecialchars(json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
  </div>
</body>
</html>
