<?php

require __DIR__ . '/../app/lib.php';
require __DIR__ . '/../shared/report-pdf.php';

function secureit_report_test_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$areas = [];
foreach (secureit_functional_area_catalog() as $index => $catalogArea) {
    $controls = [];
    if ($index === 0) {
        $controls = [
            ['title' => 'Passed control', 'status' => 'pass', 'details' => 'This control meets the baseline.'],
            ['title' => 'Failed control', 'status' => 'fail', 'details' => 'This control needs remediation.'],
            ['title' => 'Partial control', 'status' => 'partial', 'details' => 'This control needs follow-up.'],
            ['title' => 'Coverage control', 'status' => 'unmapped', 'details' => 'This control has no result.'],
        ];
    }

    $areas[] = [
        'name' => $catalogArea['name'],
        'status' => $index === 0 ? 'Needs attention' : 'No data',
        'tone' => $index === 0 ? 'bad' : 'neutral',
        'score' => $index === 0 ? 25 : null,
        'controlsTotal' => count($controls),
        'controlsPassing' => $index === 0 ? 1 : 0,
        'controlsPartial' => $index === 0 ? 1 : 0,
        'controlsFailing' => $index === 0 ? 1 : 0,
        'controlsUnmapped' => $index === 0 ? 1 : 0,
        'controls' => $controls,
    ];
}

$summary = [
    'generatedAt' => '2026-07-20T09:00:00+01:00',
    'total' => 4,
    'passed' => 1,
    'failed' => 1,
    'skipped' => 1,
];
$areaData = ['areas' => $areas];
$counts = secureit_check_summary_counts($areaData);
$html = secureit_report_build_html('Example & Tenant', '20 Jul 2026, 09:00', $summary, $areaData, $counts);

secureit_report_test_assert(str_contains($html, 'Microsoft 365 Security Assessment'), 'The report title is missing.');
secureit_report_test_assert(str_contains($html, 'Example &amp; Tenant'), 'The tenant name is not HTML escaped.');
secureit_report_test_assert(str_contains($html, 'data:image/png;base64,'), 'The ICT365 SecureIT logo is not embedded.');
secureit_report_test_assert(str_contains($html, 'Executive summary'), 'The executive summary is missing.');
secureit_report_test_assert(str_contains($html, 'Area breakdown'), 'The area overview is missing.');
secureit_report_test_assert(substr_count($html, 'class="area-detail"') === 8, 'The report must include all eight functional areas.');
secureit_report_test_assert(str_contains($html, 'Action required'), 'Priority controls are missing.');
secureit_report_test_assert(str_contains($html, 'Assessment coverage gaps'), 'Coverage gaps are missing.');
secureit_report_test_assert(str_contains($html, 'Controls meeting the baseline'), 'Passing controls are missing.');
secureit_report_test_assert(str_contains($html, 'area-summary-bad'), 'Functional-area score traffic-light styling is missing.');
secureit_report_test_assert(!str_contains($html, 'class="eyebrow"'), 'Uppercase section labels must not be rendered.');
secureit_report_test_assert(!str_contains($html, 'a clear, way'), 'The known copy error is present.');

if (extension_loaded('gd')) {
    $pdf = secureit_report_render_pdf($html, 'Example & Tenant', '20 Jul 2026, 09:00');
    secureit_report_test_assert(str_starts_with($pdf, '%PDF-'), 'The renderer did not return a PDF document.');
    secureit_report_test_assert(strlen($pdf) > 10000, 'The rendered PDF is unexpectedly small.');
}

echo "SecureIT PDF report test passed.\n";
