<?php

function secureit_report_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function secureit_report_logo_data_uri(): string {
    static $logoDataUri = null;
    if ($logoDataUri !== null) {
        return $logoDataUri;
    }

    $logoPath = __DIR__ . '/../Logo_1.png';
    if (!is_readable($logoPath)) {
        $logoDataUri = '';
        return $logoDataUri;
    }

    $logoData = file_get_contents($logoPath);
    $logoDataUri = $logoData === false ? '' : 'data:image/png;base64,' . base64_encode($logoData);
    return $logoDataUri;
}

function secureit_report_status_key(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'pass', 'passed', 'healthy' => 'pass',
        'partial', 'partially met', 'watch' => 'partial',
        'fail', 'failed', 'needs attention' => 'fail',
        'unmapped', 'not assessed', 'no data' => 'unmapped',
        default => 'unknown',
    };
}

function secureit_report_status_label(string $status): string {
    return match (secureit_report_status_key($status)) {
        'pass' => 'PASS',
        'partial' => 'PARTIAL',
        'fail' => 'FAIL',
        'unmapped' => 'NOT ASSESSED',
        default => 'UNKNOWN',
    };
}

function secureit_report_area_tone(array $area): string {
    return match (strtolower(trim((string) ($area['tone'] ?? '')))) {
        'good' => 'good',
        'warn' => 'warn',
        'bad' => 'bad',
        default => 'neutral',
    };
}

function secureit_report_area_description(string $areaName): string {
    foreach (secureit_functional_area_catalog() as $area) {
        if (($area['name'] ?? '') === $areaName) {
            return (string) ($area['description'] ?? '');
        }
    }

    return 'SecureIT reviews the Microsoft 365 services, policies, checks, and related settings mapped to this area.';
}

function secureit_report_area_insight(array $area): string {
    $failed = (int) ($area['controlsFailing'] ?? 0);
    $partial = (int) ($area['controlsPartial'] ?? 0);
    $unmapped = (int) ($area['controlsUnmapped'] ?? 0);
    $passed = (int) ($area['controlsPassing'] ?? 0);

    if ($failed > 0) {
        $message = $failed . ' ' . ($failed === 1 ? 'control requires' : 'controls require') . ' remediation. Address failed controls first';
        if ($partial > 0) {
            $message .= ', then complete the ' . $partial . ' partially met ' . ($partial === 1 ? 'control' : 'controls');
        }
        return $message . '.';
    }

    if ($partial > 0) {
        return 'No assessed controls failed. Complete the remaining work on ' . $partial . ' partially met ' . ($partial === 1 ? 'control' : 'controls') . ' to strengthen this area.';
    }

    if ($passed > 0 && $unmapped === 0) {
        return 'All assessed controls in this area meet the current SecureIT baseline.';
    }

    if ($unmapped > 0) {
        return $unmapped . ' ' . ($unmapped === 1 ? 'control has' : 'controls have') . ' no result in the latest assessment. Review assessment coverage before treating this area as complete.';
    }

    return 'No controls are currently available for assessment in this functional area.';
}

function secureit_report_area_score(array $area): string {
    return ($area['score'] ?? null) === null ? 'N/A' : ((int) $area['score']) . '%';
}

function secureit_report_group_controls(array $area): array {
    $groups = [
        'attention' => [],
        'coverage' => [],
        'passing' => [],
    ];

    foreach (($area['controls'] ?? []) as $control) {
        if (!is_array($control)) {
            continue;
        }

        $status = secureit_report_status_key((string) ($control['status'] ?? ''));
        if (in_array($status, ['fail', 'partial'], true)) {
            $groups['attention'][] = $control;
        } elseif ($status === 'pass') {
            $groups['passing'][] = $control;
        } else {
            $groups['coverage'][] = $control;
        }
    }

    usort($groups['attention'], static function (array $left, array $right): int {
        $rank = ['fail' => 0, 'partial' => 1];
        $leftRank = $rank[secureit_report_status_key((string) ($left['status'] ?? ''))] ?? 2;
        $rightRank = $rank[secureit_report_status_key((string) ($right['status'] ?? ''))] ?? 2;
        return $leftRank <=> $rightRank ?: strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    foreach (['coverage', 'passing'] as $groupName) {
        usort($groups[$groupName], static fn (array $left, array $right): int => strcasecmp(
            (string) ($left['title'] ?? $left['id'] ?? ''),
            (string) ($right['title'] ?? $right['id'] ?? '')
        ));
    }

    return $groups;
}

function secureit_report_ranked_area(array $areas, bool $strongest): ?array {
    $ranked = array_values(array_filter($areas, static fn (array $area): bool => is_numeric($area['score'] ?? null)));
    if ($ranked === []) {
        return null;
    }

    usort($ranked, static function (array $left, array $right) use ($strongest): int {
        $comparison = ((int) $left['score']) <=> ((int) $right['score']);
        if ($strongest) {
            $comparison *= -1;
        }
        return $comparison ?: strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $ranked[0];
}

function secureit_report_top_priorities(array $areas, int $limit = 5): array {
    $priorities = [];
    foreach ($areas as $area) {
        foreach (($area['controls'] ?? []) as $control) {
            if (!is_array($control)) {
                continue;
            }
            $status = secureit_report_status_key((string) ($control['status'] ?? ''));
            if (!in_array($status, ['fail', 'partial'], true)) {
                continue;
            }
            $priorities[] = [
                'area' => (string) ($area['name'] ?? 'Functional area'),
                'areaScore' => is_numeric($area['score'] ?? null) ? (int) $area['score'] : 101,
                'title' => (string) ($control['title'] ?? $control['id'] ?? 'Control'),
                'details' => trim((string) ($control['details'] ?? 'Review this control against the SecureIT baseline.')),
                'status' => $status,
            ];
        }
    }

    usort($priorities, static function (array $left, array $right): int {
        $rank = ['fail' => 0, 'partial' => 1];
        return ($rank[$left['status']] ?? 2) <=> ($rank[$right['status']] ?? 2)
            ?: $left['areaScore'] <=> $right['areaScore']
            ?: strcasecmp($left['area'], $right['area'])
            ?: strcasecmp($left['title'], $right['title']);
    });

    return array_slice($priorities, 0, max(0, $limit));
}

function secureit_report_control_table(string $heading, string $intro, array $controls, bool $breakBefore = false): string {
    if ($controls === []) {
        return '';
    }

    $rows = '';
    foreach ($controls as $control) {
        $title = (string) ($control['title'] ?? $control['id'] ?? 'Control');
        $status = secureit_report_status_key((string) ($control['status'] ?? ''));
        $details = trim((string) ($control['details'] ?? 'SecureIT reviews the available report evidence and uses it to determine this control status.'));
        $rows .= '<tr>';
        $rows .= '<td class="control-title">' . secureit_report_escape($title) . '</td>';
        $rows .= '<td><span class="table-status status-' . secureit_report_escape($status) . '">' . secureit_report_escape(secureit_report_status_label($status)) . '</span></td>';
        $rows .= '<td>' . secureit_report_escape($details) . '</td>';
        $rows .= '</tr>';
    }

    $className = $breakBefore ? 'control-group break-before' : 'control-group';

    return '<div class="' . $className . '">'
        . '<h3>' . secureit_report_escape($heading) . '</h3>'
        . '<p class="group-intro">' . secureit_report_escape($intro) . '</p>'
        . '<table class="control-table">'
        . '<thead><tr><th class="col-control">Test name</th><th class="col-status">Status</th><th class="col-description">Description</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table></div>';
}

function secureit_report_passing_summary(array $controls): string {
    if ($controls === []) {
        return '';
    }

    $rows = '';
    foreach (array_chunk($controls, 2) as $controlRow) {
        $rows .= '<tr>';
        foreach ($controlRow as $control) {
            $title = (string) ($control['title'] ?? $control['id'] ?? 'Control');
            $rows .= '<td><span class="passing-name">' . secureit_report_escape($title) . '</span><span class="table-status status-pass">PASS</span></td>';
        }
        if (count($controlRow) === 1) {
            $rows .= '<td></td>';
        }
        $rows .= '</tr>';
    }

    return '<div class="control-group passing-group">'
        . '<h3>Controls meeting the baseline</h3>'
        . '<p class="group-intro">Passing controls are summarised by name below the priority results. Their full interactive evidence remains available in the SecureIT portal.</p>'
        . '<table class="passing-table"><tbody>' . $rows . '</tbody></table>'
        . '</div>';
}

function secureit_report_build_html(string $tenantName, string $generatedAt, array $summary, array $areaData, array $counts): string {
    $areas = array_values(array_filter($areaData['areas'] ?? [], 'is_array'));
    $runDate = secureit_format_date_only($summary['generatedAt'] ?? null);
    $score = (int) ($counts['passRate'] ?? 0);
    $overallStatus = secureit_functional_area_status_from_score(($counts['total'] ?? 0) > 0 ? $score : null);
    $strongest = secureit_report_ranked_area($areas, true);
    $weakest = secureit_report_ranked_area($areas, false);
    $priorities = secureit_report_top_priorities($areas);
    $unmapped = (int) ($counts['unmapped'] ?? 0);
    $reportMonth = $runDate !== 'Not available' ? date('F Y', strtotime((string) ($summary['generatedAt'] ?? 'now'))) : date('F Y');
    $logoDataUri = secureit_report_logo_data_uri();

    ob_start();
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo secureit_report_escape('Microsoft 365 Security Assessment - ' . $tenantName); ?></title>
  <style>
    @page { margin: 23mm 14mm 19mm; }
    @page:first { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #0e2841; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5pt; line-height: 1.48; }
    p { margin: 0 0 8pt; }
    h1, h2, h3 { margin: 0; color: #00635f; font-weight: 700; }
    h1 { margin-bottom: 12pt; font-size: 23pt; line-height: 1.12; }
    h2 { margin-bottom: 7pt; font-size: 15pt; line-height: 1.2; }
    h3 { margin-bottom: 4pt; font-size: 11pt; line-height: 1.25; }
    .muted { color: #52697a; }
    .cover { position: relative; width: 210mm; height: 297mm; overflow: hidden; page-break-after: always; background: #ffffff; color: #0e2841; }
    .cover-accent { height: 5mm; background: #00635f; border-bottom: 2mm solid #339997; }
    .cover-content { padding: 28mm 22mm 0; }
    .cover-logo { height: 18mm; }
    .cover-logo img { width: 106mm; height: auto; }
    .cover-logo-fallback { color: #00635f; font-size: 22pt; font-weight: 700; }
    .cover-title { width: 168mm; margin-top: 47mm; color: #00635f; font-size: 30pt; font-weight: 700; line-height: 1.18; }
    .cover-tenant { margin-top: 13mm; color: #0e2841; font-size: 18pt; line-height: 1.25; }
    .cover-meta { margin-top: 11mm; color: #52697a; font-size: 10pt; }
    .cover-rule { width: 62mm; height: 1.2mm; margin-top: 12mm; background: #339997; }
    .cover-footer { position: absolute; right: 22mm; bottom: 16mm; left: 22mm; border-top: 1px solid #c8dadd; padding-top: 6mm; color: #52697a; font-size: 8.5pt; }
    .cover-footer-brand { float: right; color: #00635f; font-weight: 700; }
    .section-lead { margin-bottom: 14pt; color: #344f62; font-size: 10.5pt; line-height: 1.55; }
    .executive { page-break-after: always; }
    .posture-table, .metric-table, .highlight-table, .area-grid, .area-metric-table { width: 100%; border-collapse: separate; border-spacing: 5pt; margin: 0 -5pt 12pt; }
    .posture-score { width: 29%; padding: 12pt; border: 1px solid #9dcfcd; border-radius: 7px; background: #eaf6f5; text-align: center; vertical-align: middle; }
    .posture-score strong { display: block; color: #00635f; font-size: 28pt; line-height: 1; }
    .posture-score span { display: block; margin-top: 5pt; color: #00635f; font-size: 9pt; font-weight: 700; }
    .posture-copy { padding: 8pt 4pt 8pt 11pt; vertical-align: middle; }
    .posture-copy strong { display: block; margin-bottom: 3pt; color: #0e2841; font-size: 14pt; }
    .metric-cell { width: 25%; padding: 8pt; border: 1px solid #cfe3e2; border-radius: 7px; background: #f3f9f8; vertical-align: top; }
    .metric-cell.partial { border-color: #f0cf91; background: #fff7e9; }
    .metric-cell.fail { border-color: #efb6b1; background: #fff0ee; }
    .metric-label { display: block; color: #00635f; font-size: 7.5pt; font-weight: 700; text-transform: uppercase; }
    .metric-value { display: block; margin: 3pt 0; color: #0e2841; font-size: 19pt; font-weight: 700; line-height: 1; }
    .metric-note { color: #52697a; font-size: 7.5pt; }
    .highlight-cell { width: 50%; padding: 8pt 10pt; border-left: 3px solid #339997; background: #f3f7fa; vertical-align: top; }
    .highlight-label { display: block; margin-bottom: 2pt; color: #52697a; font-size: 7.5pt; font-weight: 700; text-transform: uppercase; }
    .highlight-value { color: #0e2841; font-size: 9.5pt; font-weight: 700; }
    .priority-list { margin: 2pt 0 10pt 18pt; padding: 0; }
    .priority-list li { margin-bottom: 6pt; padding-left: 3pt; color: #344f62; }
    .priority-list strong { color: #0e2841; }
    .priority-meta { color: #52697a; font-size: 8pt; }
    .notice { padding: 8pt 10pt; border-left: 3px solid #ffc000; background: #fff8e9; color: #5a4930; }
    .success-notice { padding: 8pt 10pt; border-left: 3px solid #00b050; background: #edf8f2; color: #24533a; }
    .area-overview { }
    .area-cell { width: 50%; padding: 9pt; border: 1px solid #c8dadd; border-radius: 7px; background: #f7fafb; vertical-align: top; }
    .area-cell-good { border-color: #8fd4ad; background: #eef9f2; }
    .area-cell-warn { border-color: #efca73; background: #fff8e7; }
    .area-cell-bad { border-color: #efa6a1; background: #fff0ee; }
    .area-cell-neutral { border-color: #c8dadd; background: #f3f6f7; }
    .area-name { min-height: 27pt; margin-bottom: 5pt; color: #0e2841; font-size: 10pt; font-weight: 700; line-height: 1.3; }
    .area-score { color: #00635f; font-size: 18pt; font-weight: 700; line-height: 1; }
    .area-status { float: right; margin-top: 3pt; font-size: 8pt; font-weight: 700; }
    .tone-good { color: #008443; }
    .tone-warn { color: #a66a00; }
    .tone-bad { color: #c62828; }
    .tone-neutral { color: #647784; }
    .area-bar { width: 100%; height: 5pt; margin: 7pt 0 5pt; border-collapse: collapse; background: #dfe9eb; }
    .area-bar td { height: 5pt; padding: 0; border: 0; }
    .bar-good { background: #00b050; }
    .bar-warn { background: #ffc000; }
    .bar-bad { background: #ee0000; }
    .bar-neutral { background: #9aabb4; }
    .area-counts { color: #52697a; font-size: 7.8pt; }
    .area-detail { page-break-before: always; }
    .area-summary { margin-bottom: 13pt; padding: 11pt 12pt; border-top: 4px solid #00635f; background: #eef6f6; }
    .area-summary-good { border-color: #00b050; background: #eef9f2; }
    .area-summary-warn { border-color: #ffc000; background: #fff8e7; }
    .area-summary-bad { border-color: #ee0000; background: #fff0ee; }
    .area-summary-neutral { border-color: #9aabb4; background: #f3f6f7; }
    .area-summary-score { width: 20%; color: #00635f; font-size: 23pt; font-weight: 700; vertical-align: middle; }
    .area-summary-good .area-summary-score { color: #008443; }
    .area-summary-warn .area-summary-score { color: #a66a00; }
    .area-summary-bad .area-summary-score { color: #c62828; }
    .area-summary-neutral .area-summary-score { color: #647784; }
    .area-summary-copy { width: 80%; color: #344f62; vertical-align: middle; }
    .area-summary-copy strong { display: block; margin-bottom: 2pt; color: #0e2841; font-size: 10pt; }
    .area-metric { width: 25%; padding: 6pt 7pt; border: 1px solid #d0dfe1; background: #ffffff; vertical-align: top; }
    .area-metric strong { display: block; color: #0e2841; font-size: 13pt; }
    .area-metric span { color: #52697a; font-size: 7.5pt; }
    .control-group { margin-top: 13pt; }
    .control-group.break-before { page-break-before: always; }
    .control-group h3 { page-break-after: avoid; }
    .group-intro { margin-bottom: 6pt; color: #52697a; font-size: 8.5pt; page-break-after: avoid; }
    .control-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; line-height: 1.38; }
    .control-table thead { display: table-header-group; }
    .control-table tr { page-break-inside: avoid; }
    .control-table th { padding: 7pt 6pt; border: 1px solid #00635f; background: #00635f; color: #ffffff; font-size: 8.5pt; text-align: left; vertical-align: middle; }
    .control-table td { padding: 7pt 6pt; border: 1px solid #b8cbd2; color: #344f62; vertical-align: top; }
    .control-table tbody tr:nth-child(even) td { background: #f5f8f9; }
    .control-table .col-control { width: 27.5%; }
    .control-table .col-status { width: 13%; }
    .control-table .col-description { width: 59.5%; }
    .control-title { color: #0e2841 !important; font-weight: 700; }
    .table-status { font-size: 7.5pt; font-weight: 700; }
    .status-pass { color: #008443; }
    .status-partial { color: #a66a00; }
    .status-fail { color: #d00000; }
    .status-unmapped, .status-unknown { color: #647784; }
    .passing-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.3pt; }
    .passing-table tr { page-break-inside: avoid; }
    .passing-table td { width: 50%; padding: 6pt 7pt; border: 1px solid #b8cbd2; vertical-align: middle; }
    .passing-table tr:nth-child(even) td { background: #f5f8f9; }
    .passing-name { display: inline-block; width: 80%; color: #0e2841; font-weight: 700; }
    .passing-table .table-status { float: right; }
    .empty-area { padding: 12pt; border: 1px solid #c8dadd; background: #f7fafb; color: #52697a; }
  </style>
</head>
<body>
  <section class="cover">
    <div class="cover-accent"></div>
    <div class="cover-content">
      <div class="cover-logo">
        <?php if ($logoDataUri !== ''): ?>
          <img src="<?php echo secureit_report_escape($logoDataUri); ?>" alt="ICT365 SecureIT">
        <?php else: ?>
          <span class="cover-logo-fallback">ICT365 SecureIT</span>
        <?php endif; ?>
      </div>
      <div class="cover-title">Microsoft 365<br>Security Assessment</div>
      <div class="cover-tenant"><?php echo secureit_report_escape($tenantName); ?></div>
      <div class="cover-meta">Prepared <?php echo secureit_report_escape($reportMonth); ?> &nbsp;|&nbsp; CONFIDENTIAL</div>
      <div class="cover-rule"></div>
    </div>
    <div class="cover-footer">
      <span>CONFIDENTIAL</span>
      <span class="cover-footer-brand">Microsoft 365 security, clearly reported</span>
    </div>
  </section>

  <section class="executive">
    <h1>Executive summary</h1>
    <p class="section-lead">SecureIT provides a point-in-time view of how this Microsoft 365 tenant aligns with the security controls assessed during the latest run. Results are organised into eight functional areas so that strengths, gaps, and remediation priorities are easy to identify.</p>

    <table class="posture-table"><tr>
      <td class="posture-score"><strong><?php echo $score; ?>%</strong><span>OVERALL SCORE</span></td>
      <td class="posture-copy"><strong><?php echo secureit_report_escape((string) ($overallStatus['status'] ?? 'No data')); ?></strong>The latest assessment ran on <?php echo secureit_report_escape($runDate); ?> and covered <?php echo (int) ($counts['total'] ?? 0); ?> SecureIT controls.</td>
    </tr></table>

    <table class="metric-table"><tr>
      <td class="metric-cell"><span class="metric-label">Checks</span><span class="metric-value"><?php echo (int) ($counts['total'] ?? 0); ?></span><span class="metric-note">Across the latest assessment</span></td>
      <td class="metric-cell"><span class="metric-label">Passed</span><span class="metric-value"><?php echo (int) ($counts['passed'] ?? 0); ?></span><span class="metric-note">Controls meeting the baseline</span></td>
      <td class="metric-cell partial"><span class="metric-label">Partially met</span><span class="metric-value"><?php echo (int) ($counts['partial'] ?? 0); ?></span><span class="metric-note">Controls needing follow-up</span></td>
      <td class="metric-cell fail"><span class="metric-label">Failed</span><span class="metric-value"><?php echo (int) ($counts['failed'] ?? 0); ?></span><span class="metric-note">Controls needing attention</span></td>
    </tr></table>

    <table class="highlight-table"><tr>
      <td class="highlight-cell"><span class="highlight-label">Strongest area</span><span class="highlight-value"><?php echo secureit_report_escape($strongest ? ((string) $strongest['name'] . ' (' . secureit_report_area_score($strongest) . ')') : 'Not available'); ?></span></td>
      <td class="highlight-cell"><span class="highlight-label">Lowest-scoring area</span><span class="highlight-value"><?php echo secureit_report_escape($weakest ? ((string) $weakest['name'] . ' (' . secureit_report_area_score($weakest) . ')') : 'Not available'); ?></span></td>
    </tr></table>

    <h2>Top priorities</h2>
    <?php if ($priorities !== []): ?>
      <ol class="priority-list">
        <?php foreach ($priorities as $priority): ?>
          <li><strong><?php echo secureit_report_escape($priority['title']); ?></strong> <span class="table-status status-<?php echo secureit_report_escape($priority['status']); ?>"><?php echo secureit_report_escape(secureit_report_status_label($priority['status'])); ?></span><br><span class="priority-meta"><?php echo secureit_report_escape($priority['area']); ?></span></li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="success-notice">No controls failed or partially met the baseline in this assessment. Continue monitoring the tenant and review any assessment coverage gaps below.</div>
    <?php endif; ?>

    <?php if ($unmapped > 0): ?>
      <div class="notice"><strong>Assessment coverage:</strong> <?php echo $unmapped; ?> <?php echo $unmapped === 1 ? 'control has' : 'controls have'; ?> no result in the latest run. These are shown as &quot;Not assessed&quot; and are not treated as passes.</div>
    <?php endif; ?>
  </section>

  <section class="area-overview">
    <h1>Area breakdown</h1>
    <p class="section-lead">The eight functional areas below mirror the SecureIT portal. Scores reflect the controls mapped to each area in the latest assessment.</p>
    <table class="area-grid">
      <?php foreach (array_chunk($areas, 2) as $areaRow): ?>
        <tr>
          <?php foreach ($areaRow as $area): ?>
            <?php $tone = secureit_report_area_tone($area); $areaScore = ($area['score'] ?? null) === null ? 0 : max(0, min(100, (int) $area['score'])); ?>
            <td class="area-cell area-cell-<?php echo $tone; ?>">
              <div class="area-name"><?php echo secureit_report_escape((string) ($area['name'] ?? 'Functional area')); ?></div>
              <span class="area-score"><?php echo secureit_report_escape(secureit_report_area_score($area)); ?></span>
              <span class="area-status tone-<?php echo $tone; ?>"><?php echo secureit_report_escape((string) ($area['status'] ?? 'No data')); ?></span>
              <table class="area-bar"><tr><td class="bar-<?php echo $tone; ?>" style="width:<?php echo $areaScore; ?>%"></td><td style="width:<?php echo 100 - $areaScore; ?>%"></td></tr></table>
              <div class="area-counts"><?php echo (int) ($area['controlsPassing'] ?? 0); ?> passed &nbsp; <?php echo (int) ($area['controlsPartial'] ?? 0); ?> partial &nbsp; <?php echo (int) ($area['controlsFailing'] ?? 0); ?> failed &nbsp; <?php echo (int) ($area['controlsUnmapped'] ?? 0); ?> not assessed</div>
            </td>
          <?php endforeach; ?>
          <?php if (count($areaRow) === 1): ?><td></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
    <div class="notice">Scores can change as tenant configuration, Microsoft 365 services, and the SecureIT control set evolve. Use the live portal for current interactive results and trend history.</div>
  </section>

  <?php foreach ($areas as $area): ?>
    <?php
      $areaName = (string) ($area['name'] ?? 'Functional area');
      $groups = secureit_report_group_controls($area);
      $tone = secureit_report_area_tone($area);
    ?>
    <section class="area-detail">
      <h1><?php echo secureit_report_escape($areaName); ?></h1>
      <p class="section-lead"><?php echo secureit_report_escape(secureit_report_area_description($areaName)); ?></p>

      <div class="area-summary area-summary-<?php echo $tone; ?>">
        <table><tr>
          <td class="area-summary-score"><?php echo secureit_report_escape(secureit_report_area_score($area)); ?></td>
          <td class="area-summary-copy"><strong class="tone-<?php echo $tone; ?>"><?php echo secureit_report_escape((string) ($area['status'] ?? 'No data')); ?></strong><?php echo secureit_report_escape(secureit_report_area_insight($area)); ?></td>
        </tr></table>
      </div>

      <table class="area-metric-table"><tr>
        <td class="area-metric"><strong><?php echo (int) ($area['controlsTotal'] ?? 0); ?></strong><span>Total controls</span></td>
        <td class="area-metric"><strong><?php echo (int) ($area['controlsPassing'] ?? 0); ?></strong><span>Passed</span></td>
        <td class="area-metric"><strong><?php echo (int) ($area['controlsPartial'] ?? 0) + (int) ($area['controlsFailing'] ?? 0); ?></strong><span>Need follow-up</span></td>
        <td class="area-metric"><strong><?php echo (int) ($area['controlsUnmapped'] ?? 0); ?></strong><span>Not assessed</span></td>
      </tr></table>

      <?php
        echo secureit_report_control_table(
            'Action required',
            'Failed and partially met controls are listed first so remediation work is immediately visible.',
            $groups['attention']
        );
        echo secureit_report_control_table(
            'Assessment coverage gaps',
            'These controls had no mapped result in the latest run and should be reviewed for assessment coverage.',
            $groups['coverage'],
            $groups['attention'] !== []
        );
        echo secureit_report_passing_summary($groups['passing']);
      ?>

      <?php if (($area['controls'] ?? []) === []): ?>
        <div class="empty-area">No checks are currently mapped to this functional area, so SecureIT cannot calculate an area score.</div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</body>
</html>
    <?php
    return (string) ob_get_clean();
}

function secureit_report_render_pdf(string $html, string $tenantName, string $generatedAt): string {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new RuntimeException('The PDF renderer is not installed. Build the SecureIT image or run Composer install.');
    }
    require_once $autoloadPath;

    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false);
    $options->set('isRemoteEnabled', false);
    $options->set('chroot', [dirname(__DIR__)]);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $dompdf->add_info('Title', 'Microsoft 365 Security Assessment - ' . $tenantName);
    $dompdf->add_info('Author', 'ICT365 SecureIT');
    $dompdf->add_info('Subject', 'Microsoft 365 security posture assessment');

    $tenantHeader = mb_strlen($tenantName) > 54 ? mb_substr($tenantName, 0, 51) . '...' : $tenantName;
    $canvas = $dompdf->getCanvas();
    $fontMetrics = $dompdf->getFontMetrics();
    $canvas->page_script(static function (int $pageNumber, int $pageCount, $pageCanvas, $metrics) use ($tenantHeader, $generatedAt): void {
        if ($pageNumber === 1) {
            return;
        }

        $regular = $metrics->getFont('DejaVu Sans', 'normal');
        $bold = $metrics->getFont('DejaVu Sans', 'bold');
        $width = $pageCanvas->get_width();
        $height = $pageCanvas->get_height();
        $teal = [0.0, 0.388, 0.373];
        $secondary = [0.2, 0.6, 0.592];
        $navy = [0.055, 0.157, 0.255];
        $muted = [0.322, 0.412, 0.478];

        $pageCanvas->filled_rectangle(0, 0, $width, 13, $teal);
        $pageCanvas->filled_rectangle(0, 13, $width, 3, $secondary);
        $pageCanvas->text(40, 28, 'Microsoft 365 Security Assessment', $bold, 7.5, $teal);
        $pageCanvas->text(40, 39, $tenantHeader, $regular, 7, $muted);
        $pageCanvas->line(40, $height - 35, $width - 40, $height - 35, [0.78, 0.85, 0.87], 0.6);
        $pageCanvas->text(40, $height - 25, 'ICT365 | SecureIT', $bold, 7.5, $teal);
        $pageCanvas->text(198, $height - 25, 'helpdesk@ict365.ky  |  +1 (345) 745-0365  |  ict365.ky', $regular, 6.8, $navy);
        $pageCanvas->text($width - 70, $height - 25, $pageNumber . ' / ' . $pageCount, $regular, 6.8, $muted);
    });

    return $dompdf->output();
}
