<?php
require_once __DIR__ . '/lib.php';

secureit_require_admin_access();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function secureit_runtime_diag_now(): float {
    return microtime(true);
}

function secureit_runtime_diag_elapsed(float $startedAt): float {
    return round((microtime(true) - $startedAt) * 1000, 2);
}

function secureit_runtime_diag_bytes(?string $value): ?int {
    $value = trim((string) $value);
    if ($value === '' || strtolower($value) === 'unlimited') {
        return $value === '' ? null : -1;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    $multiplier = match ($unit) {
        'g' => 1024 * 1024 * 1024,
        'm' => 1024 * 1024,
        'k' => 1024,
        default => 1,
    };

    return (int) round($number * $multiplier);
}

function secureit_runtime_diag_parse_bytes(string $value): ?int {
    $value = trim($value);
    if ($value === '' || strtolower($value) === 'max') {
        return $value === '' ? null : -1;
    }

    if (!preg_match('/^(\d+)([kmgtpe]?)$/i', $value, $matches)) {
        return null;
    }

    $power = match (strtolower($matches[2] ?? '')) {
        'k' => 1,
        'm' => 2,
        'g' => 3,
        't' => 4,
        'p' => 5,
        'e' => 6,
        default => 0,
    };

    return (int) ((int) $matches[1] * (1024 ** $power));
}

function secureit_runtime_diag_file_tree(string $path, int $maxFiles = 50000): array {
    $startedAt = secureit_runtime_diag_now();
    $result = [
        'path' => $path,
        'exists' => is_dir($path),
        'files' => 0,
        'bytes' => 0,
        'directories' => 0,
        'truncated' => false,
        'durationMs' => 0,
    ];

    if (!is_dir($path) || !is_readable($path)) {
        $result['durationMs'] = secureit_runtime_diag_elapsed($startedAt);
        return $result;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $result['files']++;
            $result['bytes'] += max(0, (int) $fileInfo->getSize());
            if ($result['files'] >= $maxFiles) {
                $result['truncated'] = true;
                break;
            }
        }

        $directoryIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($directoryIterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                $result['directories']++;
            }
        }
    } catch (Throwable $exception) {
        $result['error'] = $exception->getMessage();
    }

    $result['durationMs'] = secureit_runtime_diag_elapsed($startedAt);
    return $result;
}

function secureit_runtime_diag_path(string $path): array {
    $probe = file_exists($path) ? $path : dirname($path);
    return [
        'path' => $path,
        'exists' => file_exists($path),
        'isDirectory' => is_dir($path),
        'readable' => is_readable($probe),
        'writable' => is_writable($probe),
        'sizeBytes' => is_file($path) ? (int) filesize($path) : null,
        'freeBytes' => is_dir($probe) ? @disk_free_space($probe) : null,
    ];
}

function secureit_runtime_diag_cgroup(string $path, callable $parser): mixed {
    if (!is_readable($path)) {
        return null;
    }

    try {
        return $parser(trim((string) file_get_contents($path)));
    } catch (Throwable) {
        return null;
    }
}

function secureit_runtime_diag_graph_probe(): array {
    $config = secureit_entra_config();
    $startedAt = secureit_runtime_diag_now();
    $result = [
        'requested' => true,
        'configured' => $config['tenantId'] !== '' && $config['clientId'] !== '' && $config['clientSecret'] !== '',
        'tenantIdConfigured' => $config['tenantId'] !== '',
        'clientIdConfigured' => $config['clientId'] !== '',
        'clientSecretConfigured' => $config['clientSecret'] !== '',
        'success' => false,
        'durationMs' => 0,
    ];

    if (!$result['configured']) {
        $result['error'] = 'Entra tenant ID, client ID, or client secret is not configured.';
        $result['durationMs'] = secureit_runtime_diag_elapsed($startedAt);
        return $result;
    }

    try {
        $token = secureit_entra_client_credentials_access_token(
            $config['tenantId'],
            $config['clientId'],
            $config['clientSecret'],
            'https://graph.microsoft.com/.default'
        );
        $result['success'] = $token !== '';
    } catch (Throwable $exception) {
        $result['error'] = $exception->getMessage();
    }

    $result['durationMs'] = secureit_runtime_diag_elapsed($startedAt);
    return $result;
}

$config = secureit_config();
$dataRoot = dirname((string) ($config['tenants_file'] ?? __DIR__ . '/../data/tenants.json'));
$reportsRoot = (string) ($config['reports_root'] ?? $dataRoot . '/reports');
$tenantKey = strtolower(trim((string) ($_GET['tenant'] ?? '')));
$includeScore = in_array(strtolower(trim((string) ($_GET['score'] ?? ''))), ['1', 'true', 'yes'], true);
$includeGraph = in_array(strtolower(trim((string) ($_GET['graph'] ?? ''))), ['1', 'true', 'yes'], true);
$startedAt = secureit_runtime_diag_now();

$loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
$memoryLimit = ini_get('memory_limit');
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);
$tenant = $tenantKey !== '' ? secureit_find_tenant($tenantKey) : null;

$response = [
    'ok' => true,
    'generatedAt' => gmdate(DATE_ATOM),
    'request' => [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'remoteAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
        'tenant' => $tenantKey !== '' ? $tenantKey : null,
        'scoreProbeRequested' => $includeScore,
        'graphProbeRequested' => $includeGraph,
    ],
    'runtime' => [
        'phpVersion' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'hostname' => gethostname() ?: '',
        'pid' => function_exists('getmypid') ? getmypid() : null,
        'loadAverage' => $loadAverage,
        'memoryLimit' => $memoryLimit,
        'memoryLimitBytes' => secureit_runtime_diag_bytes($memoryLimit),
        'memoryUsageBytes' => $memoryUsage,
        'memoryPeakBytes' => $memoryPeak,
        'memoryAvailableBytes' => secureit_runtime_diag_bytes($memoryLimit) > 0 ? max(0, secureit_runtime_diag_bytes($memoryLimit) - $memoryPeak) : null,
        'maxExecutionTimeSeconds' => (int) ini_get('max_execution_time'),
        'maxInputTimeSeconds' => (int) ini_get('max_input_time'),
        'postMaxSize' => ini_get('post_max_size'),
        'uploadMaxFilesize' => ini_get('upload_max_filesize'),
        'extensions' => [
            'curl' => extension_loaded('curl'),
            'dom' => extension_loaded('dom'),
            'gd' => extension_loaded('gd'),
            'mbstring' => extension_loaded('mbstring'),
            'opcache' => extension_loaded('Zend OPcache'),
        ],
    ],
    'hostLimits' => [
        'loadavg1' => @file_get_contents('/proc/loadavg') ?: null,
        'memory' => [
            'limitBytes' => secureit_runtime_diag_cgroup('/sys/fs/cgroup/memory.max', 'secureit_runtime_diag_parse_bytes'),
            'currentBytes' => secureit_runtime_diag_cgroup('/sys/fs/cgroup/memory.current', 'secureit_runtime_diag_parse_bytes'),
            'events' => @file_get_contents('/sys/fs/cgroup/memory.events') ?: null,
        ],
        'cpu' => [
            'quota' => @file_get_contents('/sys/fs/cgroup/cpu.max') ?: null,
            'weight' => @file_get_contents('/sys/fs/cgroup/cpu.weight') ?: null,
        ],
        'pids' => [
            'current' => secureit_runtime_diag_cgroup('/sys/fs/cgroup/pids.current', static fn(string $value): ?int => is_numeric($value) ? (int) $value : null),
            'limit' => secureit_runtime_diag_cgroup('/sys/fs/cgroup/pids.max', static fn(string $value): mixed => $value === 'max' ? -1 : (is_numeric($value) ? (int) $value : null)),
        ],
    ],
    'paths' => [
        'tenantsFile' => secureit_runtime_diag_path((string) ($config['tenants_file'] ?? '')),
        'reportsRoot' => secureit_runtime_diag_path($reportsRoot),
        'canonicalControls' => secureit_runtime_diag_path((string) ($config['canonical_controls_file'] ?? '')),
        'temporaryDirectory' => secureit_runtime_diag_path(sys_get_temp_dir()),
    ],
    'configuration' => [
        'baseUrl' => (string) ($config['base_url'] ?? ''),
        'entraAuthority' => (string) ($config['entra_authority'] ?? ''),
        'entraTenantIdConfigured' => trim((string) ($config['entra_tenant_id'] ?? '')) !== '',
        'entraClientIdConfigured' => trim((string) ($config['entra_client_id'] ?? '')) !== '',
        'entraClientSecretConfigured' => trim((string) ($config['entra_client_secret'] ?? '')) !== '',
        'keyVaultTenantIdConfigured' => trim((string) ($config['key_vault_tenant_id'] ?? '')) !== '',
        'workflowSyncTokenConfigured' => trim((string) ($config['workflow_sync_token'] ?? '')) !== '',
        'mailSenderMailbox' => secureit_mail_sender_mailbox(),
    ],
    'timings' => [],
];

$writeProbeStartedAt = secureit_runtime_diag_now();
$probePath = rtrim($dataRoot, '/\\') . '/.secureit-runtime-diagnostic-' . bin2hex(random_bytes(8));
$probePayload = str_repeat('x', 1024 * 1024);
$writeOk = @file_put_contents($probePath, $probePayload, LOCK_EX) === strlen($probePayload);
$readOk = false;
if ($writeOk) {
    $readOk = @file_get_contents($probePath) === $probePayload;
}
@unlink($probePath);
$response['probes']['mountedDataWriteRead'] = [
    'write' => $writeOk,
    'read' => $readOk,
    'payloadBytes' => strlen($probePayload),
    'durationMs' => secureit_runtime_diag_elapsed($writeProbeStartedAt),
];

$response['probes']['reportsRootInventory'] = secureit_runtime_diag_file_tree($reportsRoot);
if ($tenantKey !== '') {
    $tenantReportsRoot = rtrim($reportsRoot, '/\\') . '/' . $tenantKey;
    $response['probes']['tenantLatestInventory'] = secureit_runtime_diag_file_tree($tenantReportsRoot . '/latest');
    $response['probes']['tenantHistoryInventory'] = secureit_runtime_diag_file_tree($tenantReportsRoot . '/history');
    $response['tenant'] = [
        'found' => is_array($tenant),
        'name' => is_array($tenant) ? (string) ($tenant['name'] ?? '') : '',
        'emailConfigured' => is_array($tenant) && trim((string) ($tenant['emailTo'] ?? '')) !== '',
        'latestSummaryMtime' => is_file($tenantReportsRoot . '/latest/summary.json') ? gmdate(DATE_ATOM, (int) filemtime($tenantReportsRoot . '/latest/summary.json')) : null,
    ];

    if ($includeScore) {
        $scoreStartedAt = secureit_runtime_diag_now();
        try {
            $areaData = secureit_resolve_canonical_area_scores($tenantKey);
            $response['probes']['canonicalScore'] = [
                'success' => true,
                'areas' => count($areaData['areas'] ?? []),
                'durationMs' => secureit_runtime_diag_elapsed($scoreStartedAt),
            ];
        } catch (Throwable $exception) {
            $response['probes']['canonicalScore'] = [
                'success' => false,
                'error' => $exception->getMessage(),
                'durationMs' => secureit_runtime_diag_elapsed($scoreStartedAt),
            ];
        }
    }
}

if ($includeGraph) {
    $response['probes']['graphToken'] = secureit_runtime_diag_graph_probe();
}

$response['timings']['totalMs'] = secureit_runtime_diag_elapsed($startedAt);
$response['timings']['memoryPeakBytes'] = memory_get_peak_usage(true);

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
