<?php
function secureit_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function secureit_load_tenants(): array {
    $config = secureit_config();
    $path = $config['tenants_file'];
    if (!file_exists($path)) {
        return ['tenants' => []];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : ['tenants' => []];
}

function secureit_save_tenants(array $data): void {
    $config = secureit_config();
    $path = $config['tenants_file'];
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function secureit_reports_root(): string {
    $config = secureit_config();
    $root = $config['reports_root'];
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }
    return $root;
}

function secureit_build_report_base_url(string $tenantKey): string {
    $config = secureit_config();
    return $config['base_url'] . '/' . rawurlencode(trim(strtolower($tenantKey)));
}

function secureit_valid_tenant_key(string $tenantKey): bool {
    return (bool) preg_match('/^[a-z0-9-]+$/', $tenantKey);
}

function secureit_tenant_exists(array $tenants, string $tenantKey): bool {
    foreach ($tenants as $tenant) {
        if (($tenant['id'] ?? '') === $tenantKey) {
            return true;
        }
    }
    return false;
}

function secureit_tenant_summary(string $tenantKey): ?array {
    $path = secureit_reports_root() . '/' . $tenantKey . '/latest/summary.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function secureit_find_tenant(string $tenantKey): ?array {
    $config = secureit_load_tenants();
    foreach (($config['tenants'] ?? []) as $tenant) {
        if (($tenant['id'] ?? '') === $tenantKey) {
            return $tenant;
        }
    }
    return null;
}

function secureit_load_canonical_controls(): array {
    $config = secureit_config();
    $paths = [
        $config['canonical_controls_file'] ?? '',
        $config['canonical_controls_example_file'] ?? '',
    ];

    foreach ($paths as $path) {
        if (!$path || !file_exists($path)) {
            continue;
        }
        $data = json_decode(file_get_contents($path), true);
        if (is_array($data)) {
            return $data;
        }
    }

    return [
        'functionalAreas' => [
            'Identity & Access Management',
            'Email & Calendaring',
            'Collaboration & Communication',
            'Files, Intranet & Content Management',
            'Endpoint & Device Management',
            'Security Operations & Threat Protection',
            'Compliance, Governance & Data Protection',
            'Productivity, Automation & AI',
        ],
        'controls' => [],
        'unmappedPolicy' => [
            'defaultDuplicatePolicy' => 'single',
            'defaultScoringWeight' => 1,
        ],
    ];
}

function secureit_tenant_embedded_summary(string $tenantKey): ?array {
    $path = secureit_reports_root() . '/' . $tenantKey . '/latest/embedded-summary.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function secureit_normalise_mapping_id(string $value): string {
    return strtoupper(trim($value));
}

function secureit_pattern_matches_test_id(string $pattern, string $testId): bool {
    $pattern = secureit_normalise_mapping_id($pattern);
    $testId = secureit_normalise_mapping_id($testId);

    if ($pattern === $testId) {
        return true;
    }

    if (str_contains($pattern, '*')) {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\\*', '.*', $quoted) . '$/i';
        return (bool) preg_match($regex, $testId);
    }

    return false;
}

function secureit_extract_tests_from_embedded_summary(?array $embedded): array {
    if (!$embedded) {
        return [];
    }

    $tests = [];
    foreach (($embedded['Tests'] ?? []) as $test) {
        $id = trim((string) ($test['Id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $tests[] = [
            'id' => $id,
            'result' => strtolower(trim((string) ($test['Result'] ?? 'unknown'))),
            'title' => trim((string) ($test['Title'] ?? '')),
            'severity' => trim((string) ($test['Severity'] ?? '')),
            'tags' => is_array($test['Tag'] ?? null) ? $test['Tag'] : [],
        ];
    }

    return $tests;
}

function secureit_extract_test_ids_from_embedded_summary(?array $embedded): array {
    $ids = [];
    foreach (secureit_extract_tests_from_embedded_summary($embedded) as $test) {
        $ids[] = $test['id'];
    }
    return array_values(array_unique($ids));
}

function secureit_is_pass_result(string $result): bool {
    return in_array($result, ['pass', 'passed'], true);
}

function secureit_is_fail_result(string $result): bool {
    return in_array($result, ['fail', 'failed', 'error'], true);
}

function secureit_is_neutral_result(string $result): bool {
    return in_array($result, ['skipped', 'notrun', 'not run', 'investigate', 'unknown'], true);
}

function secureit_evaluate_control_status(array $matchedTests, string $passLogic): string {
    if (!$matchedTests) {
        return 'unmapped';
    }

    $passCount = 0;
    $failCount = 0;
    $neutralCount = 0;

    foreach ($matchedTests as $test) {
        $result = $test['result'] ?? 'unknown';
        if (secureit_is_pass_result($result)) {
            $passCount++;
        } elseif (secureit_is_fail_result($result)) {
            $failCount++;
        } else {
            $neutralCount++;
        }
    }

    switch ($passLogic) {
        case 'any-pass-no-fail-review':
            if ($failCount > 0) {
                return $passCount > 0 ? 'partial' : 'fail';
            }
            return $passCount > 0 ? 'pass' : 'partial';

        case 'majority-pass':
            if ($passCount === 0 && $failCount === 0) {
                return 'partial';
            }
            if ($failCount === 0 && $passCount > 0) {
                return 'pass';
            }
            if ($passCount > 0) {
                return 'partial';
            }
            return 'fail';

        case 'direct':
        default:
            if ($passCount > 0 && $failCount === 0) {
                return 'pass';
            }
            if ($passCount > 0) {
                return 'partial';
            }
            if ($failCount > 0) {
                return 'fail';
            }
            return 'partial';
    }
}

function secureit_resolve_canonical_area_scores(string $tenantKey): array {
    $mapping = secureit_load_canonical_controls();
    $embedded = secureit_tenant_embedded_summary($tenantKey);
    $summary = secureit_tenant_summary($tenantKey);

    $functionalAreas = $mapping['functionalAreas'] ?? [];
    $controls = $mapping['controls'] ?? [];
    $tests = secureit_extract_tests_from_embedded_summary($embedded);
    $availableIds = array_values(array_unique(array_map(static fn(array $test): string => $test['id'], $tests)));

    $testsById = [];
    foreach ($tests as $test) {
        $testsById[secureit_normalise_mapping_id($test['id'])][] = $test;
    }

    $groupResults = [];
    foreach (($embedded['Blocks'] ?? []) as $group) {
        $name = (string) ($group['Name'] ?? '');
        $groupResults[$name] = [
            'result' => (string) ($group['Result'] ?? ''),
            'failed' => (int) ($group['FailedCount'] ?? 0),
            'passed' => (int) ($group['PassedCount'] ?? 0),
            'error' => (int) ($group['ErrorCount'] ?? 0),
            'investigate' => (int) ($group['InvestigateCount'] ?? 0),
            'skipped' => (int) ($group['SkippedCount'] ?? 0),
            'notRun' => (int) ($group['NotRunCount'] ?? 0),
            'total' => (int) ($group['TotalCount'] ?? 0),
            'tag' => $group['Tag'] ?? [],
        ];
    }

    $areas = [];
    foreach ($functionalAreas as $area) {
        $areas[$area] = [
            'name' => $area,
            'status' => 'No data',
            'tone' => 'neutral',
            'score' => null,
            'controlsTotal' => 0,
            'controlsPassing' => 0,
            'controlsFailing' => 0,
            'controlsPartial' => 0,
            'controlsUnmapped' => 0,
            'controls' => [],
        ];
    }

    foreach ($controls as $control) {
        $area = $control['functionalArea'] ?? '';
        if (!isset($areas[$area])) {
            continue;
        }

        $matchedTests = [];
        foreach (($control['frameworkMappings'] ?? []) as $pattern) {
            foreach ($availableIds as $availableId) {
                if (!secureit_pattern_matches_test_id((string) $pattern, $availableId)) {
                    continue;
                }
                $lookupId = secureit_normalise_mapping_id($availableId);
                foreach (($testsById[$lookupId] ?? []) as $test) {
                    $matchedTests[] = $test;
                }
            }
        }

        $matchedIds = [];
        foreach ($matchedTests as $test) {
            $matchedIds[] = $test['id'];
        }
        $matchedIds = array_values(array_unique($matchedIds));

        $status = secureit_evaluate_control_status(
            $matchedTests,
            (string) (($control['scoring']['passLogic'] ?? 'direct'))
        );

        $areas[$area]['controlsTotal']++;
        if ($status === 'pass') {
            $areas[$area]['controlsPassing']++;
        } elseif ($status === 'partial') {
            $areas[$area]['controlsPartial']++;
        } elseif ($status === 'unmapped') {
            $areas[$area]['controlsUnmapped']++;
        } else {
            $areas[$area]['controlsFailing']++;
        }

        $areas[$area]['controls'][] = [
            'id' => $control['id'] ?? '',
            'title' => $control['title'] ?? '',
            'description' => $control['description'] ?? '',
            'status' => $status,
            'frameworkMappings' => $control['frameworkMappings'] ?? [],
            'matchedIds' => $matchedIds,
            'matchedTests' => $matchedTests,
            'weight' => (int) (($control['scoring']['weight'] ?? 1)),
        ];
    }

    foreach ($areas as $areaName => &$area) {
        if ($area['controlsTotal'] === 0) {
            $area['status'] = 'No data';
            $area['tone'] = 'neutral';
            $area['score'] = null;
            continue;
        }

        $weightedEarned = 0.0;
        $weightedTotal = 0.0;
        foreach ($area['controls'] as $control) {
            $weight = max(1, (int) ($control['weight'] ?? 1));
            $weightedTotal += $weight;
            if (($control['status'] ?? '') === 'pass') {
                $weightedEarned += $weight;
            } elseif (($control['status'] ?? '') === 'partial') {
                $weightedEarned += ($weight * 0.5);
            }
        }

        $score = $weightedTotal > 0 ? (int) round(($weightedEarned / $weightedTotal) * 100) : null;
        $area['score'] = $score;

        if ($score >= 85) {
            $area['status'] = 'Healthy';
            $area['tone'] = 'good';
        } elseif ($score >= 65) {
            $area['status'] = 'Watch';
            $area['tone'] = 'warn';
        } else {
            $area['status'] = 'Needs attention';
            $area['tone'] = 'bad';
        }
    }
    unset($area);

    return [
        'summary' => $summary,
        'embedded' => $embedded,
        'groups' => $groupResults,
        'areas' => array_values($areas),
        'availableTestIds' => $availableIds,
    ];
}

function secureit_secret_name(string $tenantKey, string $suffix): string {
    return 'secureit-' . trim(strtolower($tenantKey)) . '-' . $suffix;
}

function secureit_guid_like(string $value): bool {
    return (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $value);
}

function secureit_summary_counts(?array $summary): array {
    $total = (int) ($summary['total'] ?? 0);
    $passed = (int) ($summary['passed'] ?? 0);
    $failed = (int) ($summary['failed'] ?? 0);
    $skipped = (int) ($summary['skipped'] ?? 0);
    $completed = max(0, $passed + $failed + $skipped);
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
        'completed' => $completed,
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
        $dt = new DateTimeImmutable($value);
        return $dt->format('j M Y, H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function secureit_dashboard_stats(array $tenants): array {
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

        $summary = secureit_tenant_summary($tenantKey);
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

function secureit_render_shell(string $title, string $content, array $options = []): void {
    $app = secureit_config();
    $pageTitle = $options['pageTitle'] ?? null;
    $pageIntro = $options['pageIntro'] ?? null;
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
      font-weight: 800;
      font-size: 1.3rem;
      color: var(--brand-strong);
      letter-spacing: -0.02em;
    }
    .brand-link span:last-child {
      color: #2b6e6b;
      margin-left: 6px;
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
    .button, .button-secondary, button {
      appearance: none;
      border: 0;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 16px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 600;
      transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .button:hover, .button-secondary:hover, button:hover { transform: translateY(-1px); }
    .button, button {
      background: white;
      color: var(--brand);
      box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }
    .header-actions .button {
      background: var(--brand);
      color: #fff;
      box-shadow: none;
    }
    .button-secondary {
      background: rgba(255,255,255,0.08);
      color: white;
      border: 1px solid rgba(255,255,255,0.16);
    }
    .button-ghost {
      background: transparent;
      color: var(--brand-strong);
      border: 1px solid rgba(0, 99, 95, 0.12);
      box-shadow: none;
    }
    .app-shell {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
      padding: 30px 0 52px;
    }
    .hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, rgba(0,99,95,0.92) 0%, rgba(0,99,95,0.90) 45%, rgba(51,153,151,0.85) 100%);
      color: #fff;
      border-radius: var(--radius-xl);
      padding: 34px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
      border: 1px solid rgba(255,255,255,0.08);
    }
    .hero-row {
      position: relative;
      z-index: 1;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      align-items: flex-start;
      flex-wrap: wrap;
    }
    .eyebrow,
    .hero-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      background: rgba(255,255,255,0.13);
      color: rgba(255,255,255,0.94);
      font-size: 0.86rem;
      border: 1px solid rgba(255,255,255,0.12);
    }
    .eyebrow { margin-bottom: 14px; }
    h1, h2, h3, p { margin-top: 0; }
    .hero h1 {
      margin-bottom: 10px;
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1.02;
    }
    .hero p {
      margin-bottom: 0;
      max-width: 760px;
      color: rgba(255,255,255,0.88);
      font-size: 1rem;
      line-height: 1.6;
      white-space: pre-line;
    }
    .hero-actions,
    .hero-pill-row,
    .inline-links {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }
    .hero-pill-row { margin-top: 16px; }
    .section { margin-top: 22px; }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }
    .section-title {
      margin-bottom: 4px;
      font-size: 1.2rem;
    }
    .muted {
      color: var(--muted);
      line-height: 1.65;
    }
    .metrics-grid, .tenant-grid, .feature-grid, .portal-grid, .partner-grid {
      display: grid;
      gap: 16px;
    }
    .metrics-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tenant-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .feature-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .portal-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .partner-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
    }
    .metric-card, .tenant-card, .panel, .feature-card, .portal-card, .partner-card, .metric-stat {
      padding: 24px;
      min-width: 0;
    }
    .metric-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 10px;
      min-height: 180px;
    }
    .metrics-strip {
      margin-top: 8px;
      margin-bottom: 6px;
    }
    .metrics-strip .metrics-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .metric-stat {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      text-align: left;
    }
    .metric-label, .metric-note {
      color: var(--muted);
      line-height: 1.55;
      overflow-wrap: anywhere;
    }
    .metric-value {
      font-size: clamp(2rem, 3vw, 3rem);
      line-height: 1.05;
      font-weight: 800;
      margin-bottom: 8px;
      color: var(--brand);
      overflow-wrap: anywhere;
    }
    .tenant-card, .portal-card {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .tenant-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
    }
    .tenant-name { margin-bottom: 6px; font-size: 1.2rem; }
    .tenant-meta {
      display: grid;
      gap: 6px;
      font-size: 0.93rem;
      color: var(--muted);
      overflow-wrap: anywhere;
    }
    .portal-icon,
    .feature-icon {
      width: 56px;
      height: 56px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 18px;
      font-size: 1.6rem;
      background: var(--surface-soft);
      border: 1px solid var(--line);
    }
    .section-heading {
      margin-bottom: 22px;
    }
    .section-kicker {
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--brand);
      font-weight: 800;
      margin-bottom: 10px;
    }
    .section-intro {
      color: var(--muted);
      line-height: 1.75;
      max-width: 860px;
      margin-bottom: 0;
    }
    .alt {
      background: rgba(0, 99, 95, 0.04);
      border: 1px solid rgba(0, 99, 95, 0.08);
      border-radius: var(--radius-xl);
      padding: 28px 0;
    }
    .partner-card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.86rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .tone-good { color: var(--good); background: var(--good-bg); }
    .tone-warn { color: var(--warn); background: var(--warn-bg); }
    .tone-bad { color: var(--bad); background: var(--bad-bg); }
    .tone-neutral { color: var(--neutral); background: var(--neutral-bg); }
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
    .split {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
      gap: 28px;
      align-items: stretch;
    }
    .kv { display: grid; gap: 12px; }
    .kv-row {
      display: grid;
      grid-template-columns: 150px 1fr;
      gap: 10px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--line);
    }
    .kv-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .kv-label { color: var(--muted); font-size: 0.92rem; }
    .kv-value { word-break: break-word; }
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
      color: var(--muted);
    }
    tr:last-child td { border-bottom: 0; }
    .textlink {
      color: var(--brand);
      text-decoration: none;
      font-weight: 600;
    }
    .textlink:hover { text-decoration: underline; }
    .empty-state, .success, .error {
      padding: 22px;
      border-radius: var(--radius-lg);
    }
    .empty-state {
      background: linear-gradient(180deg, #f3fbfa 0%, #edf7f6 100%);
      border: 1px solid rgba(0, 99, 95, 0.16);
      color: var(--eden);
    }
    .success {
      color: #136045;
      background: #edf9f5;
      border: 1px solid #b9ead5;
    }
    .error {
      color: #9b3f17;
      background: #fff3ef;
      border: 1px solid #f3c8b7;
    }
    input, select {
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
    input:focus, select:focus {
      outline: none;
      border-color: var(--brand-accent);
      box-shadow: 0 0 0 4px rgba(51, 153, 151, 0.16);
    }
    input[readonly], input[disabled] {
      background: #f4f8f8;
      color: #4d6763;
    }
    label {
      display: block;
      margin-top: 1rem;
      font-weight: 700;
      color: var(--eden);
    }
    .field-note {
      margin-top: 8px;
      color: var(--muted);
      font-size: 0.93rem;
      line-height: 1.6;
    }
    pre {
      background: #153a35;
      color: #eef9f7;
      padding: 1rem;
      border-radius: 14px;
      overflow: auto;
      border: 1px solid rgba(255,255,255,0.08);
    }
    .site-footer {
      margin-top: 64px;
      background: var(--footer-bg);
      color: var(--footer-text);
      border-top: 1px solid var(--footer-line);
    }
    .footer-wrap {
      padding: 42px 0 18px;
      display: grid;
      gap: 26px;
    }
    .footer-grid {
      display: grid;
      grid-template-columns: 1.2fr 1fr 1fr 1fr;
      gap: 22px;
      align-items: start;
    }
    .footer-heading {
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      margin-bottom: 14px;
    }
    .footer-copy,
    .footer-list a,
    .footer-list li,
    .footer-meta {
      color: var(--footer-text);
      line-height: 1.7;
      font-size: 0.95rem;
      text-decoration: none;
    }
    .footer-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: grid;
      gap: 10px;
    }
    .footer-list a:hover {
      color: #fff;
    }
    .footer-meta {
      padding-top: 18px;
      border-top: 1px solid var(--footer-line);
      display: flex;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      color: var(--footer-muted);
    }
    @media (max-width: 980px) {
      .main-nav {
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      .split { grid-template-columns: 1fr; }
      .metrics-grid, .feature-grid, .tenant-grid, .stats-row, .portal-grid, .partner-grid, .footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .kv-row { grid-template-columns: 1fr; gap: 6px; }
    }
    @media (max-width: 640px) {
      .container, .app-shell { width: min(100% - 20px, 1180px); }
      .app-shell { padding-top: 18px; }
      .site-header { position: static; }
      .nav-wrap, .main-nav, .nav-links, .header-actions { align-items: stretch; }
      .nav-wrap, .main-nav { flex-direction: column; }
      .nav-links, .header-actions { width: 100%; }
      .header-actions { justify-content: flex-start; }
      .hero { padding: 22px; border-radius: 20px; }
      .metrics-grid, .feature-grid, .tenant-grid, .stats-row, .portal-grid, .partner-grid, .footer-grid { grid-template-columns: 1fr; }
      th, td { padding: 12px; }
      .footer-meta { flex-direction: column; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand-link" href="index.php" aria-label="SecureIT homepage">
        <span>ICT365</span><span>SecureIT</span>
      </a>
      <div class="main-nav">
        <nav class="nav-links" aria-label="Primary">
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
              <button class="menu-trigger" type="button" aria-label="Open menu">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
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
  </header>

  <main class="app-shell">
    <?php if ($pageTitle !== null || $pageIntro !== null): ?>
      <section class="hero card">
        <div class="hero-row">
          <div>
            <?php if (!$hideHeroChrome && $eyebrow !== ''): ?>
              <div class="eyebrow"><?php echo htmlspecialchars($eyebrow); ?></div>
            <?php endif; ?>
            <?php if ($pageTitle !== null): ?>
              <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php endif; ?>
            <?php if ($pageIntro !== null && $pageIntro !== ''): ?>
              <p style="max-width: <?php echo htmlspecialchars($heroIntroMaxWidth); ?>;"><?php echo htmlspecialchars($pageIntro); ?></p>
            <?php endif; ?>
            <?php if (!$hideHeroChrome && $heroBadges): ?>
              <div class="hero-pill-row">
                <?php foreach ($heroBadges as $badge): ?>
                  <div class="hero-pill"><?php echo htmlspecialchars($badge); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php if ((!$hideHeroChrome && $heroActions) || $backHref): ?>
            <div class="hero-actions">
              <?php if ($backHref): ?>
                <a class="button-secondary" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo htmlspecialchars($backLabel); ?></a>
              <?php endif; ?>
              <?php if (!$hideHeroChrome): ?>
                <?php foreach ($heroActions as $action): ?>
                  <a class="<?php echo htmlspecialchars($action['class'] ?? 'button'); ?>" href="<?php echo htmlspecialchars($action['href']); ?>"><?php echo htmlspecialchars($action['label']); ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php echo $content; ?>
  </main>

  <footer class="site-footer">
    <div class="container footer-wrap">
      <div class="footer-grid">
        <div>
          <div class="footer-heading">SecureIT by ICT365</div>
          <p class="footer-copy">Container-ready SecureIT surface aligned with the ICT365 prototype experience for managed Microsoft 365 security reporting, customer posture visibility, and tenant onboarding.</p>
        </div>
        <div>
          <div class="footer-heading">Explore</div>
          <ul class="footer-list">
            <?php foreach ($footerLinks as $link): ?>
              <li><a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <div class="footer-heading">Platform</div>
          <ul class="footer-list">
            <?php foreach ($footerSecondaryLinks as $link): ?>
              <li><a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <div class="footer-heading">Contact</div>
          <ul class="footer-list">
            <?php foreach ($footerContact as $link): ?>
              <li><a href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <div class="footer-meta">
        <span>SecureIT container app</span>
        <span><?php echo htmlspecialchars($app['base_url']); ?></span>
      </div>
    </div>
  </footer>
</body>
</html>
<?php
}
