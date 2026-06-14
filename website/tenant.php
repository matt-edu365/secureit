<?php
require __DIR__ . '/_theme.php';

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
    file_put_contents($tenantsPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

$summaryPath = __DIR__ . '/' . $tenantKey . '/latest/summary.json';
$summary = file_exists($summaryPath) ? json_decode(file_get_contents($summaryPath), true) : null;
$counts = secureit_summary_counts($summary);
$toneClass = 'tone-' . strtolower($counts['riskTone']);
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
      <div class="progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $counts['passRate']); ?>%"></div></div>
      <div class="muted" style="margin-top:8px; margin-bottom:14px;"><?php echo htmlspecialchars((string) $counts['passRate']); ?>% passed, latest generated <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.</div>
      <div class="inline-links"><a class="textlink" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a></div>
    <?php else: ?>
      <div class="empty-state">
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
  <div class="feature-grid" style="grid-template-columns:repeat(4, minmax(0, 1fr)); gap:20px;">
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-good">Healthy</span><span class="badge tone-neutral">Score placeholder: 82%</span></div>
      <h3>Identity &amp; Access Management</h3>
      <p>User identities, authentication, access policies, admin roles, guest access, security groups, and sign-in controls.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-good">Healthy</span><span class="badge tone-neutral">Score placeholder: 86%</span></div>
      <h3>Email &amp; Calendaring</h3>
      <p>Mailboxes, shared mailboxes, distribution lists, calendars, mail flow, anti-spam, anti-malware, retention, and email archiving.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-warn">Watch</span><span class="badge tone-neutral">Score placeholder: 74%</span></div>
      <h3>Collaboration &amp; Communication</h3>
      <p>Chat, meetings, calling, webinars, channels, team collaboration, internal communities, and real-time communication.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-good">Healthy</span><span class="badge tone-neutral">Score placeholder: 88%</span></div>
      <h3>Files, Intranet &amp; Content Management</h3>
      <p>Document libraries, intranet sites, file sharing, version control, metadata, document automation, records, and structured business lists.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-warn">Watch</span><span class="badge tone-neutral">Score placeholder: 71%</span></div>
      <h3>Endpoint &amp; Device Management</h3>
      <p>Device enrolment, compliance policies, app deployment, patching, mobile device management, security baselines, and BYOD controls.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-bad">Needs attention</span><span class="badge tone-neutral">Score placeholder: 63%</span></div>
      <h3>Security Operations &amp; Threat Protection</h3>
      <p>Threat protection across email, endpoints, identities, cloud apps, phishing, malware, incidents, alerts, investigation, and response.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-good">Healthy</span><span class="badge tone-neutral">Score placeholder: 84%</span></div>
      <h3>Compliance, Governance &amp; Data Protection</h3>
      <p>Sensitivity labels, data loss prevention, retention policies, legal hold, audit logs, compliance reporting, data governance, and risk management.</p>
    </article>
    <article class="card feature-card">
      <div class="inline-links" style="justify-content:space-between; margin-bottom:8px;"><span class="badge tone-warn">Watch</span><span class="badge tone-neutral">Score placeholder: 76%</span></div>
      <h3>Productivity, Automation &amp; AI</h3>
      <p>Day-to-day productivity, task management, forms, reporting, low-code apps, workflow automation, analytics, and AI-assisted work.</p>
    </article>
  </div>
</section>

<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Run history</h2>
      <div class="muted">Historical published reports for trend review and drill-down.</div>
    </div>
    <div class="muted"><?php echo htmlspecialchars((string) count($history)); ?> historical run<?php echo count($history) === 1 ? '' : 's'; ?></div>
  </div>
  <article class="card panel">
    <?php if (!$history): ?>
      <div class="empty-state">
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
secureit_render_layout(
    ($tenant['name'] ?? $tenantKey) . ' - SecureIT',
    $tenant['name'] ?? $tenantKey,
    '',
    $content,
    [
        'eyebrow' => '',
        'backHref' => null,
        'backLabel' => '',
        'heroBadges' => [],
        'heroActions' => $summary ? [
            ['href' => $tenantKey . '/latest/index.html', 'label' => 'Open latest report'],
        ] : [],
        'navLinks' => [],
    ]
);
