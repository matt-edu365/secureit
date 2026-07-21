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
    'nonScoreableBuckets' => $diagnostics['nonScoreableBuckets'] ?? [],
    'todoControls' => $diagnostics['areaData']['todoControls'] ?? [],
    'controls' => $diagnostics['controls'] ?? [],
    'remediationSummary' => [
        'missing_permissions' => [
            'label' => secureit_control_non_scoreable_bucket_label('missing_permissions'),
            'description' => secureit_control_non_scoreable_bucket_description('missing_permissions'),
            'nextStep' => 'Grant the required Graph/API permissions, then rerun production.',
        ],
        'missing_license' => [
            'label' => secureit_control_non_scoreable_bucket_label('missing_license'),
            'description' => secureit_control_non_scoreable_bucket_description('missing_license'),
            'nextStep' => 'Enable or license the required feature, then rerun production.',
        ],
        'separate_feature' => [
            'label' => secureit_control_non_scoreable_bucket_label('separate_feature'),
            'description' => secureit_control_non_scoreable_bucket_description('separate_feature'),
            'nextStep' => 'Move this control into the separate feature workflow or expose the tenant feature/configuration it needs.',
        ],
        'other' => [
            'label' => 'Other',
            'description' => secureit_control_non_scoreable_bucket_description('other'),
            'nextStep' => 'Review the latest artifact and control output to identify the missing prerequisite or failure path.',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
