<?php
require __DIR__ . '/lib.php';
require_once __DIR__ . '/../shared/report-pdf.php';

$tenantKey = trim((string) ($_GET['tenant'] ?? ''));
$tenant = secureit_find_tenant($tenantKey);

if (!$tenant || !secureit_valid_tenant_key($tenantKey)) {
    http_response_code(404);
    echo 'Tenant not found';
    exit;
}

secureit_require_tenant_access($tenantKey);

$summary = secureit_tenant_summary($tenantKey);
if (!$summary) {
    http_response_code(404);
    echo 'No report summary is available yet for this tenant.';
    exit;
}

$tenantName = trim((string) ($tenant['name'] ?? $tenantKey));
$areaData = secureit_resolve_canonical_area_scores($tenantKey);
$counts = secureit_check_summary_counts($areaData);
$generatedAt = secureit_format_datetime($summary['generatedAt'] ?? null);

try {
    $html = secureit_report_build_html($tenantName, $generatedAt, $summary, $areaData, $counts);
    $pdf = secureit_report_render_pdf($html, $tenantName, $generatedAt);
} catch (Throwable $exception) {
    error_log('SecureIT PDF generation failed for tenant ' . $tenantKey . ': ' . $exception->getMessage());
    http_response_code(500);
    echo 'SecureIT could not generate this report. Please try again or contact ICT365 support.';
    exit;
}

$downloadName = 'secureit-latest-report-' . $tenantKey . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
