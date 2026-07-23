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
        'test_profile' => 'SecureIT-Production-101',
    ];
    $dispatchInputs['report_base_url'] = trim((string) ($tenant['reportBaseUrl'] ?? ''));
    if ($dispatchInputs['report_base_url'] === '') {
        $dispatchInputs['report_base_url'] = rtrim((string) ($app['base_url'] ?? secureit_config()['base_url']), '/') . '/' . rawurlencode($tenantKey);
    }
    $dispatchResult = secureit_github_dispatch_workflow($dispatchInputs);

    if (!empty($dispatchResult['ok'])) {
        $message = 'The SecureIT tests for ICT365 Ltd have been queued, these typically take 5 minutes, please be patient and refresh your portal.';

        secureit_flash_set('tenant_manual_report_run', [
            'ok' => true,
            'message' => $message,
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
$diagnostics = secureit_resolve_tenant_report_diagnostics($tenantKey);
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
$selectedDiagnostics = $selectedArea === null && $selectedAreaName === 'Diagnostics';

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

function secureit_tenant_control_guidance_html(array $control): string {
    $status = strtolower(trim((string) ($control['status'] ?? 'unknown')));
    $guidance = is_array($control['guidance'] ?? null) ? $control['guidance'] : [];
    $issue = trim((string) ($guidance['issue'] ?? $control['details'] ?? 'This control did not meet the expected SecureIT baseline.'));
    $impact = trim((string) ($guidance['impact'] ?? 'The result should be reviewed to understand its effect on the tenant security posture.'));
    $recommendedAction = trim((string) ($guidance['recommendedAction'] ?? 'Review the control and update the related Microsoft 365 configuration.'));
    $steps = is_array($guidance['steps'] ?? null) ? $guidance['steps'] : [];
    $reason = trim((string) ($control['reason'] ?? ''));
    $requirements = is_array($control['requirements'] ?? null) ? $control['requirements'] : [];
    $requirementItems = array_values(array_filter(
        array_map(static fn(mixed $item): string => trim((string) $item), $requirements['items'] ?? []),
        static fn(string $item): bool => $item !== ''
    ));
    $requirementSummary = trim((string) ($requirements['summary'] ?? ''));
    $bucket = trim((string) ($control['bucket'] ?? ''));
    $bucketLabel = $bucket !== '' ? secureit_control_non_scoreable_bucket_label($bucket) : '';

    ob_start();
    ?>
    <div class="control-guidance">
      <?php if ($status === 'pass'): ?>
        <div class="control-guidance-result"><strong>No remediation required.</strong> The latest evidence meets this control.</div>
        <div><strong>Control context</strong></div>
        <p><?php echo htmlspecialchars($issue . ' ' . $impact); ?></p>
      <?php elseif (!in_array($status, ['fail', 'partial'], true)): ?>
        <div class="control-guidance-result"><strong>Not scored.</strong> The latest assessment returned no scoreable evidence for this control.</div>
        <?php if ($bucketLabel !== ''): ?>
          <div><strong>Classification</strong></div>
          <p><?php echo htmlspecialchars($bucketLabel); ?></p>
        <?php endif; ?>
        <?php if ($reason !== ''): ?>
          <div><strong>Reason</strong></div>
          <p><?php echo htmlspecialchars($reason); ?></p>
        <?php endif; ?>
        <?php if ($requirementSummary !== '' || $requirementItems !== []): ?>
          <div><strong>Required to run</strong></div>
          <p><?php echo htmlspecialchars(trim($requirementSummary . ' ' . implode('; ', $requirementItems))); ?></p>
        <?php endif; ?>
        <div><strong>Control context</strong></div>
        <p><?php echo htmlspecialchars($issue . ' ' . $impact); ?></p>
      <?php else: ?>
        <div><strong>Issue and impact</strong></div>
        <p><?php echo htmlspecialchars($issue . ' ' . $impact); ?></p>
        <div><strong>Recommended action</strong></div>
        <p><?php echo htmlspecialchars($recommendedAction); ?></p>
        <?php if ($steps !== []): ?>
          <ol class="control-guidance-steps">
            <?php foreach ($steps as $step): ?>
              <?php if (!is_array($step) || trim((string) ($step['instruction'] ?? '')) === '') { continue; } ?>
              <li><span class="control-guidance-method"><?php echo htmlspecialchars((string) ($step['method'] ?? 'Action')); ?></span><?php echo htmlspecialchars((string) $step['instruction']); ?></li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function secureit_history_row_area_data(array $item): array {
    if (is_array($item['areaData'] ?? null)) {
        return $item['areaData'];
    }

    $summary = is_array($item['summary'] ?? null) ? $item['summary'] : null;
    return secureit_resolve_canonical_area_scores_from_artifact($item['embedded'] ?? null, $summary);
}

function secureit_hydrate_history_area_data(array $history): array {
    foreach ($history as &$item) {
        $item['areaData'] = secureit_history_row_area_data($item);
    }
    unset($item);

    return $history;
}

function secureit_functional_area_history_points(array $history, string $areaName): array {
    $points = [];
    foreach ($history as $item) {
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : null;
        $rowAreaData = secureit_history_row_area_data($item);
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

function secureit_tenant_history_series(array $history): array {
    $series = [
        'overall' => [
            'label' => 'Overall',
            'color' => '#0f766e',
            'fill' => '#0f766e',
            'points' => [],
        ],
    ];

    $allAreaNames = [];
    foreach ($history as $item) {
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : null;
        $rowAreaData = secureit_history_row_area_data($item);
        $overallCounts = secureit_check_summary_counts($rowAreaData);
        $series['overall']['points'][] = [
            'generatedAt' => (string) ($summary['generatedAt'] ?? ''),
            'score' => $overallCounts['score'] !== null ? (int) $overallCounts['score'] : null,
        ];

        foreach (($rowAreaData['areas'] ?? []) as $area) {
            $areaName = (string) ($area['name'] ?? '');
            if ($areaName === '') {
                continue;
            }
            $allAreaNames[$areaName] = true;
        }
    }

    $palette = [
        ['color' => '#1d4ed8', 'fill' => '#1d4ed8'],
        ['color' => '#7c3aed', 'fill' => '#7c3aed'],
        ['color' => '#c2410c', 'fill' => '#c2410c'],
        ['color' => '#b91c1c', 'fill' => '#b91c1c'],
        ['color' => '#0891b2', 'fill' => '#0891b2'],
        ['color' => '#ca8a04', 'fill' => '#ca8a04'],
        ['color' => '#15803d', 'fill' => '#15803d'],
        ['color' => '#db2777', 'fill' => '#db2777'],
    ];

    $index = 0;
    foreach (array_keys($allAreaNames) as $areaName) {
        $series[$areaName] = [
            'label' => $areaName,
            'color' => $palette[$index % count($palette)]['color'],
            'fill' => $palette[$index % count($palette)]['fill'],
            'points' => [],
            'visible' => false,
        ];
        $index++;
    }

    foreach ($history as $item) {
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : null;
        $rowAreaData = secureit_history_row_area_data($item);
        $areaScores = [];
        foreach (($rowAreaData['areas'] ?? []) as $area) {
            $areaName = (string) ($area['name'] ?? '');
            if ($areaName === '') {
                continue;
            }
            $areaScores[$areaName] = $area['score'] !== null ? (int) $area['score'] : null;
        }

        foreach ($series as $key => &$definition) {
            if ($key === 'overall') {
                continue;
            }
            $definition['points'][] = [
                'generatedAt' => (string) ($summary['generatedAt'] ?? ''),
                'score' => $areaScores[$key] ?? null,
            ];
        }
        unset($definition);
    }

    return $series;
}

function secureit_series_points(array $series): array {
    $points = [];
    foreach (($series['points'] ?? []) as $point) {
        if (($point['score'] ?? null) !== null) {
            $points[] = [
                'generatedAt' => (string) ($point['generatedAt'] ?? ''),
                'score' => (int) $point['score'],
            ];
        }
    }

    $indexedPoints = [];
    foreach ($points as $index => $point) {
        $indexedPoints[] = [
            'index' => $index,
            'point' => $point,
        ];
    }
    usort($indexedPoints, static function (array $left, array $right): int {
        $leftDate = (string) ($left['point']['generatedAt'] ?? '');
        $rightDate = (string) ($right['point']['generatedAt'] ?? '');
        $leftTimestamp = secureit_graph_point_timestamp($leftDate);
        $rightTimestamp = secureit_graph_point_timestamp($rightDate);
        if ($leftTimestamp !== null && $rightTimestamp !== null && $leftTimestamp !== $rightTimestamp) {
            return $leftTimestamp <=> $rightTimestamp;
        }
        if ($leftDate === $rightDate) {
            return $left['index'] <=> $right['index'];
        }
        if ($leftDate === '') {
            return 1;
        }
        if ($rightDate === '') {
            return -1;
        }
        return strcmp($leftDate, $rightDate);
    });

    $points = array_map(static fn (array $item): array => $item['point'], $indexedPoints);

    return $points;
}

function secureit_graph_point_timestamp(string $value): ?int {
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
}

function secureit_graph_axis_date(?string $value): string {
    if (!$value) {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('d/m');
    } catch (Throwable $e) {
        return substr($value, 0, 5);
    }
}

function secureit_line_graph_card(string $title, array $series, array $options = []): string {
    $width = 640;
    $height = (int) ($options['height'] ?? 160);
    $showLegend = (bool) ($options['showLegend'] ?? true);
    $showSubtitle = (bool) ($options['showSubtitle'] ?? true);
    $showLatestPoint = (bool) ($options['showLatestPoint'] ?? true);
    $controlsHtml = (string) ($options['controlsHtml'] ?? '');
    $controlsWidth = (int) ($options['controlsWidth'] ?? 258);
    $paddingX = 34;
    $paddingY = 28;
    $plotWidth = $width - ($paddingX * 2);
    $plotHeight = $height - ($paddingY * 2);
    $activeSeries = [];
    foreach ($series as $key => $definition) {
        $points = secureit_series_points($definition);
        if ($points === []) {
            continue;
        }
        $activeSeries[$key] = [
            'label' => (string) ($definition['label'] ?? $key),
            'color' => (string) ($definition['color'] ?? '#0f766e'),
            'fill' => (string) ($definition['fill'] ?? '#0f766e'),
            'points' => $points,
            'visible' => (bool) ($definition['visible'] ?? true),
        ];
    }

    if ($activeSeries === []) {
        return '<article class="card panel" style="margin-bottom:18px;"><div class="empty-state" style="box-shadow:none;"><strong>No trend data yet.</strong><p class="muted">A score trend will appear once SecureIT has a few historical runs.</p></div></article>';
    }

    $gridLines = '';
    foreach ([0, 25, 50, 75, 100] as $mark) {
        $y = $paddingY + ($plotHeight - (($mark / 100) * $plotHeight));
        $gridLines .= '<line x1="' . $paddingX . '" y1="' . number_format($y, 2, '.', '') . '" x2="' . ($width - $paddingX) . '" y2="' . number_format($y, 2, '.', '') . '" stroke="rgba(15, 23, 42, 0.08)" stroke-width="1"/>';
        $gridLines .= '<text x="12" y="' . number_format($y + 4, 2, '.', '') . '" fill="#6b7c77" font-size="11" font-family="Arial,Helvetica,sans-serif">' . $mark . '%</text>';
    }

    $axisLabels = '';
    $axisSeries = $activeSeries['overall'] ?? reset($activeSeries);
    $axisPoints = is_array($axisSeries) ? ($axisSeries['points'] ?? []) : [];
    $axisCount = is_array($axisPoints) ? count($axisPoints) : 0;
    if ($axisCount > 0) {
        $axisStep = $axisCount > 1 ? $plotWidth / ($axisCount - 1) : 0;
        $axisY = $height - $paddingY;
        $axisLabels .= '<line x1="' . $paddingX . '" y1="' . number_format($axisY, 2, '.', '') . '" x2="' . ($width - $paddingX) . '" y2="' . number_format($axisY, 2, '.', '') . '" stroke="rgba(15, 23, 42, 0.18)" stroke-width="1"/>';
        foreach ($axisPoints as $index => $point) {
            $x = $paddingX + ($axisStep * $index);
            $axisLabels .= '<line x1="' . number_format($x, 2, '.', '') . '" y1="' . number_format($axisY, 2, '.', '') . '" x2="' . number_format($x, 2, '.', '') . '" y2="' . number_format($axisY + 5, 2, '.', '') . '" stroke="rgba(15, 23, 42, 0.18)" stroke-width="1"/>';
            $axisLabels .= '<text x="' . number_format($x, 2, '.', '') . '" y="' . number_format($height - 8, 2, '.', '') . '" text-anchor="middle" fill="#6b7c77" font-size="11" font-family="Arial,Helvetica,sans-serif">' . htmlspecialchars(secureit_graph_axis_date($point['generatedAt'] ?? null)) . '</text>';
        }
    }

    $svgSeries = '';
    $legend = '';
    foreach ($activeSeries as $key => $definition) {
        $visible = (bool) ($definition['visible'] ?? true);
        $points = $definition['points'];
        $count = count($points);
        $step = $count > 1 ? $plotWidth / ($count - 1) : 0;
        $linePoints = [];
        $plotPoints = [];
        foreach ($points as $index => $point) {
            $x = $paddingX + ($step * $index);
            $y = $paddingY + ($plotHeight - (($point['score'] / 100) * $plotHeight));
            $linePoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
            $plotPoints[] = [$x, $y, (int) $point['score']];
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
            $dots .= '<circle cx="' . number_format($x, 2, '.', '') . '" cy="' . number_format($y, 2, '.', '') . '" r="4" fill="' . htmlspecialchars($definition['color']) . '" stroke="#ffffff" stroke-width="2"><title>' . htmlspecialchars($definition['label'] . ' - ' . $label . ' - ' . $score . '%') . '</title></circle>';
        }

        $seriesMarkup = ($fillPath !== '' ? '<path d="' . htmlspecialchars($fillPath) . '" fill="' . htmlspecialchars($definition['fill']) . '" fill-opacity="0.08" stroke="none"/>' : '')
            . '<path d="' . htmlspecialchars('M ' . implode(' L ', $linePoints)) . '" fill="none" stroke="' . htmlspecialchars($definition['color']) . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
            . $dots;
        $svgSeries .= '<g data-series-key="' . htmlspecialchars((string) $key) . '" style="' . ($visible ? '' : 'display:none;') . '">' . $seriesMarkup . '</g>';

        $legend .= '<span class="badge" style="display:inline-flex; align-items:center; gap:8px; background:#f7faf9; border:1px solid #dbe8e2; color:#102d2a;">'
            . '<span style="width:10px; height:10px; border-radius:999px; background:' . htmlspecialchars($definition['color']) . '; display:inline-block;"></span>'
            . htmlspecialchars((string) $definition['label'])
            . '</span>';
    }

    $latestSeries = reset($activeSeries);
    $latestPoints = is_array($latestSeries) ? ($latestSeries['points'] ?? []) : [];
    $latestPoint = is_array($latestPoints) ? end($latestPoints) : null;
    $latestScore = is_array($latestPoint) ? (int) ($latestPoint['score'] ?? 0) : 0;
    $latestLabel = is_array($latestPoint) ? secureit_format_datetime($latestPoint['generatedAt'] ?? null) : '';

    return '<article class="card panel" style="margin-bottom:18px; padding:20px 20px 18px;">'
        . '<div class="section-header" style="margin-bottom:14px; align-items:flex-start;">'
        . '<div>'
        . '<h3 class="section-title" style="font-size:1.08rem; margin-bottom:4px;">' . htmlspecialchars($title) . '</h3>'
        . ($showSubtitle ? '<div class="muted">Overall score is shown by default. Select functional-area lines to compare overlays.</div>' : '')
        . '</div>'
        . '<div class="badge tone-good">Latest ' . htmlspecialchars((string) $latestScore) . '%</div>'
        . '</div>'
        . ($showLegend && $legend !== '' ? '<div class="inline-links" style="flex-wrap:wrap; gap:8px; margin-bottom:12px;">' . $legend . '</div>' : '')
        . '<div style="' . ($controlsHtml !== '' ? 'display:flex; gap:22px; align-items:flex-start; flex-wrap:nowrap;' : '') . '">'
        . '<div style="' . ($controlsHtml !== '' ? 'flex:1 1 auto; min-width:0;' : '') . '">'
        . '<svg data-trend-chart="1" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . htmlspecialchars($title) . '" style="width:100%; height:auto; display:block; overflow:visible;">'
        . '<defs><linearGradient id="overallTrendFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#0f766e" stop-opacity="0.28"/><stop offset="100%" stop-color="#0f766e" stop-opacity="0.02"/></linearGradient></defs>'
        . $gridLines
        . $svgSeries
        . $axisLabels
        . '</svg>'
        . ($showLatestPoint ? '<div class="muted" style="margin-top:10px;">Latest plotted point: ' . htmlspecialchars($latestLabel) . '.</div>' : '')
        . '</div>'
        . ($controlsHtml !== '' ? '<div data-trend-controls="1" style="flex:0 0 ' . $controlsWidth . 'px; min-width:' . $controlsWidth . 'px; align-self:stretch;">' . $controlsHtml . '</div>' : '')
        . '</div>'
        . '</article>';
}

function secureit_functional_area_trend_card(string $areaName, array $points): string {
    $series = [
        'area' => [
            'label' => $areaName,
            'color' => '#0f766e',
            'fill' => '#0f766e',
            'points' => $points,
        ],
    ];
    return secureit_line_graph_card('Score trend - ' . $areaName, $series, ['height' => 160]);
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
    $controlsNotAssessed = (int) ($area['controlsNotAssessed'] ?? $area['controlsUnmapped'] ?? 0);
    $testsTotal = (int) ($area['testsTotal'] ?? 0);
    $testsPassed = (int) ($area['testsPassed'] ?? 0);
    $testsFailed = (int) ($area['testsFailed'] ?? 0);
    $testsNotAssessed = (int) ($area['testsNotAssessed'] ?? $area['testsSkipped'] ?? 0);

    $summary = [];
    $summary[] = sprintf(
        'This area scores %s across %d assessed checks.',
        $score !== null ? (string) $score . '%' : 'unavailable',
        $controlsPassing + $controlsPartial + $controlsFailing
    );
    $summary[] = sprintf(
        '%d checks passed, %d were partially met, and %d failed.',
        $controlsPassing,
        $controlsPartial,
        $controlsFailing
    );

    if ($controlsNotAssessed > 0) {
        $summary[] = sprintf(
            '%d additional %s not assessed and %s excluded from the score.',
            $controlsNotAssessed,
            $controlsNotAssessed === 1 ? 'check was' : 'checks were',
            $controlsNotAssessed === 1 ? 'was' : 'were'
        );
    }

    if ($testsTotal > 0) {
        $summary[] = sprintf(
            'Those checks are backed by %d underlying assessment items with %d passed, %d failed, and %d not assessed.',
            $testsTotal,
            $testsPassed,
            $testsFailed,
            $testsNotAssessed
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
$history = array_slice($history, 0, 10);
$history = secureit_hydrate_history_area_data($history);
$selectedAreaHistory = $selectedArea ? secureit_functional_area_history_points($history, (string) ($selectedArea['name'] ?? '')) : [];
$overviewTrendSeries = $selectedArea ? [] : secureit_tenant_history_series($history);
$selectedOverviewTrendOverall = true;

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
    <div class="section-header" style="margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:nowrap;">
      <div style="min-width:0; flex:1 1 auto;">
        <h2 class="section-title" style="white-space:nowrap;"><?php echo $selectedArea ? 'Area Posture' : 'Current posture'; ?></h2>
      </div>
<?php if (!$selectedArea && !$selectedDiagnostics): ?>
        <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end; flex:0 0 auto;">
          <form method="post" action="tenant.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>" style="margin:0;">
            <button type="submit" name="run_latest_report" value="1" style="white-space:nowrap; padding:14px 18px; min-width:150px;">Run tests now</button>
          </form>
          <?php if ($summary): ?>
            <a class="button" href="report-download.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>" style="background:#0f766e; color:#fff; box-shadow:none; white-space:nowrap; padding:10px 14px; min-width:150px;">Download results</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (is_array($manualReportRunNotice ?? null)): ?>
      <div class="<?php echo !empty($manualReportRunNotice['ok']) ? 'success' : 'error'; ?>" style="margin-bottom:16px;">
        <?php echo htmlspecialchars((string) ($manualReportRunNotice['message'] ?? '')); ?>
      </div>
    <?php endif; ?>

    <?php if ($summary): ?>
      <?php
        $displayScore = $selectedArea ? ($selectedArea['score'] ?? null) : ($counts['score'] ?? null);
        $displayScoreWidth = $displayScore !== null ? max(0, min(100, (int) $displayScore)) : 0;
      ?>
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
      <div class="muted" style="margin-bottom:8px;"><?php echo $selectedArea ? 'Area score' : 'Overall score'; ?></div>
      <div class="progress" aria-label="SecureIT score progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $displayScoreWidth); ?>%"></div></div>
      <div class="muted" style="margin-top:8px; margin-bottom:14px;">
        <?php if ($displayScore !== null): ?>
          <?php echo htmlspecialchars((string) $displayScore); ?>% SecureIT score<?php echo $selectedArea ? ' in this area' : ''; ?>, based only on assessed controls, on <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.
        <?php else: ?>
          Score unavailable because no controls returned a scoreable result on <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?>.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="empty-state" style="box-shadow:none;">
        <strong>No published report yet.</strong>
        <p class="muted">Once SecureIT publishes a latest summary for this tenant, the posture snapshot will appear here.</p>
      </div>
    <?php endif; ?>
  </article>
</section>
<?php if (!$selectedArea && !$selectedDiagnostics): ?>
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

<?php if (!$selectedArea && !$selectedDiagnostics): ?>
  <section class="section">
    <div class="section-header">
      <div>
        <h2 class="section-title">Diagnostics</h2>
        <div class="muted">Open the diagnostics drill-down for controls that are unmapped, skipped, not run, or errored.</div>
      </div>
    </div>
    <a class="card feature-card" href="tenant.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>&area=Diagnostics" style="display:block; text-decoration:none; color:inherit;">
      <div class="inline-links" style="justify-content:space-between; align-items:flex-start; margin-bottom:8px; gap:12px;">
        <span style="display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:16px; background:#eef7f6; box-shadow:0 10px 20px rgba(15, 118, 110, 0.10); color:#0f766e; flex:0 0 auto; border:1px solid rgba(15, 23, 42, 0.08);">
          <span style="width:24px; height:24px; display:flex; align-items:center; justify-content:center;">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/><circle cx="10.5" cy="10.5" r="5.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M10.5 8v5M8 10.5h5"/></svg>
          </span>
        </span>
        <span class="badge tone-neutral">Diagnostics</span>
      </div>
      <h3 style="min-height:2.8em;">Diagnostics</h3>
      <p style="margin-top:0; color:var(--muted); line-height:1.6;">Review the checks that need attention without affecting the main functional-area scorecards.</p>
    </a>
  </section>
<?php endif; ?>

<?php if ($selectedDiagnostics): ?>
  <?php
    $diagnosticControls = array_values(array_filter($diagnostics['controls'] ?? [], 'is_array'));
    $diagnosticStatusCounts = $diagnostics['statusCounts'] ?? [];
  ?>
  <section class="section">
    <article class="card panel" style="margin-bottom:18px;">
      <div class="section-header" style="margin-bottom:14px; align-items:flex-start;">
        <div>
          <h3 class="section-title" style="font-size:1.2rem;">Diagnostics checks</h3>
          <div class="muted">Controls that were unmapped, skipped, errored, or not run in the latest production run.</div>
        </div>
        <div class="inline-links">
          <a class="button" href="tenant.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>" style="background:var(--brand); color:#fff; box-shadow:none;">Back to overview</a>
          <a class="textlink" href="report-diagnostics.php?tenant=<?php echo htmlspecialchars(rawurlencode($tenantKey)); ?>">Open JSON diagnostics</a>
        </div>
      </div>

      <div class="stats-row" style="margin-bottom:16px;">
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) count($diagnosticControls)); ?></strong><span>Diagnostics items</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($diagnosticStatusCounts['unmapped'] ?? 0)); ?></strong><span>Unmapped</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($diagnosticStatusCounts['skipped'] ?? 0)); ?></strong><span>Skipped</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($diagnosticStatusCounts['not_run'] ?? 0)); ?></strong><span>Not run</span></div>
        <div class="stat-chip"><strong><?php echo htmlspecialchars((string) ($diagnosticStatusCounts['error'] ?? 0)); ?></strong><span>Error</span></div>
      </div>

      <?php if ($diagnosticControls !== []): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Control</th>
                <th>Area</th>
                <th>Status</th>
                <th>Reason</th>
                <th>Required to run</th>
                <th>What to do next</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($diagnosticControls as $control): ?>
                <?php
                  $requirements = is_array($control['requirements'] ?? null) ? $control['requirements'] : [];
                  $requirementItems = array_values(array_filter(
                      array_map(static fn(mixed $item): string => trim((string) $item), $requirements['items'] ?? []),
                      static fn(string $item): bool => $item !== ''
                  ));
                  $requirementSummary = trim((string) ($requirements['summary'] ?? ''));
                  $required = trim($requirementSummary . ' ' . implode('; ', $requirementItems));
                ?>
                <tr>
                  <td>
                    <strong><?php echo htmlspecialchars((string) ($control['title'] ?? $control['id'] ?? 'Control')); ?></strong><br>
                    <span class="muted"><?php echo htmlspecialchars((string) ($control['id'] ?? '')); ?></span>
                  </td>
                  <td><?php echo htmlspecialchars((string) ($control['functionalArea'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars(str_replace('_', ' ', strtoupper((string) ($control['status'] ?? 'unknown')))); ?></td>
                  <td><?php echo htmlspecialchars((string) ($control['reason'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars($required !== '' ? $required : 'No additional prerequisites recorded.'); ?></td>
                  <td><?php echo htmlspecialchars((string) ($control['nextStep'] ?? 'Review the latest artifact and rerun the workflow.')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state" style="box-shadow:none;">
          <strong>No diagnostics items yet.</strong>
          <p class="muted">When a control fails, is skipped, is not run, or returns no scoreable evidence, it will appear here with its reason and prerequisites.</p>
        </div>
      <?php endif; ?>
    </article>
  </section>
<?php endif; ?>

<?php if (!$selectedArea && !$selectedDiagnostics): ?>
  <section class="section">
    <?php
      $overviewColors = [
          '#1d4ed8',
          '#7c3aed',
          '#c2410c',
          '#b91c1c',
          '#0891b2',
          '#ca8a04',
          '#15803d',
          '#db2777',
      ];
      $overviewSeriesForGraph = [];
      if (isset($overviewTrendSeries['overall'])) {
          $overviewSeriesForGraph['overall'] = $overviewTrendSeries['overall'];
      }
      $overviewSeriesForGraph['overall']['visible'] = $selectedOverviewTrendOverall;
      $overviewControlsHtml = '<div style="display:flex; flex-direction:column; gap:1px; align-items:stretch; padding-top:2px;">'
          . '<label style="display:flex; align-items:center; gap:6px; justify-content:flex-start; padding:1px 0; color:#102d2a; font-size:0.76rem; font-weight:600; line-height:1; cursor:pointer;">'
          . '<input'
          . ' type="checkbox"'
          . ' name="trend_overall"'
          . ' value="1"'
          . ($selectedOverviewTrendOverall ? ' checked' : '')
          . ' data-series-key="overall"'
          . ' style="width:12px; height:12px; accent-color:#0f766e; margin:0; flex:0 0 auto;"'
          . '>'
          . '<span style="flex:1 1 auto;">Overall</span>'
          . '</label>';

      foreach ($functionalAreas as $index => $area) {
          $areaName = (string) ($area['name'] ?? '');
          if ($areaName === '') {
              continue;
          }
          $areaSeries = is_array($overviewTrendSeries[$areaName] ?? null) ? $overviewTrendSeries[$areaName] : [];
          $areaPoints = is_array($areaSeries['points'] ?? null) ? $areaSeries['points'] : [];
          $hasHistoryPoint = false;
          foreach ($areaPoints as $point) {
              if (($point['score'] ?? null) !== null) {
                  $hasHistoryPoint = true;
                  break;
              }
          }
          $hasData = ($area['score'] ?? null) !== null && $hasHistoryPoint;
          $color = $overviewColors[$index % count($overviewColors)];
          if ($hasData) {
              $overviewSeriesForGraph[$areaName] = [
                  'label' => $areaName,
                  'color' => $color,
                  'fill' => $color,
                  'points' => $areaPoints,
                  'visible' => false,
              ];
          }
          $overviewControlsHtml .= '<label style="display:flex; align-items:center; gap:6px; justify-content:flex-start; padding:1px 0; color:' . htmlspecialchars($hasData ? '#102d2a' : '#94a3b8') . '; font-size:0.76rem; font-weight:600; line-height:1; cursor:' . htmlspecialchars($hasData ? 'pointer' : 'not-allowed') . '; opacity:' . htmlspecialchars($hasData ? '1' : '0.42') . ';">'
              . '<input'
              . ' type="checkbox"'
              . ' name="trend_area[]"'
              . ' value="' . htmlspecialchars($areaName) . '"'
              . ' title="' . htmlspecialchars($areaName) . '"'
              . ' aria-label="' . htmlspecialchars($areaName) . '"'
              . ' data-series-key="' . htmlspecialchars($areaName) . '"'
              . ($hasData ? '' : ' disabled')
              . ' style="width:12px; height:12px; accent-color:' . htmlspecialchars($hasData ? $color : '#94a3b8') . '; margin:0; flex:0 0 auto;"'
              . '>'
              . '<span style="flex:1 1 auto;">' . htmlspecialchars($areaName) . '</span>'
              . '</label>';
      }
      $overviewControlsHtml .= '</div>';
    ?>
    <?php echo secureit_line_graph_card('Tenant overview score trend', $overviewSeriesForGraph, ['height' => 213, 'showLegend' => false, 'showSubtitle' => false, 'showLatestPoint' => false, 'controlsHtml' => $overviewControlsHtml, 'controlsWidth' => 238]); ?>
  </section>
<?php endif; ?>

<?php if ($selectedArea): ?>
  <section class="section">
    <article class="card panel" style="margin-bottom:18px;">
      <div class="section-header" style="margin-bottom:14px;">
        <div>
          <h3 class="section-title" style="font-size:1.2rem;"><?php echo htmlspecialchars($selectedArea['name'] ?? 'Functional area'); ?> checks</h3>
          <div class="muted">Pass and fail detail for the selected functional area.</div>
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
                <th>Check</th>
                <th>
                  <details class="status-filter-menu" data-status-filter-menu>
                    <summary class="status-filter-trigger" aria-label="Filter checks by status">
                      <span>Status</span>
                      <span class="status-filter-caret" aria-hidden="true"></span>
                    </summary>
                    <div class="status-filter-panel" role="menu" aria-label="Filter check status">
                      <label class="status-filter-option"><input type="checkbox" data-status-filter-option="pass" checked><span class="status-filter-dot status-filter-dot-pass" aria-hidden="true"></span><span class="status-filter-option-label">Pass</span></label>
                      <label class="status-filter-option"><input type="checkbox" data-status-filter-option="partial" checked><span class="status-filter-dot status-filter-dot-partial" aria-hidden="true"></span><span class="status-filter-option-label">Partial</span></label>
                      <label class="status-filter-option"><input type="checkbox" data-status-filter-option="fail" checked><span class="status-filter-dot status-filter-dot-fail" aria-hidden="true"></span><span class="status-filter-option-label">Fail</span></label>
                      <label class="status-filter-option"><input type="checkbox" data-status-filter-option="not_assessed" checked><span class="status-filter-dot status-filter-dot-unmapped" aria-hidden="true"></span><span class="status-filter-option-label">Not assessed</span></label>
                    </div>
                  </details>
                </th>
                <th>Issue, impact and recommended action</th>
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
                  $controlStatusFilterValue = strtolower(trim($controlStatus));
                  if ($controlStatusFilterValue === '') {
                      $controlStatusFilterValue = 'unknown';
                  }
                  if (!in_array($controlStatusFilterValue, ['pass', 'partial', 'fail'], true)) {
                      $controlStatusFilterValue = 'not_assessed';
                  }
                  $controlStatusLabel = match ($controlStatus) {
                      'not_applicable' => 'Not applicable',
                      'not_run' => 'Not run',
                      'skipped' => 'Skipped',
                      'unmapped' => 'Unmapped',
                      'error' => 'Error',
                      'unknown' => 'Not assessed',
                      default => ucfirst($controlStatus),
                  };
                ?>
                <tr data-status-value="<?php echo htmlspecialchars($controlStatusFilterValue); ?>">
                  <td>
                    <strong><?php echo htmlspecialchars($control['title'] ?? $control['id'] ?? 'Check'); ?></strong>
                  </td>
                  <td><span class="badge tone-<?php echo htmlspecialchars($controlTone); ?>"><?php echo htmlspecialchars($controlStatusLabel); ?></span></td>
                  <td>
                    <?php echo secureit_tenant_control_guidance_html($control); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-state" style="box-shadow:none;">
          <strong>No checks mapped to this area.</strong>
          <p class="muted">This area currently has no canonical checks in the seeded data.</p>
        </div>
      <?php endif; ?>
    </article>
  </section>
  <section class="section">
    <?php echo secureit_functional_area_trend_card((string) ($selectedArea['name'] ?? 'Functional area'), $selectedAreaHistory); ?>
  </section>
<?php endif; ?>

<section class="section">
  <article class="card panel">
    <div class="section-header" style="margin-bottom:14px; align-items:flex-start;">
      <div>
        <h2 class="section-title"><?php echo $selectedArea ? 'Run history - ' . htmlspecialchars((string) ($selectedArea['name'] ?? 'Functional area')) : 'Run history'; ?></h2>
        <div class="muted"><?php echo $selectedArea ? 'Historical published reports for this functional area only.' : 'Historical published reports for trend review and quick drill-down. Showing the latest 10 stored runs for now.'; ?></div>
      </div>
      <div class="muted"><?php echo htmlspecialchars((string) min(10, $historyStoredCount)); ?> shown of <?php echo htmlspecialchars((string) $historyStoredCount); ?> stored run<?php echo $historyStoredCount === 1 ? '' : 's'; ?></div>
    </div>
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
                $rowAreaData = secureit_history_row_area_data($item);
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
<script>
(function () {
  const normalizeStatus = (value) => (value || '').toString().trim().toLowerCase() || 'unknown';

  document.querySelectorAll('[data-trend-controls="1"]').forEach((controls) => {
    const card = controls.closest('article');
    const chart = card ? card.querySelector('[data-trend-chart="1"]') : null;
    if (!chart) {
      return;
    }

    const groups = new Map();
    chart.querySelectorAll('[data-series-key]').forEach((group) => {
      groups.set(group.dataset.seriesKey || '', group);
    });

    function setLineVisibility(checkbox) {
      const group = groups.get(checkbox.dataset.seriesKey || '');
      if (!group) {
        return;
      }
      group.style.display = checkbox.checked && !checkbox.disabled ? '' : 'none';
    }

    controls.querySelectorAll('input[type="checkbox"][data-series-key]').forEach((checkbox) => {
      if (checkbox.disabled) {
        checkbox.checked = false;
      }
      setLineVisibility(checkbox);
      checkbox.addEventListener('change', () => setLineVisibility(checkbox));
    });
  });

  document.querySelectorAll('[data-status-filter-menu]').forEach((menu) => {
    const table = menu.closest('table');
    if (!table) {
      return;
    }

    const tableWrap = menu.closest('.table-wrap');
    const options = Array.from(menu.querySelectorAll('input[type="checkbox"][data-status-filter-option]'));
    const rows = Array.from(table.querySelectorAll('tbody tr[data-status-value]'));

    function applyFilter() {
      const activeValues = new Set(
        options
          .filter((checkbox) => checkbox.checked)
          .map((checkbox) => normalizeStatus(checkbox.dataset.statusFilterOption))
      );

      rows.forEach((row) => {
        const rowValue = normalizeStatus(row.dataset.statusValue);
        row.style.display = activeValues.has(rowValue) ? '' : 'none';
      });
    }

    options.forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        applyFilter();
      });
    });

    menu.addEventListener('toggle', () => {
      if (menu.open) {
        document.querySelectorAll('[data-status-filter-menu][open]').forEach((otherMenu) => {
          if (otherMenu !== menu) {
            otherMenu.open = false;
          }
        });
      }
      if (tableWrap) {
        tableWrap.classList.toggle('has-open-status-filter', menu.open);
      }
    });

    document.addEventListener('click', (event) => {
      if (menu.open && !menu.contains(event.target)) {
        menu.open = false;
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || !menu.open) {
        return;
      }
      menu.open = false;
      const summary = menu.querySelector('summary');
      if (summary) {
        summary.focus();
      }
    });

    applyFilter();
  });
})();
</script>
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
