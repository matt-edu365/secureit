<?php
require __DIR__ . '/_theme.php';
secureit_require_admin_access();

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

ob_start();
?>
<section class="section split">
  <article class="card panel">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">Shared platform settings</h2>
        <div class="muted">Manage the SecureIT platform values used across tenants in this prototype environment.</div>
      </div>
    </div>

    <?php foreach ($errors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($messages): ?>
      <div class="success" style="margin-bottom:16px;"><?php echo htmlspecialchars($messages[0]); ?></div>
    <?php endif; ?>

    <form method="post">
      <h3 class="section-title">Azure and secret storage</h3>

      <label for="key_vault_name">Azure Key Vault name</label>
      <input id="key_vault_name" name="key_vault_name" placeholder="secureit-prod-kv" value="<?php echo htmlspecialchars($config['azure']['keyVaultName'] ?? ''); ?>">
      <p class="field-note">Shared Key Vault name used to store tenant authentication secrets and related values.</p>

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

      <h3 class="section-title">Notification defaults</h3>

      <label for="default_from_name">Default report sender name</label>
      <input id="default_from_name" name="default_from_name" placeholder="SecureIT Reports" value="<?php echo htmlspecialchars($config['notifications']['defaultFromName'] ?? ''); ?>">
      <p class="field-note">Default sender display name for shared report and notification workflows.</p>

      <label for="default_reply_to">Default reply-to address</label>
      <input id="default_reply_to" name="default_reply_to" placeholder="security@example.com" value="<?php echo htmlspecialchars($config['notifications']['defaultReplyTo'] ?? ''); ?>">
      <p class="field-note">Reply-to address for reports unless a tenant-specific workflow overrides it.</p>

      <h3 class="section-title">Reporting defaults</h3>

      <label for="base_site_url">Base site URL</label>
      <input id="base_site_url" name="base_site_url" placeholder="https://example.ict365.uk" value="<?php echo htmlspecialchars($config['reports']['baseSiteUrl'] ?? 'https://example.ict365.uk'); ?>">
      <p class="field-note">Used when tenant report URLs need to be derived from the common platform address.</p>

      <button type="submit">Save admin settings</button>
    </form>
  </article>

  <aside class="card panel">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">Reference</h2>
        <div class="muted">Example shape for the local admin configuration file.</div>
      </div>
    </div>
    <div class="empty-state" style="margin-bottom:16px;">
      <strong>Keep secrets out of this page.</strong>
      <p class="muted">Store sensitive values in Azure Key Vault or another secure secret store wherever possible.</p>
    </div>
    <pre><?php echo htmlspecialchars(json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
  </aside>
</section>
<?php
$content = ob_get_clean();
secureit_render_layout(
    'Admin Actions - SecureIT',
    'Admin Actions',
    'Manage shared platform defaults for Key Vault, notifications, and reporting in the ICT365 SecureIT prototype.',
    $content,
    [
        'eyebrow' => 'SecureIT platform administration',
        'backHref' => 'dashboard.php',
        'backLabel' => 'Back to admin dashboard',
        'heroBadges' => [
            'Shared Key Vault: ' . ($config['azure']['keyVaultName'] ?? 'Not set'),
            'Base site URL: ' . ($config['reports']['baseSiteUrl'] ?? 'https://example.ict365.uk'),
        ],
        'navLinks' => [],
    ]
);
