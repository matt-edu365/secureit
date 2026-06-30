<?php
require __DIR__ . '/lib.php';
$tenantKey = $_GET['tenant'] ?? '';
$tenant = secureit_find_tenant($tenantKey);
$app = secureit_config();
$authRole = secureit_current_user_role();

if (!$tenant) {
    http_response_code(404);
    echo 'Tenant not found';
    exit;
}

secureit_require_tenant_access($tenantKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_latest_report'])) {
    $dispatchInputs = [
        'tenant_key' => $tenantKey,
        'tenant_name' => trim((string) ($tenant['name'] ?? '')),
        'tenant_id' => trim((string) ($tenant['tenantId'] ?? '')),
        'client_id' => trim((string) ($tenant['clientId'] ?? '')),
        'auth_mode' => trim((string) ($tenant['authMode'] ?? 'client-secret')),
        'client_secret_name' => trim((string) ($tenant['clientSecretName'] ?? '')),
        'certificate_secret_name' => trim((string) ($tenant['certificateSecretName'] ?? '')),
        'certificate_password_secret_name' => trim((string) ($tenant['certificatePasswordSecretName'] ?? '')),
        'tenant_domain' => trim((string) ($tenant['tenantDomain'] ?? '')),
        'm365_tenant_name' => trim((string) ($tenant['m365TenantName'] ?? '')),
        'email_to' => trim((string) ($tenant['emailTo'] ?? '')),
        'test_profile' => 'client-secret-full',
    ];
    $dispatchInputs['report_base_url'] = trim((string) ($tenant['reportBaseUrl'] ?? ''));
    if ($dispatchInputs['report_base_url'] === '') {
        $dispatchInputs['report_base_url'] = rtrim((string) ($app['base_url'] ?? secureit_config()['base_url']), '/') . '/' . rawurlencode($tenantKey);
    }
    $dispatchResult = secureit_github_dispatch_workflow($dispatchInputs);

    if (!empty($dispatchResult['ok'])) {
        $workflowUrl = (string) ($dispatchResult['workflowUrl'] ?? '');
        $message = 'Queued SecureIT Production for ' . ($tenant['name'] ?? $tenantKey) . '.';
        if ($workflowUrl !== '') {
            $message .= ' Open the workflow runs page if you want to watch it progress.';
        }

        secureit_flash_set('tenant_manual_report_run', [
            'ok' => true,
            'message' => $message,
            'workflowUrl' => $workflowUrl,
        ]);
    } else {
        secureit_flash_set('tenant_manual_report_run', [
            'ok' => false,
            'message' => 'Unable to queue the SecureIT Production workflow: ' . trim((string) ($dispatchResult['error'] ?? 'Unknown error')),
            'workflowUrl' => (string) ($dispatchResult['workflowUrl'] ?? ''),
        ]);
    }

    header('Location: tenant.php?tenant=' . rawurlencode($tenantKey), true, 303);
    exit;
}

$manualReportRunNotice = secureit_flash_pull('tenant_manual_report_run');

$summary = secureit_tenant_summary($tenantKey);
$areaData = secureit_resolve_canonical_area_scores($tenantKey);
$counts = secureit_check_summary_counts($areaData);
$functionalAreas = $areaData['areas'] ?? [];
$analysisText = secureit_tenant_analysis_text($summary, $areaData);
$selectedAreaName = trim((string) ($_GET['area'] ?? ''));
$selectedArea = null;
if ($selectedAreaName !== '') {
    foreach ($functionalAreas as $area) {
        if (($area['name'] ?? '') === $selectedAreaName) {
            $selectedArea = $area;
            break;
        }
    }
}

function secureit_functional_area_description(string $areaName): string {
    foreach (secureit_functional_area_catalog() as $area) {
        if (($area['name'] ?? '') === $areaName) {
            return (string) ($area['description'] ?? '');
        }
    }

    return 'SecureIT reviews the Microsoft 365 services, policies, checks, and related settings that map to this area.';
}

function secureit_functional_area_partial_test_count(array $area): int {
    $partialTests = [];
    foreach (($area['controls'] ?? []) as $control) {
        if (($control['status'] ?? '') !== 'partial') {
            continue;
        }
        foreach (($control['matchedTests'] ?? []) as $test) {
            $testId = secureit_normalise_mapping_id((string) ($test['id'] ?? ''));
            if ($testId !== '') {
                $partialTests[$testId] = true;
            }
        }
    }

    return count($partialTests);
}

function secureit_functional_area_history_points(array $history, string $areaName): array {
    $points = [];
    foreach ($history as $item) {
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : null;
        $rowAreaData = secureit_resolve_canonical_area_scores_from_artifact($item['embedded'] ?? null, $summary);
        $areaScore = null;
        foreach (($rowAreaData['areas'] ?? []) as $area) {
            if (($area['name'] ?? '') !== $areaName) {
                continue;
            }
            $areaScore = $area['score'] !== null ? (int) $area['score'] : null;
            break;
        }

        $points[] = [
            'generatedAt' => (string) ($summary['generatedAt'] ?? ''),
            'score' => $areaScore,
        ];
    }

    return $points;
}

function secureit_functional_area_trend_card(string $areaName, array $points): string {
    $scores = [];
    foreach ($points as $point) {
        if (($point['score'] ?? null) !== null) {
            $scores[] = (int) $point['score'];
        }
    }

    if ($scores === []) {
        return '<article class="card panel" style="margin-bottom:18px;"><div class="empty-state" style="box-shadow:none;"><strong>No area trend data yet.</strong><p class="muted">A score trend will appear once SecureIT has a few historical runs for this functional area.</p></div></article>';
    }

    $width = 640;
    $height = 240;
    $paddingX = 34;
    $paddingY = 28;
    $plotWidth = $width - ($paddingX * 2);
    $plotHeight = $height - ($paddingY * 2);
    $count = count($scores);
    $step = $count > 1 ? $plotWidth / ($count - 1) : 0;
    $linePoints = [];
    $plotPoints = [];
    foreach ($scores as $index => $score) {
        $x = $paddingX + ($step * $index);
        $y = $paddingY + ($plotHeight - (($score / 100) * $plotHeight));
        $linePoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        $plotPoints[] = [$x, $y, $score];
    }

    $gridLines = '';
    foreach ([0, 25, 50, 75, 100] as $mark) {
        $y = $paddingY + ($plotHeight - (($mark / 100) * $plotHeight));
        $gridLines .= '<line x1="' . $paddingX . '" y1="' . number_format($y, 2, '.', '') . '" x2="' . ($width - $paddingX) . '" y2="' . number_format($y, 2, '.', '') . '" stroke="rgba(15, 23, 42, 0.08)" stroke-width="1"/>';
        $gridLines .= '<text x="12" y="' . number_format($y + 4, 2, '.', '') . '" fill="#6b7c77" font-size="11" font-family="Arial,Helvetica,sans-serif">' . $mark . '%</text>';
    }

    $fillPath = '';
    if ($plotPoints !== []) {
        $first = $plotPoints[0];
        $last = $plotPoints[$count - 1];
        $fillPath = 'M ' . number_format($first[0], 2, '.', '') . ',' . number_format($height - $paddingY, 2, '.', '') . ' L ' . implode(' L ', $linePoints) . ' L ' . number_format($last[0], 2, '.', '') . ',' . number_format($height - $paddingY, 2, '.', '') . ' Z';
    }

    $dots = '';
    foreach ($plotPoints as $index => $point) {
        [$x, $y, $score] = $point;
        $label = secureit_format_datetime($points[$index]['generatedAt'] ?? null);
        $dots .= '<g>';
        $dots .= '<circle cx="' . number_format($x, 2, '.', '') . '" cy="' . number_format($y, 2, '.', '') . '" r="4.5" fill="#0f766e" stroke="#ffffff" stroke-width="2"><title>' . htmlspecialchars($label . ' - ' . $score . '%') . '</title></circle>';
        $dots .= '<text x="' . number_format($x, 2, '.', '') . '" y="' . ($height - 10) . '" text-anchor="middle" fill="#526660" font-size="10" font-family="Arial,Helvetica,sans-serif">' . htmlspecialchars($index === ($count - 1) ? 'Latest' : 'Run ' . ($index + 1)) . '</text>';
        $dots .= '</g>';
    }

    $latestScore = $scores[$count - 1];
    $latestLabel = secureit_format_datetime($points[$count - 1]['generatedAt'] ?? null);

    return '<article class="card panel" style="margin-bottom:18px; padding:20px 20px 18px;">'
        . '<div class="section-header" style="margin-bottom:14px; align-items:flex-start;">'
        . '<div>'
        . '<h3 class="section-title" style="font-size:1.08rem; margin-bottom:4px;">Score trend - ' . htmlspecialchars($areaName) . '</h3>'
        . '<div class="muted">Last 5 report scores for this functional area. Latest score: ' . htmlspecialchars((string) $latestScore) . '% on ' . htmlspecialchars($latestLabel) . '.</div>'
        . '</div>'
        . '<div class="badge tone-good">Latest ' . htmlspecialchars((string) $latestScore) . '%</div>'
        . '</div>'
        . '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Score trend for ' . htmlspecialchars($areaName) . '" style="width:100%; height:auto; display:block; overflow:visible;">'
        . '<defs><linearGradient id="areaTrendFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#0f766e" stop-opacity="0.28"/><stop offset="100%" stop-color="#0f766e" stop-opacity="0.02"/></linearGradient></defs>'
        . $gridLines
        . ($fillPath !== '' ? '<path d="' . htmlspecialchars($fillPath) . '" fill="url(#areaTrendFill)" stroke="none"/>' : '')
        . '<path d="' . htmlspecialchars('M ' . implode(' L ', $linePoints)) . '" fill="none" stroke="#0f766e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
        . $dots
        . '</svg>'
        . '<div class="muted" style="margin-top:10px;">The chart plots the selected functional area score from the last five stored reports.</div>'
        . '</article>';
}

function secureit_functional_area_analysis_text(array $area): string {
    $controlsTotal = (int) ($area['controlsTotal'] ?? 0);
    if ($controlsTotal === 0) {
        return 'No canonical checks are currently mapped to this functional area, so SecureIT cannot calculate an area score yet.';
    }

    $score = $area['score'];
    $controlsPassing = (int) ($area['controlsPassing'] ?? 0);
    $controlsPartial = (int) ($area['controlsPartial'] ?? 0);
    $controlsFailing = (int) ($area['controlsFailing'] ?? 0);
    $testsTotal = (int) ($area['testsTotal'] ?? 0);
    $testsPassed = (int) ($area['testsPassed'] ?? 0);
    $testsFailed = (int) ($area['testsFailed'] ?? 0);
    $testsSkipped = (int) ($area['testsSkipped'] ?? 0);

    $summary = [];
    $summary[] = sprintf(
        'This area scores %s across %d checks.',
        $score !== null ? (string) $score . '%' : 'unavailable',
        $controlsTotal
    );
    $summary[] = sprintf(
        '%d checks passed, %d were partially met, and %d failed.',
        $controlsPassing,
        $controlsPartial,
        $controlsFailing
    );

    if ($testsTotal > 0) {
        $summary[] = sprintf(
            'Those checks are backed by %d underlying assessment items with %d passed, %d failed, and %d skipped.',
            $testsTotal,
            $testsPassed,
            $testsFailed,
            $testsSkipped
        );
    }

    return implode(' ', $summary);
}

function secureit_functional_area_visual(string $areaName): array {
    $visuals = [
        'Identity & Access Management' => [
            'bg' => '#eef7f6',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#0f766e',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-2.9 7.8-7 10-4.1-2.2-7-5.5-7-10V6l7-3z"/><path fill="currentColor" d="M12 8.3a2.2 2.2 0 100 4.4 2.2 2.2 0 000-4.4z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M12 13.2v4"/></svg>',
        ],
        'Email & Calendaring' => [
            'bg' => '#eef4fb',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#1d4ed8',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4.8 7.2l7.2 5.4 7.2-5.4"/></svg>',
        ],
        'Collaboration & Communication' => [
            'bg' => '#f2effc',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#6d28d9',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M7 6h10a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3h-4l-4 3v-3H7a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z"/><circle cx="9" cy="11" r="1.1" fill="currentColor"/><circle cx="12" cy="11" r="1.1" fill="currentColor"/><circle cx="15" cy="11" r="1.1" fill="currentColor"/></svg>',
        ],
        'Files, Intranet & Content Management' => [
            'bg' => '#fff4e8',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#c2410c',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M4 7h6l2 2h8v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 10.5h16"/></svg>',
        ],
        'Endpoint & Device Management' => [
            'bg' => '#ecf7fb',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#0f766e',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M8 19h8M12 16v3"/></svg>',
        ],
        'Security Operations & Threat Protection' => [
            'bg' => '#fff1f2',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#b91c1c',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-2.9 7.8-7 10-4.1-2.2-7-5.5-7-10V6l7-3z"/><path fill="currentColor" d="M13 7l-2 5h3l-3 6 1-4h-3l4-7z"/></svg>',
        ],
        'Compliance, Governance & Data Protection' => [
            'bg' => '#f4f5f7',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#475569',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="4" width="14" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M8.5 8.5h7M8.5 12h7M8.5 15.5h4.5"/><path fill="currentColor" d="M16.2 8.3l1.2 1.2 2-2 1 1-3 3-2.2-2.2z"/></svg>',
        ],
        'Productivity, Automation & AI' => [
            'bg' => '#f3efff',
            'shadow' => 'rgba(15, 23, 42, 0.10)',
            'stroke' => '#7c3aed',
            'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M13 3l-2 7h5l-6 11 2-7H7l6-11z"/><circle cx="18" cy="6" r="1.3" fill="currentColor"/><circle cx="6" cy="18" r="1.3" fill="currentColor"/><circle cx="16.5" cy="16.5" r="1.1" fill="currentColor"/></svg>',
        ],
    ];

    return $visuals[$areaName] ?? [
        'bg' => '#f3f4f6',
        'shadow' => 'rgba(15, 23, 42, 0.10)',
        'stroke' => '#64748b',
        'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M12 8v8M8 12h8"/></svg>',
    ];
}

$historyRoot = secureit_reports_root() . '/' . $tenantKey . '/history';
$history = [];
if (is_dir($historyRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($historyRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'summary.json') {
            $relative = str_replace(secureit_reports_root() . '/', '', $file->getPathname());
            $embeddedPath = dirname($file->getPathname()) . '/embedded-summary.json';
            $history[] = [
                'reportPath' => dirname($relative) . '/index.html',
                'summary' => json_decode(file_get_contents($file->getPathname()), true),
                'embedded' => file_exists($embeddedPath) ? json_decode(file_get_contents($embeddedPath), true) : null,
            ];
        }
    }
    usort($history, function ($a, $b) {
        return strcmp($b['summary']['generatedAt'] ?? '', $a['summary']['generatedAt'] ?? '');
    });
}
$historyStoredCount = count($history);
$history = array_slice($history, 0, 5);
$selectedAreaHistory = $selectedArea ? secureit_functional_area_history_points($history, (string) ($selectedArea['name'] ?? '')) : [];

ob_start();
?>
<section class="section split" style="grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items:stretch;">
  <article class="card panel" style="height:100%; display:flex; flex-direction:column; padding-bottom:34px;">
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title">
          <?php echo htmlspecialchars(($tenant['name'] ?? $tenantKey) . ' - ' . ($selectedArea ? ($selectedArea['name'] ?? 'Functional area') : 'SecureIT Dashboard')); ?>
        </h2>
      </div>
    </div>
    <?php if ($selectedArea): ?>
      <div class="kv">
        <div class="kv-row">
          <div class="kv-label">Technologies encompassed</div>
          <div class="kv-value"><?php echo htmlspecialchars(secureit_functional_area_description((string) ($selectedArea['name'] ?? ''))); ?></div>
        </div>
        <div class="kv-row">
          <div class="kv-label">Analysis</div>
          <div class="kv-value"><?php echo htmlspecialchars(secureit_functional_area_analysis_text($selectedArea)); ?></div>
        </div>
      </div>
    <?php else: ?>
      <div class="kv">
        <div class="kv-row"><div class="kv-label">Tenant ID</div><div class="kv-value"><?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div></div>
        <div class="kv-row"><div class="kv-label">Report recipient</div><div class="kv-value"><?php echo htmlspecialchars($tenant['emailTo'] ?? ''); ?></div></div>
        <div class="kv-row"><div class="kv-label">Latest analysis</div><div class="kv-value"><?php echo htmlspecialchars($analysisText); ?></div></div>
      </div>
    <?php endif; ?>
  </article>

  <article class="card panel" style="height:100%; display:flex; flex-direction:column;">
    <div class="section-header" style="margin-bottom:18px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
      <div style="min-width:0;">
        <h2 class="section-title"><?php echo $selectedArea ? 'Area Posture' : 'Current posture'; ?></h2>
        <div class="muted"><?php echo $selectedArea ? 'Operational snapshot for the selected functional area.' : 'Summary of the latest report.'; ?></div>
      </div>
      <?php if (!$selectedArea): ?>
        <form method="post" action="tenant.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>" style="margin:0;">
          <button type="submit" name="run_latest_report" value="1" style="white-space:nowrap;">Run report now</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (is_array($manualReportRunNotice ?? null)): ?>
      <div class="<?php echo !empty($manualReportRunNotice['ok']) ? 'success' : 'error'; ?>" style="margin-bottom:16px;">
        <?php echo htmlspecialchars((string) ($manualReportRunNotice['message'] ?? '')); ?>
        <?php if (!empty($manualReportRunNotice['workflowUrl'])): ?>
          <div style="margin-top:8px;">
            <a class="textlink" href="<?php echo htmlspecialchars((string) $manualReportRunNotice['workflowUrl']); ?>">Open GitHub workflow runs</a>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($summary): ?>
      <div class="stats-row" style="margin-bottom:14px;">
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['controlsTotal'] ?? 0) : $counts['total'])); ?></strong><span>Checks</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['controlsPassing'] ?? 0) : $counts['passed'])); ?></strong><span>Passed</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['controlsPartial'] ?? 0) : $counts['partial'])); ?></strong><span>Partially met</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['controlsFailing'] ?? 0) : $counts['failed'])); ?></strong><span>Failed</span></div>
      </div>
      <?php if ($selectedArea): ?>
        <?php $partialTests = secureit_functional_area_partial_test_count($selectedArea); ?>
        <?php if ($partialTests > 0): ?>
          <div class="muted" style="margin-bottom:10px;">** <?php echo htmlspecialchars((string) $partialTests); ?> checks were partially met.</div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="muted" style="margin-bottom:8px;"><?php echo $selectedArea ? 'Area pass rate' : 'Pass rate'; ?></div>
      <div class="progress" aria-label="Pass rate progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) ($selectedArea && (($selectedArea['controlsTotal'] ?? 0) > 0) ? round((($selectedArea['controlsPassing'] ?? 0) / max(1, (int) ($selectedArea['controlsTotal'] ?? 0))) * 100) : $counts['passRate'])); ?>%"></div></div>
      <div class="muted" style="margin-top:8px; margin-bottom:14px;"><?php echo htmlspecialchars((string) ($selectedArea && (($selectedArea['controlsTotal'] ?? 0) > 0) ? round((($selectedArea['controlsPassing'] ?? 0) / max(1, (int) ($selectedArea['controlsTotal'] ?? 0))) * 100) : $counts['passRate'])); ?>% checks passed<?php echo $selectedArea ? ' in this area' : ''; ?>, on <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.</div>
    <?php else: ?>
      <div class="empty-state" style="box-shadow:none;">
        <strong>No published report yet.</strong>
        <p class="muted">Once SecureIT publishes a latest summary for this tenant, the posture snapshot will appear here.</p>
      </div>
    <?php endif; ?>
  </article>
</section>
<?php if (!$selectedArea): ?>
<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Functional areas:</h2>
    </div>
  </div>
  <?php if (!$selectedArea): ?>
    <div class="feature-grid" id="functional-areas">
      <?php if (!$functionalAreas): ?>
        <article class="card feature-card">
          <h3>No functional area data yet</h3>
          <p>Run a tenant assessment with embedded summary persistence to populate canonical SecureIT checks and area scoring.</p>
        </article>
      <?php else: ?>
        <?php foreach ($functionalAreas as $area): ?>
          <?php $toneClass = 'tone-' . ($area['tone'] ?? 'neutral'); ?>
          <?php $areaHref = 'tenant.php?tenant=' . rawurlencode($tenantKey) . '&area=' . rawurlencode((string) ($area['name'] ?? '')); ?>
          <?php $areaVisual = secureit_functional_area_visual((string) ($area['name'] ?? '')); ?>
          <?php
            $areaScore = $area['score'] !== null ? (int) $area['score'] : null;
            $scoreState = secureit_functional_area_status_from_score($areaScore);
            $scoreTone = 'tone-' . $scoreState['tone'];
            $scoreLabel = $scoreState['scoreLabel'];
          ?>
          <a class="card feature-card" href="<?php echo htmlspecialchars($areaHref); ?>" style="display:block; text-decoration:none; color:inherit;">
            <div class="inline-links" style="justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:12px;">
              <span style="display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:16px; background:<?php echo htmlspecialchars($areaVisual['bg']); ?>; box-shadow:0 10px 20px <?php echo htmlspecialchars($areaVisual['shadow']); ?>; color:<?php echo htmlspecialchars($areaVisual['stroke']); ?>; flex:0 0 auto; border:1px solid rgba(15, 23, 42, 0.08);">
                <span style="width:24px; height:24px; display:flex; align-items:center; justify-content:center;">
                  <?php echo $areaVisual['svg']; ?>
                </span>
              </span>
              <span class="badge <?php echo htmlspecialchars($scoreTone); ?>"><?php echo htmlspecialchars($scoreLabel); ?></span>
            </div>
            <h3 style="min-height:2.8em;"><?php echo htmlspecialchars($area['name'] ?? 'Functional area'); ?></h3>
            <div class="kv" style="gap:6px; margin-top:12px;">
              <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Checks</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['controlsTotal'] ?? 0)); ?></div></div>
              <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Passed</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['controlsPassing'] ?? 0)); ?></div></div>
              <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Partially met</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['controlsPartial'] ?? 0)); ?></div></div>
              <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Failed</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['controlsFailing'] ?? 0)); ?></div></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($selectedArea): ?>
  <section class="section">
    <?php echo secureit_functional_area_trend_card((string) ($selectedArea['name'] ?? 'Functional area'), $selectedAreaHistory); ?>
  </section>
<?php endif; ?>

<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title"><?php echo $selectedArea ? 'Run history - ' . htmlspecialchars((string) ($selectedArea['name'] ?? 'Functional area')) : 'Run history'; ?></h2>
      <div class="muted"><?php echo $selectedArea ? 'Historical published reports for this functional area only.' : 'Historical published reports for trend review and quick drill-down. Showing the latest 5 stored runs for now.'; ?></div>
    </div>
    <div class="muted"><?php echo htmlspecialchars((string) min(5, $historyStoredCount)); ?> shown of <?php echo htmlspecialchars((string) $historyStoredCount); ?> stored run<?php echo $historyStoredCount === 1 ? '' : 's'; ?></div>
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
              <th>Checks</th>
              <th>Passed</th>
              <th>Partially met</th>
              <th>Failed</th>
              <th>Status</th>
              <?php if (!$selectedArea): ?>
                <th>Report</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $item): ?>
              <?php
                $s = $item['summary'] ?? [];
                $rowAreaData = secureit_resolve_canonical_area_scores_from_artifact($item['embedded'] ?? null, is_array($s) ? $s : null);
                if ($selectedArea) {
                    $row = null;
                    foreach (($rowAreaData['areas'] ?? []) as $area) {
                        if (($area['name'] ?? '') === (string) ($selectedArea['name'] ?? '')) {
                            $row = $area;
                            break;
                        }
                    }

                    $rowControlsTotal = (int) ($row['controlsTotal'] ?? 0);
                    $rowControlsPassing = (int) ($row['controlsPassing'] ?? 0);
                    $rowControlsPartial = (int) ($row['controlsPartial'] ?? 0);
                    $rowControlsFailing = (int) ($row['controlsFailing'] ?? 0);
                    $rowScore = $row['score'] !== null ? (int) $row['score'] : null;
                    $rowStatus = secureit_functional_area_status_from_score($rowScore);
                    $rowToneClass = 'tone-' . $rowStatus['tone'];
                    $rowRiskLevel = $rowStatus['status'];
                } else {
                    $rowCounts = secureit_check_summary_counts($rowAreaData);
                    $rowControlsTotal = $rowCounts['total'];
                    $rowControlsPassing = $rowCounts['passed'];
                    $rowControlsPartial = $rowCounts['partial'];
                    $rowControlsFailing = $rowCounts['failed'];
                    $rowToneClass = 'tone-' . strtolower($rowCounts['riskTone']);
                    $rowRiskLevel = $rowCounts['riskLevel'];
                }
              ?>
              <tr>
                <td><?php echo htmlspecialchars(secureit_format_datetime($s['generatedAt'] ?? null)); ?></td>
                <td><?php echo htmlspecialchars((string) $rowControlsTotal); ?></td>
                <td><?php echo htmlspecialchars((string) $rowControlsPassing); ?></td>
                <td><?php echo htmlspecialchars((string) $rowControlsPartial); ?></td>
                <td><?php echo htmlspecialchars((string) $rowControlsFailing); ?></td>
                <td><span class="badge <?php echo htmlspecialchars($rowToneClass); ?>"><?php echo htmlspecialchars($rowRiskLevel); ?></span></td>
                <?php if (!$selectedArea): ?>
                  <td><a class="textlink" href="<?php echo htmlspecialchars($item['reportPath']); ?>">Open report</a></td>
                <?php endif; ?>
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
secureit_render_shell(($tenant['name'] ?? $tenantKey) . ' - ' . $app['app_name'], $content, [
    'pageTitle' => null,
    'pageIntro' => null,
    'backHref' => null,
    'backLabel' => 'Back to customer login',
    'eyebrow' => '',
    'heroActions' => [],
    'navLinks' => [],
    'headerMenu' => $authRole === 'admin' ? [
        ['href' => 'dashboard.php', 'label' => 'Tenant overview dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
    ] : [],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'login.php', 'label' => 'Customer login'],
    ],
    'footerSecondaryLinks' => $authRole === 'admin' ? [
        ['href' => 'dashboard.php', 'label' => 'Employee portal'],
        ['href' => 'tenant.php?tenant=' . rawurlencode($tenantKey), 'label' => 'Current tenant'],
        ['href' => 'admin.php', 'label' => 'Admin'],
    ] : [
        ['href' => 'tenant.php?tenant=' . rawurlencode($tenantKey), 'label' => 'Current tenant'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
