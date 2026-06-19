<?php
require __DIR__ . '/lib.php';
$authRole = secureit_current_user_role();
if ($authRole !== 'admin') {
    header('Location: login.php?denied=1', true, 302);
    exit;
}

$baseSiteUrl = secureit_config()['base_url'];
$messages = [];
$errors = [];
$config = secureit_load_tenants();
$config['tenants'] = $config['tenants'] ?? [];
$example = ['tenants' => [[
    'id' => 'contoso-prod',
    'name' => 'Contoso Production',
    'tenantId' => '00000000-0000-0000-0000-000000000000',
    'clientId' => '11111111-1111-1111-1111-111111111111',
    'authMode' => 'client-secret',
    'clientSecretName' => 'AZURE-CLIENT-SECRET-CONTOSO-PROD',
    'reportBaseUrl' => $baseSiteUrl . '/contoso-prod',
    'emailTo' => 'security@example.com',
]]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantKey = trim(strtolower($_POST['tenant_key'] ?? ''));
    $tenantName = trim($_POST['tenant_name'] ?? '');
    $tenantId = trim($_POST['tenant_id'] ?? '');
    $clientId = trim($_POST['client_id'] ?? '');
    $authMode = 'client-secret';
    $clientSecretName = trim($_POST['client_secret_name'] ?? '');
    $emailTo = trim($_POST['email_to'] ?? '');
    $reportBaseUrl = secureit_build_report_base_url($tenantKey);

    if (!$clientSecretName && $tenantKey) {
        $clientSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $tenantKey));
    }

    if (!$tenantKey || !secureit_valid_tenant_key($tenantKey)) {
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
    if (!$clientSecretName) {
        $errors[] = 'Client secret name is required for client secret authentication.';
    }
    if (secureit_tenant_exists($config['tenants'], $tenantKey)) {
        $errors[] = 'That tenant key already exists.';
    }

    if (!$errors) {
        $tenant = [
            'id' => $tenantKey,
            'name' => $tenantName,
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'authMode' => $authMode,
            'clientSecretName' => $clientSecretName,
            'reportBaseUrl' => $reportBaseUrl,
            'emailTo' => $emailTo,
        ];

        $config['tenants'][] = $tenant;
        secureit_save_tenants($config);

        $tenantDir = secureit_reports_root() . '/' . $tenantKey;
        @mkdir($tenantDir . '/latest', 0775, true);
        @mkdir($tenantDir . '/history', 0775, true);

        $messages[] = 'Tenant saved into the container app successfully.';
        $messages[] = 'Report Base URL was generated automatically from the tenant key.';
        $messages[] = 'Suggested tenant JSON block:';
        $messages[] = json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $config = secureit_load_tenants();
    }
}

ob_start();
?>
<section class="section split">
  <article class="card panel">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">New tenant setup</h2>
        <div class="muted">Create the local tenant record used by the SecureIT app dashboard and report routing.</div>
      </div>
    </div>

    <?php foreach ($errors as $error): ?>
      <div class="empty-state" style="margin-bottom:12px; color:#9b3f17; background:#fff3ef; border-color:#f3c8b7;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php if ($messages): ?>
      <div class="empty-state" style="margin-bottom:16px; color:#136045; background:#edf9f5; border-color:#b9ead5;"><?php echo htmlspecialchars($messages[0]); ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="tenant_key">Tenant key</label>
      <input id="tenant_key" name="tenant_key" placeholder="contoso-prod" required oninput="updateReportBaseUrl()" value="<?php echo htmlspecialchars($_POST['tenant_key'] ?? ''); ?>">
      <p class="field-note">Simple lowercase key used in URLs, folders, and tenant configuration.</p>

      <label for="tenant_name">Tenant name</label>
      <input id="tenant_name" name="tenant_name" placeholder="Contoso Production" required value="<?php echo htmlspecialchars($_POST['tenant_name'] ?? ''); ?>">
      <p class="field-note">Customer-facing display name used on the dashboard and tenant pages.</p>

      <label for="tenant_id">M365 Tenant ID</label>
      <input id="tenant_id" name="tenant_id" placeholder="00000000-0000-0000-0000-000000000000" required value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? ''); ?>">

      <label for="client_id">M365 Application ID</label>
      <input id="client_id" name="client_id" placeholder="11111111-1111-1111-1111-111111111111" required value="<?php echo htmlspecialchars($_POST['client_id'] ?? ''); ?>">
      <p class="field-note">Client ID of the Entra app registration used by SecureIT for this tenant.</p>

      <label for="auth_mode_display">Authentication method</label>
      <input id="auth_mode_display" type="text" value="Client secret" readonly disabled>
      <input type="hidden" name="auth_mode" value="client-secret">
      <p class="field-note">Client secret with Azure Key Vault storage is the current onboarding method for this app.</p>

      <label for="client_secret_name">Key Vault client secret name</label>
      <input id="client_secret_name" name="client_secret_name" placeholder="AZURE-CLIENT-SECRET-EXAMPLE-TENANT" value="<?php echo htmlspecialchars($_POST['client_secret_name'] ?? ''); ?>">
      <p class="field-note">Derived automatically from the tenant key unless manually overridden.</p>

      <label for="email_to">Report email recipient</label>
      <input id="email_to" name="email_to" placeholder="security@example.com" value="<?php echo htmlspecialchars($_POST['email_to'] ?? ''); ?>">

      <label for="report_base_url">Report Base URL (auto-generated)</label>
      <input id="report_base_url" name="report_base_url" readonly>

      <div style="height:16px;"></div>
      <button type="submit">Save customer</button>
    </form>

    <?php if (count($messages) > 1): ?>
      <div class="section" style="margin-top:18px;">
        <h3 class="section-title">Saved output</h3>
        <?php for ($i = 1; $i < count($messages) - 1; $i++): ?>
          <p><?php echo htmlspecialchars($messages[$i]); ?></p>
        <?php endfor; ?>
        <pre><?php echo htmlspecialchars($messages[count($messages) - 1]); ?></pre>
      </div>
    <?php endif; ?>
  </article>

  <aside style="display:grid; gap:22px; align-content:start;">
      <div class="empty-state" style="width:100%; max-width:100%; box-sizing:border-box; overflow-wrap:anywhere;">
        <h3 class="section-title" style="font-size:1.35rem; margin-bottom:12px;">Required items</h3>
        <ul style="margin:12px 0 0 18px; padding:0; line-height:1.7; color:var(--eden);">
          <li>Customer organisation name and preferred tenant key</li>
          <li>Microsoft 365 Tenant ID</li>
          <li>Entra ID App Registration Application (client) ID</li>
          <li>Client secret authentication details for the SecureIT app</li>
          <li>Key Vault secret name for the client secret</li>
          <li>Customer report recipient email address</li>
          <li>Admin consent to the required Microsoft Graph application permissions</li>
        </ul>
      </div>

      <div class="panel" style="padding:22px; background:var(--surface-soft); border-radius:20px; border:1px solid var(--line); box-shadow:none; overflow:hidden; min-width:0;">
        <h3 class="section-title" style="font-size:1.35rem; margin-bottom:14px;">Onboarding flow</h3>
        <div style="display:grid; gap:12px; min-width:0;">
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>1. Confirm tenant details</strong><br><span class="muted">Collect the customer name, tenant key, tenant ID, report recipient, and the client secret details that will be used for SecureIT access.</span></div>
          <div style="text-align:center; color:var(--brand); font-weight:700;">↓</div>
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>2. Create the Entra ID App Registration</strong><br><span class="muted">In Microsoft Entra admin center, create a new app registration for the customer tenant and record the Application (client) ID and Directory (tenant) ID.</span></div>
          <div style="text-align:center; color:var(--brand); font-weight:700;">↓</div>
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>3. Add API permissions and grant admin consent</strong><br><span class="muted">Assign the required Microsoft Graph application permissions, then grant tenant-wide admin consent so SecureIT can run non-interactive reporting.</span></div>
          <div style="text-align:center; color:var(--brand); font-weight:700;">↓</div>
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>4. Create and store the client secret</strong><br><span class="muted">Generate the client secret, store it in Azure Key Vault using the agreed secret name, and confirm that the secret value and expiry are recorded securely.</span></div>
          <div style="text-align:center; color:var(--brand); font-weight:700;">↓</div>
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>5. Save the tenant in SecureIT</strong><br><span class="muted">Complete the tenant form on the left, confirm the generated report URL, and save the record to create the local tenant structure and reporting folders.</span></div>
          <div style="text-align:center; color:var(--brand); font-weight:700;">↓</div>
          <div style="width:100%; max-width:100%; box-sizing:border-box; padding:14px 16px; border-radius:16px; background:#fff; border:1px solid var(--line); overflow-wrap:anywhere;"><strong>6. Validate reporting</strong><br><span class="muted">Run the first reporting cycle, confirm access, verify the report recipient, and check that the latest report path is resolving correctly.</span></div>
        </div>
      </div>

      <div>
        <h3 class="section-title" style="font-size:1.35rem; margin-bottom:12px;">Required Entra ID / Microsoft Graph permissions</h3>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Permission</th>
                <th>Type</th>
                <th>Purpose</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Policy.Read.All</td><td>Application</td><td>Read Conditional Access and other security policy configuration.</td></tr>
              <tr><td>Policy.Read.ConditionalAccess</td><td>Application</td><td>Inspect Conditional Access policy assignments and logic.</td></tr>
              <tr><td>Directory.Read.All</td><td>Application</td><td>Read directory objects referenced by policy and tenant configuration.</td></tr>
              <tr><td>Application.Read.All</td><td>Application</td><td>Review enterprise apps and app registrations relevant to assessment output.</td></tr>
              <tr><td>User.Read.All</td><td>Application</td><td>Support policy impact analysis and What If style identity checks.</td></tr>
              <tr><td>Group.Read.All</td><td>Application</td><td>Resolve group-based policy targeting and exclusions.</td></tr>
              <tr><td>Organization.Read.All</td><td>Application</td><td>Read tenant profile information for reporting context.</td></tr>
            </tbody>
          </table>
        </div>
        <p class="field-note">Exact permissions may evolve with the reporting scope, but these are the core read permissions typically required for SecureIT style tenant assessment and policy analysis.</p>
      </div>
  </aside>
</section>
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
    if (secretInput && !secretInput.dataset.manualOverride) {
      secretInput.value = deriveSecretName(tenantKey);
    }
  }

  window.addEventListener('DOMContentLoaded', () => {
    const secretInput = document.getElementById('client_secret_name');
    if (secretInput) {
      secretInput.addEventListener('input', () => {
        secretInput.dataset.manualOverride = secretInput.value.trim() !== '' ? 'true' : '';
      });
    }
    updateReportBaseUrl();
  });
</script>
<?php
$content = ob_get_clean();
secureit_render_shell('Customer Onboarding - ' . secureit_config()['app_name'], $content, [
    'pageTitle' => 'Customer Onboarding',
    'pageIntro' => 'Create the local tenant record used by the SecureIT app dashboard and report routing.',
    'eyebrow' => '',
    'heroBackground' => secureit_default_hero_background(),
    'heroTextAlign' => 'center',
    'navLinks' => [],
    'headerMenu' => [
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
    ],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'login.php', 'label' => 'Customer login'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Admin dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
