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

$summary = secureit_tenant_summary($tenantKey);
$counts = secureit_summary_counts($summary);
$areaData = secureit_resolve_canonical_area_scores($tenantKey);
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

    return 'SecureIT reviews the Microsoft 365 services, policies, controls, and checks that map to this area.';
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

function secureit_functional_area_analysis_text(array $area): string {
    $controlsTotal = (int) ($area['controlsTotal'] ?? 0);
    if ($controlsTotal === 0) {
        return 'No canonical controls are currently mapped to this functional area, so SecureIT cannot calculate an area score yet.';
    }

    $score = $area['score'];
    $controlsPassing = (int) ($area['controlsPassing'] ?? 0);
    $controlsPartial = (int) ($area['controlsPartial'] ?? 0);
    $controlsFailing = (int) ($area['controlsFailing'] ?? 0);
    $controlsUnmapped = (int) ($area['controlsUnmapped'] ?? 0);
    $testsTotal = (int) ($area['testsTotal'] ?? 0);
    $testsPassed = (int) ($area['testsPassed'] ?? 0);
    $testsFailed = (int) ($area['testsFailed'] ?? 0);
    $testsSkipped = (int) ($area['testsSkipped'] ?? 0);

    $summary = [];
    $summary[] = sprintf(
        'This area scores %s across %d controls.',
        $score !== null ? (string) $score . '%' : 'unavailable',
        $controlsTotal
    );
    $summary[] = sprintf(
        '%d controls passed, %d were partially passed, %d failed, and %d are unmapped.',
        $controlsPassing,
        $controlsPartial,
        $controlsFailing,
        $controlsUnmapped
    );

    if ($testsTotal > 0) {
        $summary[] = sprintf(
            'The matched checks behind this area include %d tests with %d passed, %d failed, and %d skipped.',
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
    <div class="section-header" style="margin-bottom:18px;">
      <div>
        <h2 class="section-title"><?php echo $selectedArea ? 'Area Posture' : 'Current posture'; ?></h2>
        <div class="muted"><?php echo $selectedArea ? 'Operational snapshot for the selected functional area.' : 'Operational snapshot of the latest report.'; ?></div>
      </div>
    </div>

    <?php if ($summary): ?>
      <div class="stats-row" style="margin-bottom:14px;">
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['testsTotal'] ?? 0) : $counts['total'])); ?></strong><span>Total</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['testsPassed'] ?? 0) : $counts['passed'])); ?></strong><span>Passed</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['testsFailed'] ?? 0) : $counts['failed'])); ?></strong><span>Failed</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($selectedArea ? ($selectedArea['testsSkipped'] ?? 0) : $counts['skipped'])); ?></strong><span>Skipped</span></div>
      </div>
      <?php if ($selectedArea): ?>
        <?php $partialTests = secureit_functional_area_partial_test_count($selectedArea); ?>
        <?php if ($partialTests > 0): ?>
          <div class="muted" style="margin-bottom:10px;">** <?php echo htmlspecialchars((string) $partialTests); ?> tests were partially passed.</div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="muted" style="margin-bottom:8px;"><?php echo $selectedArea ? 'Area pass rate' : 'Pass rate'; ?></div>
      <div class="progress" aria-label="Pass rate progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) ($selectedArea && (($selectedArea['testsTotal'] ?? 0) > 0) ? round((($selectedArea['testsPassed'] ?? 0) / max(1, (int) ($selectedArea['testsTotal'] ?? 0))) * 100) : $counts['passRate'])); ?>%"></div></div>
      <div class="muted" style="margin-top:8px; margin-bottom:14px;"><?php echo htmlspecialchars((string) ($selectedArea && (($selectedArea['testsTotal'] ?? 0) > 0) ? round((($selectedArea['testsPassed'] ?? 0) / max(1, (int) ($selectedArea['testsTotal'] ?? 0))) * 100) : $counts['passRate'])); ?>% tests passed<?php echo $selectedArea ? ' in this area' : ''; ?>, on <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.</div>
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
      <h2 class="section-title">Functional areas:</h2>
    </div>
  </div>
  <?php if ($selectedArea): ?>
    <article class="card panel" style="margin-bottom:18px;">
      <div class="section-header" style="margin-bottom:14px;">
        <div>
          <h3 class="section-title" style="font-size:1.2rem;"><?php echo htmlspecialchars($selectedArea['name'] ?? 'Functional area'); ?></h3>
        </div>
        <div class="inline-links">
          <a class="button" href="tenant.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>" style="background:var(--brand); color:#fff; box-shadow:none;">Back to all areas</a>
        </div>
      </div>
      <?php if (!empty($selectedArea['controls'])): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Control</th>
                <th>Status</th>
                <th>Weight</th>
                <th>Matched checks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($selectedArea['controls'] as $control): ?>
                <?php
                  $controlStatus = (string) ($control['status'] ?? 'unknown');
                  $controlTone = 'neutral';
                  if ($controlStatus === 'pass') {
                      $controlTone = 'good';
                  } elseif ($controlStatus === 'partial') {
                      $controlTone = 'warn';
                  } elseif ($controlStatus === 'fail') {
                      $controlTone = 'bad';
                  }
                ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars($control['title'] ?? $control['id'] ?? 'Control'); ?></strong><br>
                    <span class="muted"><?php echo htmlspecialchars($control['description'] ?? ''); ?></span>
                  </td>
                  <td><span class="badge tone-<?php echo htmlspecialchars($controlTone); ?>"><?php echo htmlspecialchars(ucfirst($controlStatus)); ?></span></td>
                  <td><?php echo htmlspecialchars((string) ($control['weight'] ?? 1)); ?></td>
                  <td>
                    <?php if (!empty($control['matchedTests'])): ?>
                      <div class="muted" style="font-size:0.92rem;">
                        <?php
                          $matchedLabels = [];
                          foreach (array_slice($control['matchedTests'], 0, 6) as $test) {
                              $matchedLabels[] = $test['id'] . ' (' . ucfirst((string) ($test['result'] ?? 'unknown')) . ')';
                          }
                          echo htmlspecialchars(implode(', ', $matchedLabels));
                        ?>
                      </div>
                      <?php if (count($control['matchedTests']) > 6): ?>
                        <div class="muted" style="font-size:0.88rem; margin-top:4px;">+<?php echo htmlspecialchars((string) (count($control['matchedTests']) - 6)); ?> more checks</div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="muted">No matched checks</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state" style="box-shadow:none;">
          <strong>No controls mapped to this area.</strong>
          <p class="muted">This area currently has no canonical controls in the seeded data.</p>
        </div>
      <?php endif; ?>
    </article>
  <?php endif; ?>
  <div class="feature-grid" id="functional-areas">
    <?php if (!$functionalAreas): ?>
      <article class="card feature-card">
        <h3>No functional area data yet</h3>
        <p>Run a tenant assessment with embedded summary persistence to populate canonical SecureIT controls and area scoring.</p>
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
          <h3><?php echo htmlspecialchars($area['name'] ?? 'Functional area'); ?></h3>
          <div class="kv" style="gap:6px; margin-top:12px;">
            <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Total tests</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['testsTotal'] ?? 0)); ?></div></div>
            <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Passed</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['testsPassed'] ?? 0)); ?></div></div>
            <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Partially met</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['controlsPartial'] ?? 0)); ?></div></div>
            <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Failed</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['testsFailed'] ?? 0)); ?></div></div>
            <div class="kv-row" style="grid-template-columns: 1fr auto; padding-bottom:4px;"><div class="kv-label">Skipped</div><div class="kv-value"><?php echo htmlspecialchars((string) ($area['testsSkipped'] ?? 0)); ?></div></div>
          </div>
        </a>
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
secureit_render_shell(($tenant['name'] ?? $tenantKey) . ' - ' . $app['app_name'], $content, [
    'pageTitle' => null,
    'pageIntro' => null,
    'backHref' => null,
    'backLabel' => 'Back to customer login',
    'eyebrow' => '',
    'heroActions' => [],
    'navLinks' => [],
    'headerMenu' => $authRole === 'admin' ? [
        ['href' => 'dashboard.php', 'label' => 'ICT365 admin dashboard'],
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
