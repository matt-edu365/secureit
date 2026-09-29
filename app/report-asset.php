<?php
require __DIR__ . '/lib.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$tenantKey = trim(strtolower((string) ($_GET['tenant'] ?? '')));
$relativePath = (string) ($_GET['path'] ?? '');
$tenant = secureit_find_tenant($tenantKey);

if (!$tenant || !secureit_valid_tenant_key($tenantKey)) {
    http_response_code(404);
    echo 'Report not found';
    exit;
}

secureit_require_tenant_access($tenantKey, '/login.php?denied=1');

$assetPath = secureit_tenant_report_asset_path($tenantKey, $relativePath);
if ($assetPath === null) {
    http_response_code(404);
    echo 'Report asset not found';
    exit;
}

$contentType = secureit_report_asset_content_type($assetPath);
header('Content-Type: ' . $contentType);
header('Content-Length: ' . (string) filesize($assetPath));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');

if (str_starts_with($contentType, 'text/html')) {
    header('Content-Security-Policy: ' . secureit_report_asset_content_security_policy());
}

if ($method === 'HEAD') {
    exit;
}

readfile($assetPath);
