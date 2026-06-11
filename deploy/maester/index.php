<?php
$tenantsPath = __DIR__ . '/tenants.json';
$tenants = [];
if (file_exists($tenantsPath)) {
    $config = json_decode(file_get_contents($tenantsPath), true);
    $tenants = $config['tenants'] ?? [];
}

function tenant_summary(string $tenantId): ?array {
    $path = __DIR__ . '/' . $tenantId . '/latest/summary.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maester Multi-Tenant Dashboard</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #1f2937; background: #f8fafc; }
    h1, h2 { margin-bottom: 0.4rem; }
    .muted { color: #6b7280; }
    .topbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .button { display: inline-block; padding: 0.75rem 1rem; background: #0b5fff; color: white; text-decoration: none; border-radius: 8px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .stats { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
    .stat { background: #f3f4f6; border-radius: 8px; padding: 0.65rem 0.85rem; min-width: 88px; }
    .empty { padding: 1rem; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; }
    .linkrow { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
    a.textlink { color: #0b5fff; text-decoration: none; }
  </style>
</head>
<body>
  <div class="topbar">
    <div>
      <h1>SecureIT Dashboard</h1>
      <div class="muted">Prototype multi-tenant Microsoft 365 security reporting</div>
    </div>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
      <a class="button" href="admin.php">Admin Actions</a>
      <a class="button" href="onboard.php">Customer Onboarding</a>
    </div>
  </div>

  <?php if (!$tenants): ?>
    <div class="empty">
      <strong>No tenants configured yet.</strong>
      <p class="muted">Use the onboarding flow to add your first tenant.</p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($tenants as $tenant): ?>
        <?php
          $tenantKey = $tenant['id'] ?? 'unknown';
          $summary = tenant_summary($tenantKey);
        ?>
        <div class="card">
          <h2><?php echo htmlspecialchars($tenant['name'] ?? $tenantKey); ?></h2>
          <div class="muted">Tenant key: <?php echo htmlspecialchars($tenantKey); ?></div>
          <div class="muted">Tenant ID: <?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div>
          <div class="muted">Report Base URL: <?php echo htmlspecialchars($tenant['reportBaseUrl'] ?? 'Unknown'); ?></div>

          <?php if ($summary): ?>
            <div class="stats">
              <div class="stat"><strong>Total</strong><br><?php echo htmlspecialchars((string)($summary['total'] ?? 0)); ?></div>
              <div class="stat"><strong>Passed</strong><br><?php echo htmlspecialchars((string)($summary['passed'] ?? 0)); ?></div>
              <div class="stat"><strong>Failed</strong><br><?php echo htmlspecialchars((string)($summary['failed'] ?? 0)); ?></div>
              <div class="stat"><strong>Skipped</strong><br><?php echo htmlspecialchars((string)($summary['skipped'] ?? 0)); ?></div>
            </div>
            <div class="linkrow">
              <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">View tenant</a>
              <a class="textlink" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Latest report</a>
            </div>
            <p class="muted">Last generated: <?php echo htmlspecialchars($summary['generatedAt'] ?? 'Unknown'); ?></p>
          <?php else: ?>
            <p class="muted">No report published yet for this tenant.</p>
            <div class="linkrow">
              <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">View tenant</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</body>
</html>
