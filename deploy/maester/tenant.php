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

$summaryPath = __DIR__ . '/' . $tenantKey . '/latest/summary.json';
$summary = file_exists($summaryPath) ? json_decode(file_get_contents($summaryPath), true) : null;
$counts = summary_counts($summary);
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($tenant['name']); ?> - SecureIT</title>
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
      margin-bottom: 24px;
    }
    .hero-row, .section-header, .inline-links { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start; }
    .eyebrow, .pill, .badge {
      display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius: var(--radius-sm); font-size:0.86rem;
    }
    .eyebrow, .pill { background: rgba(255,255,255,0.14); color: rgba(255,255,255,0.92); }
    .badge { font-weight:700; white-space:nowrap; }
    .tone-good { color: var(--good); background: var(--good-bg); }
    .tone-warn { color: var(--warn); background: var(--warn-bg); }
    .tone-bad { color: var(--bad); background: var(--bad-bg); }
    .tone-neutral { color: var(--neutral); background: var(--neutral-bg); }
    .pill-row, .hero-actions, .inline-links { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .pill-row { margin-top:14px; }
    h1, h2, p { margin-top:0; }
    .hero h1 { margin-bottom:10px; font-size: clamp(2rem, 4vw, 3rem); line-height: 1.02; }
    .hero p { margin-bottom:0; max-width:760px; color: rgba(255,255,255,0.86); line-height:1.6; }
    .button, .textlink { text-decoration:none; font-weight:600; }
    .button { display:inline-flex; padding:12px 16px; border-radius:12px; background:white; color:var(--brand); box-shadow:0 10px 30px rgba(0,0,0,0.12); }
    .textlink { color: var(--brand); }
    .textlink:hover { text-decoration: underline; }
    .section { margin-top:22px; }
    .section-title { margin-bottom:4px; font-size:1.2rem; }
    .muted { color: var(--muted); }
    .card { background: var(--surface); border:1px solid var(--line); border-radius: var(--radius-lg); box-shadow: var(--shadow); }
    .panel { padding:20px; }
    .split { display:grid; grid-template-columns:minmax(0, 1.4fr) minmax(280px, 0.8fr); gap:16px; }
    .kv { display:grid; gap:12px; }
    .kv-row { display:grid; grid-template-columns:150px 1fr; gap:10px; padding-bottom:12px; border-bottom:1px solid var(--line); }
    .kv-row:last-child { border-bottom:0; padding-bottom:0; }
    .kv-label { color: var(--muted); font-size:0.92rem; }
    .kv-value { word-break: break-word; }
    .stats-row { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; margin-bottom:14px; }
    .stat-chip { background: var(--surface-muted); border-radius:14px; padding:12px; min-height:78px; }
    .stat-chip strong { display:block; font-size:1.1rem; margin-bottom:5px; }
    .stat-chip span { color: var(--muted); font-size:0.85rem; }
    .progress { height:10px; border-radius:999px; background:#dbe7f3; overflow:hidden; }
    .progress-bar { height:100%; border-radius:inherit; background: linear-gradient(90deg, #0f4e84 0%, #01a2df 100%); }
    .table-wrap { overflow-x:auto; border-radius:16px; border:1px solid var(--line); background: var(--surface-strong); }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:14px 16px; border-bottom:1px solid var(--line); font-size:0.95rem; vertical-align:middle; }
    th { color: var(--muted); font-size:0.82rem; text-transform:uppercase; letter-spacing:0.04em; background:#f8fbff; }
    tr:last-child td { border-bottom:0; }
    .empty-state { padding:22px; background: linear-gradient(180deg, #fffaf2 0%, #fff6ea 100%); border:1px solid #f6d7a6; border-radius: var(--radius-lg); color:#854d0e; }
    @media (max-width: 860px) {
      .split { grid-template-columns:1fr; }
      .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .kv-row { grid-template-columns:1fr; gap:6px; }
    }
    @media (max-width: 640px) {
      .app-shell { width: min(100% - 20px, 1200px); padding-top:18px; }
      .hero { padding:22px; border-radius:20px; }
      .stats-row { grid-template-columns:1fr; }
      th, td { padding:12px; }
    }
  </style>
</head>
<body>
  <main class="app-shell">
    <section class="hero card">
      <div class="hero-row">
        <div>
          <div class="eyebrow"><a href="index.php" style="color:inherit; text-decoration:none;">← Back to dashboard</a></div>
          <h1><?php echo htmlspecialchars($tenant['name']); ?></h1>
          <p>SecureIT tenant view for latest posture, customer identifiers, and historical report access in one place.</p>
          <div class="pill-row">
            <div class="pill">Tenant key: <?php echo htmlspecialchars($tenant['id']); ?></div>
            <div class="pill">Tenant ID: <?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div>
          </div>
        </div>
        <div class="hero-actions">
          <?php if ($summary): ?><a class="button" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a><?php endif; ?>
          <div class="badge" style="background: rgba(255,255,255,0.14); color:white; border:1px solid rgba(255,255,255,0.18);"><?php echo htmlspecialchars($counts['riskLevel']); ?></div>
        </div>
      </div>
    </section>

    <section class="section split">
      <article class="card panel">
        <div class="section-header" style="margin-bottom:18px;">
          <div>
            <h2 class="section-title">Tenant details</h2>
            <div class="muted">Core identifiers and routing information for this customer.</div>
          </div>
        </div>
        <div class="kv">
          <div class="kv-row"><div class="kv-label">Tenant name</div><div class="kv-value"><?php echo htmlspecialchars($tenant['name']); ?></div></div>
          <div class="kv-row"><div class="kv-label">Tenant key</div><div class="kv-value"><?php echo htmlspecialchars($tenant['id']); ?></div></div>
          <div class="kv-row"><div class="kv-label">Tenant ID</div><div class="kv-value"><?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div></div>
          <div class="kv-row"><div class="kv-label">Client ID</div><div class="kv-value"><?php echo htmlspecialchars($tenant['clientId'] ?? 'Unknown'); ?></div></div>
          <div class="kv-row"><div class="kv-label">Report base URL</div><div class="kv-value"><?php echo htmlspecialchars($tenant['reportBaseUrl'] ?? 'Unknown'); ?></div></div>
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
          <div class="stats-row">
            <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['total']); ?></strong><span>Total</span></div>
            <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['passed']); ?></strong><span>Passed</span></div>
            <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['failed']); ?></strong><span>Failed</span></div>
            <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['skipped']); ?></strong><span>Skipped</span></div>
          </div>
          <div class="muted" style="margin-bottom:8px;">Pass rate</div>
          <div class="progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $counts['passRate']); ?>%"></div></div>
          <div class="muted" style="margin-top:8px; margin-bottom:14px;"><?php echo htmlspecialchars((string) $counts['passRate']); ?>% passed, latest generated <?php echo htmlspecialchars(format_datetime($summary['generatedAt'] ?? null)); ?>.</div>
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
          <h2 class="section-title">Run history</h2>
          <div class="muted">Historical published reports for trend review and quick drill-down.</div>
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
                    $rowCounts = summary_counts($s);
                    $rowToneClass = 'tone-' . strtolower($rowCounts['riskTone']);
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars(format_datetime($s['generatedAt'] ?? null)); ?></td>
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
  </main>
</body>
</html>
