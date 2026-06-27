<?php
require __DIR__ . '/lib.php';

function secureit_workflow_sync_tenant_payload(array $tenant): array {
    $authMode = strtolower(trim((string) ($tenant['authMode'] ?? '')));
    $missing = [];

    $tenantKey = trim((string) ($tenant['id'] ?? ''));
    $tenantName = trim((string) ($tenant['name'] ?? ''));
    $tenantId = trim((string) ($tenant['tenantId'] ?? ''));
    $clientId = trim((string) ($tenant['clientId'] ?? ''));
    $reportBaseUrl = trim((string) ($tenant['reportBaseUrl'] ?? ''));
    $tenantDomain = trim((string) ($tenant['tenantDomain'] ?? ''));
    $officialTenantName = trim((string) ($tenant['m365TenantName'] ?? ''));
    $emailTo = trim((string) ($tenant['emailTo'] ?? ''));

    if ($tenantKey === '') {
        $missing[] = 'tenant key';
    }
    if ($tenantName === '') {
        $missing[] = 'dashboard label';
    }
    if ($tenantId === '') {
        $missing[] = 'tenant ID';
    }
    if ($clientId === '') {
        $missing[] = 'client ID';
    }
    if ($authMode === '') {
        $missing[] = 'authentication mode';
    }
    if ($reportBaseUrl === '') {
        $missing[] = 'report base URL';
    }

    if ($authMode === 'client-secret') {
        if (trim((string) ($tenant['clientSecretName'] ?? '')) === '') {
            $missing[] = 'client secret name';
        }
    } elseif ($authMode === 'certificate') {
        if (trim((string) ($tenant['certificateSecretName'] ?? '')) === '') {
            $missing[] = 'certificate secret name';
        }
        if (trim((string) ($tenant['certificatePasswordSecretName'] ?? '')) === '') {
            $missing[] = 'certificate password secret name';
        }
    } elseif ($authMode !== '') {
        $missing[] = 'unsupported auth mode: ' . $authMode;
    }

    $requiredMissing = array_values(array_unique($missing));

    return [
        'tenantKey' => $tenantKey,
        'tenantName' => $tenantName,
        'tenantId' => $tenantId,
        'clientId' => $clientId,
        'authMode' => $authMode,
        'clientSecretName' => trim((string) ($tenant['clientSecretName'] ?? '')),
        'certificateSecretName' => trim((string) ($tenant['certificateSecretName'] ?? '')),
        'certificatePasswordSecretName' => trim((string) ($tenant['certificatePasswordSecretName'] ?? '')),
        'tenantDomain' => $tenantDomain,
        'm365TenantName' => $officialTenantName,
        'reportBaseUrl' => $reportBaseUrl,
        'emailTo' => $emailTo,
        'ready' => $requiredMissing === [],
        'missing' => $requiredMissing,
    ];
}

if (!secureit_workflow_sync_authorized()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$config = secureit_load_tenants();
$tenants = $config['tenants'] ?? [];
$requestedTenantKey = trim(strtolower((string) ($_GET['tenant_key'] ?? $_GET['tenant'] ?? '')));
$readyOnly = filter_var($_GET['ready'] ?? false, FILTER_VALIDATE_BOOL);

$payloadTenants = [];
foreach ($tenants as $tenant) {
    if (!is_array($tenant)) {
        continue;
    }

    $payload = secureit_workflow_sync_tenant_payload($tenant);
    if ($requestedTenantKey !== '' && $payload['tenantKey'] !== $requestedTenantKey) {
        continue;
    }
    if ($readyOnly && empty($payload['ready'])) {
        continue;
    }

    $payloadTenants[] = $payload;
}

if ($requestedTenantKey !== '' && $payloadTenants === []) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'No tenant matched the requested key.',
        'tenantKey' => $requestedTenantKey,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
    'count' => count($payloadTenants),
    'readyCount' => count(array_filter($payloadTenants, static fn (array $tenant): bool => !empty($tenant['ready']))),
    'tenants' => $payloadTenants,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
