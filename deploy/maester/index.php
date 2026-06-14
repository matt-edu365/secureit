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

function summary_counts(?array $summary): array {
    $total = (int) ($summary['total'] ?? 0);
    $passed = (int) ($summary['passed'] ?? 0);
    $failed = (int) ($summary['failed'] ?? 0);
    $skipped = (int) ($summary['skipped'] ?? 0);
    $passRate = $total > 0 ? (int) round(($passed / $total) * 100) : 0;
    $riskLevel = 'No data';
    $riskTone = 'neutral';

    if ($total > 0) {
        if ($failed === 0) {
            $riskLevel = 'Healthy';
            $riskTone = 'good';
        } elseif ($failed <= 3) {
            $riskLevel = 'Watch';
            $riskTone = 'warn';
        } else {
            $riskLevel = 'Needs attention';
            $riskTone = 'bad';
        }
    }

    return [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'skipped' => $skipped,
        'passRate' => $passRate,
        'riskLevel' => $riskLevel,
        'riskTone' => $riskTone,
    ];
}

function format_datetime(?string $value): string {
    if (!$value) {
        return 'Unknown';
    }

    try {
        return (new DateTimeImmutable($value))->format('j M Y, H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function dashboard_stats(array $tenants): array {
    $stats = [
        'tenantCount' => count($tenants),
        'reportingCount' => 0,
        'healthyCount' => 0,
        'attentionCount' => 0,
        'latestGeneratedAt' => null,
    ];

    foreach ($tenants as $tenant) {
        $tenantKey = $tenant['id'] ?? '';
        if ($tenantKey === '') {
            continue;
        }

        $summary = tenant_summary($tenantKey);
        if (!$summary) {
            continue;
        }

        $stats['reportingCount']++;
        $counts = summary_counts($summary);
        if ($counts['riskTone'] === 'good') {
            $stats['healthyCount']++;
        }
        if ($counts['riskTone'] === 'bad') {
            $stats['attentionCount']++;
        }

        $generatedAt = $summary['generatedAt'] ?? null;
        if ($generatedAt && ($stats['latestGeneratedAt'] === null || strcmp($generatedAt, $stats['latestGeneratedAt']) > 0)) {
            $stats['latestGeneratedAt'] = $generatedAt;
        }
    }

    return $stats;
}

$dashboard = dashboard_stats($tenants);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SecureIT Dashboard</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f3f7fb;
      --surface: rgba(255, 255, 255, 0.9);
      --surface-strong: #ffffff;
      --surface-muted: #eef4fb;
      --text: #0f172a;
      --muted: #5b6b81;
      --line: rgba(148, 163, 184, 0.22);
      --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      --brand: #0f4e84;
      --good: #0f9f6e;
      --good-bg: #eafaf3;
      --warn: #d97706;
      --warn-bg: #fff6e8;
      --bad: #c2410c;
      --bad-bg: #fff1eb;
      --neutral: #475569;
      --neutral-bg: #eef2f7;
      --radius-xl: 24px;
      --radius-lg: 18px;
      --radius-sm: 999px;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(1, 162, 223, 0.10), transparent 32%),
        radial-gradient(circle at top right, rgba(15, 78, 132, 0.12), transparent 28%),
        linear-gradient(180deg, #f8fbfe 0%, var(--bg) 100%);
      min-height: 100vh;
    }
    a { color: inherit; }
    .app-shell { width: min(1200px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 48px; }
    .hero {
      background: linear-gradient(135deg, #0d3f6c 0%, #0f4e84 42%, #01a2df 100%);
      color: white;
      border-radius: var(--radius-xl);
      padding: 30px;
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
      margin-bottom: 24px;
    }
    .hero-row { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; flex-wrap: wrap; position: relative; z-index: 1; }
    .eyebrow, .pill {
      display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: var(--radius-sm);
      background: rgba(255,255,255,0.14); color: rgba(255,255,255,0.92); font-size: 0.86rem;
    }
    .pill-row, .hero-actions, .inline-links { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .pill-row { margin-top: 14px; }
    h1, h2, h3, p { margin-top: 0; }
    .hero h1 { margin-bottom: 10px; font-size: clamp(2rem, 4vw, 3rem); line-height: 1.02; }
    .hero p { margin-bottom: 0; max-width: 760px; color: rgba(255,255,255,0.86); line-height: 1.6; }
    .button, .textlink {
      display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-weight: 600;
    }
    .button {
      padding: 12px 16px; border-radius: 12px; background: white; color: var(--brand); box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }
    .textlink { color: var(--brand); }
    .textlink:hover { text-decoration: underline; }
    .section { margin-top: 22px; }
    .section-header { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:14px; flex-wrap:wrap; }
    .section-title { margin-bottom: 4px; font-size: 1.2rem; }
    .muted { color: var(--muted); }
    .metrics-grid, .tenant-grid { display:grid; gap:16px; }
    .metrics-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 18px; }
    .tenant-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-lg); box-shadow: var(--shadow); }
    .metric-card, .tenant-card { padding: 20px; }
    .metric-label, .tenant-meta, .metric-note { color: var(--muted); }
    .metric-value { font-size: 2rem; font-weight: 700; line-height: 1; margin: 10px 0; }
    .tenant-card { display:flex; flex-direction:column; gap:16px; }
    .tenant-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .tenant-meta { display:grid; gap:6px; font-size:0.93rem; }
    .badge {
      display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius: var(--radius-sm); font-size:0.86rem; font-weight:700; white-space:nowrap;
    }
    .tone-good { color: var(--good); background: var(--good-bg); }
    .tone-warn { color: var(--warn); background: var(--warn-bg); }
    .tone-bad { color: var(--bad); background: var(--bad-bg); }
    .tone-neutral { color: var(--neutral); background: var(--neutral-bg); }
    .stats-row { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; }
    .stat-chip { background: var(--surface-muted); border-radius: 14px; padding: 12px; min-height: 78px; }
    .stat-chip strong { display:block; font-size:1.1rem; margin-bottom:5px; }
    .stat-chip span { color: var(--muted); font-size:0.85rem; }
    .progress { height:10px; border-radius:999px; background:#dbe7f3; overflow:hidden; }
    .progress-bar { height:100%; border-radius:inherit; background: linear-gradient(90deg, #0f4e84 0%, #01a2df 100%); }
    .empty-state {
      padding: 22px; background: linear-gradient(180deg, #fffaf2 0%, #fff6ea 100%); border: 1px solid #f6d7a6; border-radius: var(--radius-lg); color: #854d0e; box-shadow: var(--shadow);
    }
    @media (max-width: 860px) { .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) {
      .app-shell { width: min(100% - 20px, 1200px); padding-top: 18px; }
      .hero { padding: 22px; border-radius: 20px; }
      .stats-row, .metrics-grid, .tenant-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <main class="app-shell">
    <section class="hero card">
      <div class="hero-row">
        <div>
          <div class="eyebrow">SecureIT multi-tenant security reporting</div>
          <h1>SecureIT Dashboard</h1>
          <p>Keep track of tenant posture, latest published reports, and who needs attention first, without digging through raw report folders.</p>
          <div class="pill-row">
            <div class="pill">Configured tenants: <?php echo htmlspecialchars((string) $dashboard['tenantCount']); ?></div>
            <div class="pill">Latest activity: <?php echo htmlspecialchars(format_datetime($dashboard['latestGeneratedAt'])); ?></div>
          </div>
        </div>
        <div class="hero-actions">
          <a class="button" href="admin.php">Admin actions</a>
          <a class="button" href="onboard.php">Customer onboarding</a>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="metrics-grid">
        <article class="card metric-card"><div class="metric-label">Tenants</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['tenantCount']); ?></div><div class="metric-note">Total tenants configured in this prototype.</div></article>
        <article class="card metric-card"><div class="metric-label">Reporting</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['reportingCount']); ?></div><div class="metric-note">Tenants with a latest summary available.</div></article>
        <article class="card metric-card"><div class="metric-label">Healthy</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['healthyCount']); ?></div><div class="metric-note">Tenants with no current failed checks.</div></article>
        <article class="card metric-card"><div class="metric-label">Needs attention</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['attentionCount']); ?></div><div class="metric-note">Tenants with multiple failed checks in the latest run.</div></article>
      </div>
    </section>

    <section class="section">
      <div class="section-header">
        <div>
          <h2 class="section-title">Tenant overview</h2>
          <div class="muted">A cleaner operational view across every customer, with latest posture and quick report access.</div>
        </div>
      </div>

      <?php if (!$tenants): ?>
        <div class="empty-state">
          <strong>No tenants configured yet.</strong>
          <p class="muted">Use the onboarding flow to add your first tenant, then published reports will start appearing here.</p>
        </div>
      <?php else: ?>
        <div class="tenant-grid">
          <?php foreach ($tenants as $tenant): ?>
            <?php
              $tenantKey = $tenant['id'] ?? 'unknown';
              $summary = tenant_summary($tenantKey);
              $counts = summary_counts($summary);
              $toneClass = 'tone-' . strtolower($counts['riskTone']);
            ?>
            <article class="card tenant-card">
              <div class="tenant-head">
                <div>
                  <h3 style="margin-bottom:6px;"><?php echo htmlspecialchars($tenant['name'] ?? $tenantKey); ?></h3>
                  <div class="tenant-meta">
                    <div>Tenant key: <?php echo htmlspecialchars($tenantKey); ?></div>
                    <div>Tenant ID: <?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div>
                    <div>Report URL: <?php echo htmlspecialchars($tenant['reportBaseUrl'] ?? 'Unknown'); ?></div>
                  </div>
                </div>
                <div class="badge <?php echo htmlspecialchars($toneClass); ?>"><?php echo htmlspecialchars($counts['riskLevel']); ?></div>
              </div>

              <?php if ($summary): ?>
                <div>
                  <div class="muted" style="margin-bottom:8px;">Pass rate</div>
                  <div class="progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $counts['passRate']); ?>%"></div></div>
                  <div class="muted" style="margin-top:8px;"><?php echo htmlspecialchars((string) $counts['passRate']); ?>% of checks passed in the latest published run.</div>
                </div>
                <div class="stats-row">
                  <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['total']); ?></strong><span>Total checks</span></div>
                  <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['passed']); ?></strong><span>Passed</span></div>
                  <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['failed']); ?></strong><span>Failed</span></div>
                  <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['skipped']); ?></strong><span>Skipped</span></div>
                </div>
                <div class="inline-links">
                  <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">Open tenant page</a>
                  <a class="textlink" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a>
                </div>
                <div class="muted">Last generated: <?php echo htmlspecialchars(format_datetime($summary['generatedAt'] ?? null)); ?></div>
              <?php else: ?>
                <div class="empty-state" style="padding:16px; box-shadow:none;">
                  <strong>No published report yet.</strong>
                  <p class="muted">This tenant is configured, but no latest summary has been published yet.</p>
                </div>
                <div class="inline-links">
                  <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">Open tenant page</a>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
