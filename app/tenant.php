<?php
require __DIR__ . '/lib.php';
$tenantKey = $_GET['tenant'] ?? '';
$tenant = secureit_find_tenant($tenantKey);
$app = secureit_config();

if (!$tenant) {
    http_response_code(404);
    echo 'Tenant not found';
    exit;
}

$config = secureit_load_tenants();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recipients'])) {
    $emailsRaw = trim($_POST['email_to'] ?? '');
    $parts = preg_split('/[\r\n,;]+/', $emailsRaw) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $email = trim($part);
        if ($email === '') {
            continue;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower($email);
        }
    }
    $emails = array_values(array_unique($emails));
    if (count($emails) > 5) {
        $emails = array_slice($emails, 0, 5);
    }

    foreach ($config['tenants'] as &$item) {
        if (($item['id'] ?? '') === $tenantKey) {
            $item['emailTo'] = implode(', ', $emails);
            $tenant = $item;
            break;
        }
    }
    unset($item);
    secureit_save_tenants($config);
}

$summary = secureit_tenant_summary($tenantKey);
$counts = secureit_summary_counts($summary);
$areaData = secureit_resolve_canonical_area_scores($tenantKey);
$functionalAreas = $areaData['areas'] ?? [];
$historyRoot = secureit_reports_root() . '/' . $tenantKey . '/history';
$history = [];
if (is_dir($historyRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($historyRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'summary.json') {
            $relative = str_replace(secureit_reports_root() . '/', '', $file->getPathname());
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

ob_start();
?>
<section class="section split">
  <article class="card panel">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">Tenant details</h2>
        <div class="muted"></div>
      </div>
    </div>
    <div class="kv">
      <div class="kv-row"><div class="kv-label">Tenant name</div><div class="kv-value"><?php echo htmlspecialchars($tenant['name']); ?></div></div>
      <div class="kv-row"><div class="kv-label">Tenant ID</div><div class="kv-value"><?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div></div>
      <div class="kv-row"><div class="kv-label">Auth mode</div><div class="kv-value"><?php echo htmlspecialchars($tenant['authMode'] ?? 'Unknown'); ?></div></div>
      <div class="kv-row">
        <div class="kv-label">Report recipient</div>
        <div class="kv-value">
          <form method="post" style="display:grid; gap:10px;">
            <input id="email_to" name="email_to" type="text" value="<?php echo htmlspecialchars($tenant['emailTo'] ?? ''); ?>" placeholder="security@example.com, it@example.com">
            <p class="field-note" style="margin:0;">You can enter up to 5 email addresses, separated by commas, semicolons, or new lines.</p>
            <div><button type="submit" name="save_recipients" value="1">Save recipients</button></div>
          </form>
        </div>
      </div>
    </div>
  </article>

  <article class="card panel">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">Latest posture</h2>
        <div class="muted">A quick operational snapshot from the latest published run.</div>
      </div>
    </div>

    <?php if ($summary): ?>
      <div class="stats-row" style="margin-bottom:14px;">
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['total']); ?></strong><span>Total</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['passed']); ?></strong><span>Passed</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['failed']); ?></strong><span>Failed</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['skipped']); ?></strong><span>Skipped</span></div>
      </div>
      <div class="muted" style="margin-bottom:8px;">Pass rate</div>
      <div class="progress" aria-label="Pass rate progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $counts['passRate']); ?>%"></div></div>
      <div class="muted" style="margin-top:8px; margin-bottom:14px;"><?php echo htmlspecialchars((string) $counts['passRate']); ?>% passed, latest generated <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.</div>
      <div class="inline-links"><a class="textlink" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a></div>
    <?php else: ?>
      <div class="empty-state" style="box-shadow:none;">
        <strong>No published report yet.</strong>
        <p class="muted">Once SecureIT publishes a latest summary for this tenant, the posture snapshot will appear here.</p>
      </div>
    <?php endif; ?>
  </article>
</section>

<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Functional area coverage</h2>
      <div class="muted"></div>
    </div>
  </div>
  <div class="feature-grid">
    <?php if (!$functionalAreas): ?>
      <article class="card feature-card">
        <h3>No functional area data yet</h3>
        <p>Run a tenant assessment with embedded summary persistence to populate canonical SecureIT controls and area scoring.</p>
      </article>
    <?php else: ?>
      <?php foreach ($functionalAreas as $area): ?>
        <?php $toneClass = 'tone-' . ($area['tone'] ?? 'neutral'); ?>
        <article class="card feature-card">
          <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge <?php echo htmlspecialchars($toneClass); ?>"><?php echo htmlspecialchars($area['status'] ?? 'No data'); ?></span><span class="badge tone-neutral"><?php echo ($area['score'] !== null) ? 'Score: ' . htmlspecialchars((string) $area['score']) . '%' : 'Score unavailable'; ?></span></div>
          <h3><?php echo htmlspecialchars($area['name'] ?? 'Functional area'); ?></h3>
          <p class="muted" style="margin-bottom:10px;">Passing controls: <?php echo htmlspecialchars((string) ($area['controlsPassing'] ?? 0)); ?>, partially met: <?php echo htmlspecialchars((string) ($area['controlsPartial'] ?? 0)); ?>, not yet covered by current mapping: <?php echo htmlspecialchars((string) ($area['controlsUnmapped'] ?? 0)); ?>, total controls assessed: <?php echo htmlspecialchars((string) ($area['controlsTotal'] ?? 0)); ?>.</p>
          <?php if (!empty($area['controls'])): ?>
            <div class="muted" style="font-size:0.92rem; margin-bottom:8px;">Sample control evidence from the latest assessment:</div>
            <div class="kv" style="gap:8px;">
              <?php foreach (array_slice($area['controls'], 0, 3) as $control): ?>
                <div class="kv-row" style="grid-template-columns: 1fr; padding-bottom:8px;">
                  <div class="kv-value"><strong><?php echo htmlspecialchars($control['title'] ?? $control['id'] ?? 'Control'); ?></strong></div>
                  <div class="muted" style="font-size:0.92rem;"><?php echo htmlspecialchars(ucfirst($control['status'] ?? 'unknown')); ?>, matched checks: <?php echo htmlspecialchars(implode(', ', $control['matchedIds'] ?? [])); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Run history</h2>
      <div class="muted">Historical published reports for trend review and quick drill-down.</div>
    </div>
    <div class="muted"><?php echo htmlspecialchars((string) count($history)); ?> historical run<?php echo count($history) === 1 ? '' : 's'; ?></div>
  </div>

  <article class="card panel">
    <?php if (!$history): ?>
      <div class="empty-state" style="box-shadow:none;">
        <strong>No historical reports found.</strong>
        <p class="muted">Run history will appear here as SecureIT publishes archived summaries.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Generated</th>
              <th>Total</th>
              <th>Passed</th>
              <th>Failed</th>
              <th>Skipped</th>
              <th>Status</th>
              <th>Report</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $item): ?>
              <?php
                $s = $item['summary'] ?? [];
                $rowCounts = secureit_summary_counts($s);
                $rowToneClass = 'tone-' . strtolower($rowCounts['riskTone']);
              ?>
              <tr>
                <td><?php echo htmlspecialchars(secureit_format_datetime($s['generatedAt'] ?? null)); ?></td>
                <td><?php echo htmlspecialchars((string) $rowCounts['total']); ?></td>
                <td><?php echo htmlspecialchars((string) $rowCounts['passed']); ?></td>
                <td><?php echo htmlspecialchars((string) $rowCounts['failed']); ?></td>
                <td><?php echo htmlspecialchars((string) $rowCounts['skipped']); ?></td>
                <td><span class="badge <?php echo htmlspecialchars($rowToneClass); ?>"><?php echo htmlspecialchars($rowCounts['riskLevel']); ?></span></td>
                <td><a class="textlink" href="<?php echo htmlspecialchars($item['reportPath']); ?>">Open report</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>
</section>
<?php
$content = ob_get_clean();
$heroActions = [];
if ($summary) {
    $heroActions[] = ['href' => $tenantKey . '/latest/index.html', 'label' => 'Open latest report'];
}
secureit_render_shell(($tenant['name'] ?? $tenantKey) . ' - ' . $app['app_name'], $content, [
    'pageTitle' => $tenant['name'] ?? $tenantKey,
    'pageIntro' => 'Customer SecureIT tenant view with posture summary, functional coverage areas, and published report history.',
    'backHref' => 'portal.php',
    'backLabel' => 'Back to customer portal',
    'eyebrow' => '',
    'heroActions' => $heroActions,
    'navLinks' => [],
    'headerMenu' => [
        ['href' => 'dashboard.php', 'label' => 'ICT365 admin dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
    ],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'portal.php', 'label' => 'Customer portal'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Employee portal'],
        ['href' => 'tenant.php?tenant=' . rawurlencode($tenantKey), 'label' => 'Current tenant'],
        ['href' => 'admin.php', 'label' => 'Admin'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
