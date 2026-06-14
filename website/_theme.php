<?php
function secureit_summary_counts(?array $summary): array {
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

function secureit_format_datetime(?string $value): string {
    if (!$value) {
        return 'Unknown';
    }

    try {
        return (new DateTimeImmutable($value))->format('j M Y, H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function secureit_dashboard_stats(array $tenants, callable $summaryResolver): array {
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

        $summary = $summaryResolver($tenantKey);
        if (!$summary) {
            continue;
        }

        $stats['reportingCount']++;
        $counts = secureit_summary_counts($summary);
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

function secureit_render_layout(string $title, string $pageTitle, string $pageIntro, string $content, array $options = []): void {
    $backHref = $options['backHref'] ?? null;
    $backLabel = $options['backLabel'] ?? 'Back';
    $heroBadges = $options['heroBadges'] ?? [];
    $heroActions = $options['heroActions'] ?? [];
    $eyebrow = $options['eyebrow'] ?? 'ICT365 SecureIT';
    $navLinks = $options['navLinks'] ?? [];
    $navCta = $options['navCta'] ?? null;
    $footerLinks = $options['footerLinks'] ?? [];
    $footerSecondaryLinks = $options['footerSecondaryLinks'] ?? [];
    $footerContact = $options['footerContact'] ?? [];
    $heroIntroMaxWidth = $options['heroIntroMaxWidth'] ?? '760px';
    $hideHeroChrome = (bool) ($options['hideHeroChrome'] ?? false);
    $headerMenu = $options['headerMenu'] ?? [];
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title); ?></title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f8fbfb;
      --surface: #ffffff;
      --surface-muted: #f4f8f8;
      --surface-soft: #eef6f6;
      --text: #173530;
      --muted: #5f7874;
      --line: rgba(0, 99, 95, 0.12);
      --shadow: 0 18px 44px rgba(10, 61, 50, 0.09);
      --brand: #00635f;
      --brand-strong: #004f4c;
      --brand-accent: #339997;
      --eden: #0a3d32;
      --good: #0c7b57;
      --good-bg: #e8f8f1;
      --warn: #a46212;
      --warn-bg: #fff6e9;
      --bad: #af4d1a;
      --bad-bg: #fff1eb;
      --neutral: #46655f;
      --neutral-bg: #edf3f2;
      --radius-xl: 28px;
      --radius-lg: 20px;
      --radius-md: 14px;
      --radius-sm: 999px;
      --footer-bg: #111827;
      --footer-line: rgba(255,255,255,0.08);
      --footer-text: #d1d5db;
      --footer-muted: #9ca3af;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
      background: linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
      min-height: 100vh;
    }
    a { color: inherit; }
    .container {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
    }
    .site-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(0, 99, 95, 0.08);
      box-shadow: 0 8px 28px rgba(10, 61, 50, 0.05);
    }
    .nav-wrap {
      min-height: 80px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
    }
    .brand-link {
      display: inline-flex;
      align-items: center;
      text-decoration: none;
      flex-shrink: 0;
    }
    .brand-link img {
      display: block;
      height: 56px;
      width: auto;
    }
    .main-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 28px;
      flex: 1;
    }
    .nav-links {
      display: flex;
      align-items: center;
      gap: 28px;
      flex-wrap: wrap;
    }
    .nav-link {
      color: #495f5b;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.97rem;
    }
    .nav-link:hover { color: var(--eden); }
    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: auto;
    }
    .menu-dropdown {
      position: relative;
    }
    .menu-trigger {
      min-width: 46px;
      width: 46px;
      padding: 0;
      border-radius: 12px;
      background: #ffffff;
      color: var(--brand-strong);
      border: 1px solid rgba(0, 99, 95, 0.12);
      box-shadow: 0 8px 24px rgba(10, 61, 50, 0.08);
    }
    .menu-trigger:hover {
      background: #f4fbfb;
      box-shadow: 0 12px 26px rgba(10, 61, 50, 0.12);
    }
    .menu-panel {
      position: absolute;
      right: 0;
      top: calc(100% + 10px);
      min-width: 250px;
      background: #fff;
      border: 1px solid rgba(0, 99, 95, 0.12);
      border-radius: 18px;
      box-shadow: 0 18px 44px rgba(10, 61, 50, 0.14);
      padding: 10px;
      display: none;
      z-index: 60;
    }
    .menu-dropdown:hover .menu-panel,
    .menu-dropdown:focus-within .menu-panel {
      display: block;
    }
    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      text-decoration: none;
      color: var(--text);
      font-weight: 600;
    }
    .menu-item:hover {
      background: #f4fbfb;
      color: var(--eden);
    }
    .button,
    button {
      appearance: none;
      border: 0;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      min-height: 46px;
      padding: 0 20px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.95rem;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, color 120ms ease;
      background: var(--brand-accent);
      color: #fff;
      box-shadow: 0 10px 24px rgba(51, 153, 151, 0.28);
    }
    .button:hover,
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 28px rgba(51, 153, 151, 0.32);
    }
    .button-ghost {
      background: rgba(255,255,255,0.18);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.2);
      box-shadow: none;
    }
    .hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, rgba(0,99,95,0.92) 0%, rgba(0,99,95,0.90) 45%, rgba(51,153,151,0.85) 100%), url('https://ict365.ky/images/hero/hero-bg.jpg') center/cover no-repeat;
      color: #fff;
    }
    .hero::before,
    .hero::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      pointer-events: none;
    }
    .hero::before {
      width: 360px;
      height: 360px;
      top: -110px;
      left: 12%;
      background: rgba(255,255,255,0.12);
      filter: blur(60px);
    }
    .hero::after {
      width: 320px;
      height: 320px;
      bottom: -140px;
      right: 10%;
      background: rgba(10,61,50,0.20);
      filter: blur(64px);
    }
    .hero-inner {
      position: relative;
      z-index: 1;
      padding: 78px 0 92px;
      text-align: center;
    }
    .hero-logo {
      display: none;
    }
    .eyebrow,
    .hero-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: var(--radius-sm);
      background: rgba(255,255,255,0.14);
      color: rgba(255,255,255,0.95);
      font-size: 0.86rem;
      border: 1px solid rgba(255,255,255,0.16);
      backdrop-filter: blur(8px);
    }
    .eyebrow { margin-bottom: 18px; }
    h1, h2, h3, p { margin-top: 0; }
    .hero h1 {
      margin: 0 auto 16px;
      max-width: 1280px;
      font-size: clamp(1.8rem, 3vw, 3.2rem);
      line-height: 1.08;
      letter-spacing: -0.03em;
      font-weight: 700;
      color: #ffffff;
      text-wrap: balance;
    }
    .hero p {
      margin: 0 auto;
      max-width: 760px;
      color: rgba(219, 234, 254, 0.98);
      font-size: 1.25rem;
      line-height: 1.75;
      font-weight: 400;
    }
    .hero-pill-row,
    .hero-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 26px;
    }
    .section {
      padding: 72px 0;
    }
    .section.alt {
      background: var(--surface-soft);
    }
    .section-heading {
      text-align: center;
      margin-bottom: 48px;
    }
    .section-kicker {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 14px;
      border-radius: var(--radius-sm);
      background: var(--eden);
      color: #fff;
      font-size: 0.84rem;
      font-weight: 700;
      margin-bottom: 18px;
    }
    .section-title {
      font-size: clamp(2rem, 3.4vw, 3rem);
      line-height: 1.08;
      letter-spacing: -0.03em;
      margin-bottom: 14px;
      color: #111827;
    }
    .section-intro,
    .muted {
      color: var(--muted);
      line-height: 1.72;
      font-size: 1rem;
    }
    .section-intro {
      max-width: 760px;
      margin: 0 auto;
    }
    .split {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
      gap: 28px;
      align-items: stretch;
    }
    .card,
    .panel,
    .feature-card,
    .journey-card,
    .metric-card,
    .partner-card,
    .footer-card {
      background: var(--surface);
      border-radius: 24px;
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
    }
    .panel,
    .feature-card,
    .journey-card,
    .partner-card,
    .footer-card {
      padding: 28px;
    }
    .feature-grid,
    .journey-grid,
    .partner-grid,
    .footer-grid {
      display: grid;
      gap: 24px;
    }
    .feature-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .journey-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .partner-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tenant-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
    .metrics-strip {
      padding: 26px 0;
      border-top: 1px solid rgba(0,99,95,0.08);
      border-bottom: 1px solid rgba(0,99,95,0.08);
      background: #fff;
    }
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 20px;
    }
    .metric-stat,
    .metric-card {
      min-width: 0;
    }
    .metric-card {
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 10px;
      min-height: 180px;
    }
    .metric-stat {
      text-align: center;
    }
    .metric-value {
      font-size: clamp(2rem, 3vw, 3rem);
      line-height: 1.05;
      font-weight: 800;
      margin-bottom: 8px;
      color: var(--brand);
      overflow-wrap: anywhere;
    }
    .metric-label,
    .metric-note {
      color: var(--muted);
      font-weight: 600;
      line-height: 1.55;
      overflow-wrap: anywhere;
    }
    .feature-icon,
    .journey-step {
      width: 56px;
      height: 56px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(0,99,95,0.10) 0%, rgba(51,153,151,0.18) 100%);
      color: var(--brand-strong);
      font-size: 1.4rem;
      font-weight: 800;
      border: 1px solid rgba(0,99,95,0.10);
      margin-bottom: 18px;
    }
    .feature-card h3,
    .journey-card h3,
    .partner-card h3 {
      margin-bottom: 10px;
      font-size: 1.14rem;
      color: var(--eden);
    }
    .feature-card p,
    .journey-card p,
    .partner-card p {
      margin-bottom: 0;
      color: var(--muted);
      line-height: 1.72;
    }
    .empty-state,
    .error,
    .success {
      padding: 18px 20px;
      border-radius: 16px;
      border: 1px solid;
    }
    .empty-state {
      background: linear-gradient(180deg, #f3fbfa 0%, #edf7f6 100%);
      border-color: rgba(0, 99, 95, 0.16);
      color: var(--eden);
    }
    .error {
      background: #fff3ef;
      border-color: #f3c8b7;
      color: #9b3f17;
    }
    .success {
      background: #edf9f5;
      border-color: #b9ead5;
      color: #136045;
    }
    label {
      display: block;
      margin-top: 1rem;
      font-weight: 700;
      color: var(--eden);
    }
    input,
    select {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      padding: 0.85rem 0.95rem;
      margin-top: 0.4rem;
      border: 1px solid #cfe2df;
      border-radius: 12px;
      background: #fff;
      color: var(--text);
      font: inherit;
    }
    input:focus,
    select:focus {
      outline: none;
      border-color: var(--brand-accent);
      box-shadow: 0 0 0 4px rgba(51, 153, 151, 0.16);
    }
    .field-note {
      margin-top: 8px;
      color: var(--muted);
      font-size: 0.93rem;
      line-height: 1.6;
    }
    .site-footer {
      background: var(--footer-bg);
      color: var(--footer-text);
      margin-top: 32px;
    }
    .site-footer a { text-decoration: none; }
    .site-footer a:hover { color: #d8fffd; }
    .footer-main {
      padding: 60px 0 54px;
    }
    .footer-grid {
      grid-template-columns: 1.7fr 1fr 1fr;
    }
    .footer-logo {
      display: inline-flex;
      align-items: center;
      margin-bottom: 18px;
    }
    .footer-logo img {
      height: 46px;
      width: auto;
      filter: brightness(1.15);
    }
    .footer-title {
      color: #fff;
      font-size: 1.08rem;
      font-weight: 700;
      margin-bottom: 18px;
    }
    .footer-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 12px;
    }
    .footer-copy {
      color: var(--footer-muted);
      line-height: 1.75;
      max-width: 520px;
    }
    .footer-bottom {
      border-top: 1px solid var(--footer-line);
      padding: 22px 0;
      color: var(--footer-muted);
      font-size: 0.92rem;
    }
    .footer-bottom-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      flex-wrap: wrap;
    }
    .footer-bottom-links {
      display: flex;
      gap: 18px;
      flex-wrap: wrap;
    }
    .tone-good { color: var(--good); background: var(--good-bg); }
    .tone-warn { color: var(--warn); background: var(--warn-bg); }
    .tone-bad { color: var(--bad); background: var(--bad-bg); }
    .tone-neutral { color: var(--neutral); background: var(--neutral-bg); }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.86rem;
      font-weight: 800;
      white-space: nowrap;
    }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }
    .stat-chip {
      background: var(--surface-muted);
      border-radius: 14px;
      padding: 12px;
      min-height: 78px;
      border: 1px solid rgba(0, 99, 95, 0.08);
    }
    .stat-chip strong {
      display: block;
      font-size: 1.1rem;
      margin-bottom: 5px;
      color: var(--eden);
    }
    .progress {
      height: 10px;
      border-radius: 999px;
      background: #d8eceb;
      overflow: hidden;
    }
    .progress-bar {
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, var(--brand) 0%, var(--brand-accent) 100%);
    }
    .textlink {
      color: var(--brand);
      text-decoration: none;
      font-weight: 700;
    }
    .textlink:hover { text-decoration: underline; }
    .table-wrap {
      overflow-x: auto;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: var(--surface);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--surface);
    }
    th, td {
      text-align: left;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      font-size: 0.95rem;
      vertical-align: middle;
    }
    th {
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      background: #f5fbfb;
    }
    tr:last-child td { border-bottom: 0; }
    .kv { display: grid; gap: 12px; }
    .kv-row {
      display: grid;
      grid-template-columns: 160px 1fr;
      gap: 10px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--line);
    }
    .kv-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .kv-value { word-break: break-word; }
    .tenant-card {
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 18px;
      min-width: 0;
    }
    .tenant-meta {
      display: grid;
      gap: 6px;
      color: var(--muted);
      font-size: 0.94rem;
      overflow-wrap: anywhere;
    }
    .tenant-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
    }
    .inline-links {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      align-items: center;
    }
    .lock-row {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    .lock-row input { margin-top: 0.4rem; }
    pre {
      background: #153a35;
      color: #eef9f7;
      padding: 1rem;
      border-radius: 14px;
      overflow: auto;
      border: 1px solid rgba(255,255,255,0.08);
    }
    @media (max-width: 980px) {
      .main-nav,
      .nav-wrap,
      .split,
      .footer-grid {
        grid-template-columns: 1fr;
      }
      .nav-wrap,
      .main-nav {
        flex-direction: column;
        align-items: flex-start;
      }
      .main-nav { width: 100%; }
      .header-actions { margin-left: 0; }
      .feature-grid,
      .journey-grid,
      .partner-grid,
      .metrics-grid,
      .stats-row,
      .tenant-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .kv-row { grid-template-columns: 1fr; gap: 6px; }
      .hero-inner { padding: 62px 0 74px; }
    }
    @media (max-width: 640px) {
      .container { width: min(100% - 20px, 1180px); }
      .nav-links,
      .footer-bottom-links {
        gap: 14px;
      }
      .hero-logo img { height: 54px; }
      .feature-grid,
      .journey-grid,
      .partner-grid,
      .metrics-grid,
      .stats-row,
      .tenant-grid,
      .footer-grid {
        grid-template-columns: 1fr;
      }
      .section { padding: 54px 0; }
      .panel,
      .feature-card,
      .journey-card,
      .partner-card,
      .footer-card { padding: 22px; }
      th, td { padding: 12px; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container">
      <div class="nav-wrap">
        <a class="brand-link" href="index.php">
          <img src="ICT365-logo-1.0.png" alt="ICT365">
        </a>
        <div class="main-nav">
          <nav class="nav-links" aria-label="Primary navigation">
            <?php foreach ($navLinks as $link): ?>
              <a class="nav-link" href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
            <?php endforeach; ?>
          </nav>
          <div class="header-actions">
            <?php if ($navCta): ?>
              <a class="button" href="<?php echo htmlspecialchars($navCta['href']); ?>"><?php echo htmlspecialchars($navCta['label']); ?></a>
            <?php endif; ?>
            <?php if ($headerMenu): ?>
              <div class="menu-dropdown">
                <button type="button" class="menu-trigger" aria-label="Open menu">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 12H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  </svg>
                </button>
                <div class="menu-panel">
                  <?php foreach ($headerMenu as $item): ?>
                    <a class="menu-item" href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <section class="hero">
    <div class="container hero-inner">
      <div class="hero-logo"></div>
      <?php if (!$hideHeroChrome && $eyebrow !== ''): ?>
        <div class="eyebrow"><?php echo htmlspecialchars($eyebrow); ?></div>
      <?php endif; ?>
      <?php if (!$hideHeroChrome && $backHref): ?>
        <div style="margin-bottom:16px;">
          <a class="button button-ghost" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo htmlspecialchars($backLabel); ?></a>
        </div>
      <?php endif; ?>
      <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
      <p style="max-width: <?php echo htmlspecialchars($heroIntroMaxWidth); ?>;"><?php echo htmlspecialchars($pageIntro); ?></p>
      <?php if (!$hideHeroChrome && $heroBadges): ?>
        <div class="hero-pill-row">
          <?php foreach ($heroBadges as $badge): ?>
            <div class="hero-pill"><?php echo htmlspecialchars($badge); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!$hideHeroChrome && $heroActions): ?>
        <div class="hero-actions">
          <?php foreach ($heroActions as $action): ?>
            <a class="<?php echo htmlspecialchars($action['class'] ?? 'button'); ?>" href="<?php echo htmlspecialchars($action['href']); ?>"><?php echo htmlspecialchars($action['label']); ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php echo $content; ?>

  <footer class="site-footer">
    <div class="container footer-main">
      <div class="footer-grid">
        <div>
          <div class="footer-logo">
            <img src="ICT365-logo-1.0.png" alt="ICT365">
          </div>
          <p class="footer-copy">Delivering IT solutions across the Caribbean, now with a cleaner SecureIT portal experience for managed Microsoft 365 security reporting and customer-facing posture reviews.</p>
        </div>
        <div>
          <div class="footer-title">Quick links</div>
          <ul class="footer-list">
            <?php foreach ($footerLinks as $link): ?>
              <li><a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <div class="footer-title">Contact</div>
          <ul class="footer-list">
            <?php foreach ($footerContact as $item): ?>
              <li>
                <?php if (!empty($item['href'])): ?>
                  <a href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
                <?php else: ?>
                  <?php echo htmlspecialchars($item['label']); ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container footer-bottom-row">
        <div>© <?php echo date('Y'); ?> ICT365. All rights reserved.</div>
        <div class="footer-bottom-links">
          <?php foreach ($footerSecondaryLinks as $link): ?>
            <a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </footer>
</body>
</html>
<?php
}
