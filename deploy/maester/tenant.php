<?php
$tenantKey = $_GET['tenant'] ?? '';
$tenantsPath = __DIR__ . '/tenants.json';
$config = file_exists($tenantsPath) ? json_decode(file_get_contents($tenantsPath), true) : ['tenants' => []];
$tenants = $config['tenants'] ?? [];
$tenant = null;
foreach ($tenants as $item) {
    if (($item['id'] ?? '') === $tenantKey) {
        $tenant = $item;
        break;
    }
}

if (!$tenant) {
    http_response_code(404);
    echo 'Tenant not found';
    exit;
}

$summaryPath = __DIR__ . '/' . $tenantKey . '/latest/summary.json';
$summary = file_exists($summaryPath) ? json_decode(file_get_contents($summaryPath), true) : null;
$historyRoot = __DIR__ . '/' . $tenantKey . '/history';
$history = [];
if (is_dir($historyRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($historyRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'summary.json') {
            $relative = str_replace(__DIR__ . '/', '', $file->getPathname());
            $history[] = [
                'reportPath' => dirname($relative) . '/index.html',
                'summary' => json_decode(file_get_contents($file->getPathname()), true)
            ];
        }
    }
    usort($history, function ($a, $b) {
        return strcmp($b['summary']['generatedAt'] ?? '', $a['summary']['generatedAt'] ?? '');
    });
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($tenant['name']); ?> - Maester</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #1f2937; background: #f8fafc; }
    .card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
    .muted { color: #6b7280; }
    .button, .textlink { color: #0b5fff; text-decoration: none; }
    table { width: 100%; border-collapse: collapse; background: white; }
    th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
  </style>
</head>
<body>
  <p><a class="textlink" href="index.php">← Back to dashboard</a></p>
  <div class="card">
    <h1><?php echo htmlspecialchars($tenant['name']); ?></h1>
    <p class="muted">Tenant key: <?php echo htmlspecialchars($tenant['id']); ?></p>
    <p class="muted">Tenant ID: <?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></p>
    <p class="muted">Client ID: <?php echo htmlspecialchars($tenant['clientId'] ?? 'Unknown'); ?></p>
    <p class="muted">Report Base URL: <?php echo htmlspecialchars($tenant['reportBaseUrl'] ?? 'Unknown'); ?></p>
    <?php if ($summary): ?>
      <p><strong>Latest result:</strong> <?php echo htmlspecialchars((string)($summary['failed'] ?? 0)); ?> failed of <?php echo htmlspecialchars((string)($summary['total'] ?? 0)); ?></p>
      <p><a class="button" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a></p>
    <?php else: ?>
      <p class="muted">No published report yet for this tenant.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Run history</h2>
    <?php if (!$history): ?>
      <p class="muted">No historical reports found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Generated</th>
            <th>Total</th>
            <th>Passed</th>
            <th>Failed</th>
            <th>Skipped</th>
            <th>Report</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $item): ?>
            <?php $s = $item['summary'] ?? []; ?>
            <tr>
              <td><?php echo htmlspecialchars($s['generatedAt'] ?? 'Unknown'); ?></td>
              <td><?php echo htmlspecialchars((string)($s['total'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars((string)($s['passed'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars((string)($s['failed'] ?? 0)); ?></td>
              <td><?php echo htmlspecialchars((string)($s['skipped'] ?? 0)); ?></td>
              <td><a class="textlink" href="<?php echo htmlspecialchars($item['reportPath']); ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
