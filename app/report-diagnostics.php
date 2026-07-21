<?php
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$tenantKey = trim((string) ($_GET['tenant'] ?? ''));

if ($tenantKey === '' || !secureit_valid_tenant_key($tenantKey)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'A valid tenant key is required.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$tenant = secureit_find_tenant($tenantKey);
if (!$tenant) {
    http_response_code(404);
    echo json_encode([
        'tenantKey' => $tenantKey,
        'error' => 'Tenant not found.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

secureit_require_tenant_access($tenantKey);

$diagnostics = secureit_resolve_tenant_report_diagnostics($tenantKey);
$summary = $diagnostics['summary'];
$summaryCounts = $diagnostics['counts'] ?? [];
$counts = [
    'total' => (int) ($summaryCounts['total'] ?? 0),
    'assessed' => (int) ($summaryCounts['assessed'] ?? 0),
    'notAssessed' => (int) ($summaryCounts['notAssessed'] ?? 0),
    'passed' => (int) ($summaryCounts['passed'] ?? 0),
    'partial' => (int) ($summaryCounts['partial'] ?? 0),
    'failed' => (int) ($summaryCounts['failed'] ?? 0),
    'unmapped' => (int) ($summaryCounts['unmapped'] ?? 0),
];

echo json_encode([
    'tenantKey' => $diagnostics['tenantKey'],
    'tenantName' => $diagnostics['tenantName'],
    'generatedAt' => $summary['generatedAt'] ?? null,
    'testProfile' => $summary['testProfile'] ?? null,
    'reportUrl' => $summary['reportUrl'] ?? null,
    'counts' => $counts,
    'statusCounts' => $diagnostics['statusCounts'] ?? [],
    'nonScoreableGroups' => $diagnostics['nonScoreableGroups'] ?? [],
    'controls' => $diagnostics['controls'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
