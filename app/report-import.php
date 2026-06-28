<?php
require __DIR__ . '/lib.php';

function secureit_report_import_json_response(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function secureit_report_import_remove_tree(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
}

function secureit_report_import_copy_tree(string $source, string $destination): void {
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0775, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0775, true);
            }
            continue;
        }

        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        copy($item->getPathname(), $target);
    }
}

function secureit_report_import_detect_root(string $extractDir): string {
    $entries = array_values(array_filter(scandir($extractDir) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
    if (count($entries) === 1) {
        $candidate = $extractDir . DIRECTORY_SEPARATOR . $entries[0];
        if (is_dir($candidate) && file_exists($candidate . '/latest/summary.json')) {
            return $candidate;
        }
    }

    return $extractDir;
}

function secureit_report_import_load_archive_bytes(string $path): string {
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') {
        throw new RuntimeException('The uploaded bundle was empty.');
    }

    $maybeGzip = strlen($bytes) >= 2 && substr($bytes, 0, 2) === "\x1f\x8b";
    if ($maybeGzip) {
        $decoded = gzdecode($bytes);
        if ($decoded === false) {
            throw new RuntimeException('The uploaded gzip bundle could not be decompressed.');
        }

        return $decoded;
    }

    return $bytes;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    secureit_report_import_json_response(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

if (!secureit_workflow_sync_authorized()) {
    secureit_report_import_json_response(401, [
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

$tenantKey = trim(strtolower((string) (
    $_POST['tenant_key']
    ?? $_POST['tenant']
    ?? $_GET['tenant_key']
    ?? $_GET['tenant']
    ?? secureit_request_header_value(['HTTP_X_SECUREIT_TENANT_KEY', 'X-SecureIT-Tenant-Key'])
    ?? ''
)));
if ($tenantKey === '' || !secureit_valid_tenant_key($tenantKey)) {
    secureit_report_import_json_response(400, [
        'ok' => false,
        'message' => 'A valid tenant_key is required.',
    ]);
    exit;
}

if (!secureit_find_tenant($tenantKey)) {
    secureit_report_import_json_response(404, [
        'ok' => false,
        'message' => 'The requested tenant is not configured in SecureIT.',
    ]);
    exit;
}

$extractDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'secureit-import-' . bin2hex(random_bytes(8));
if (!mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
    secureit_report_import_json_response(500, [
        'ok' => false,
        'message' => 'Unable to create a temporary import directory.',
    ]);
    exit;
}

try {
    $uploadedPath = '';
    $cleanupUpload = '';
    $decompressedPath = '';

    if (isset($_FILES['bundle_tar'])) {
        $upload = $_FILES['bundle_tar'];
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The bundle_tar upload failed.');
        }

        $uploadedPath = (string) ($upload['tmp_name'] ?? '');
        if ($uploadedPath === '' || !is_uploaded_file($uploadedPath)) {
            throw new RuntimeException('The uploaded bundle could not be verified.');
        }
    } else {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || $rawBody === '') {
            throw new RuntimeException('No report bundle was received.');
        }

        $cleanupUpload = tempnam(sys_get_temp_dir(), 'secureit-bundle-');
        if ($cleanupUpload === false) {
            throw new RuntimeException('Unable to create a temporary bundle file.');
        }
        $tarPath = $cleanupUpload . '.tar.gz';
        if (!rename($cleanupUpload, $tarPath)) {
            @unlink($cleanupUpload);
            throw new RuntimeException('Unable to prepare a temporary tar file.');
        }
        $cleanupUpload = $tarPath;
        file_put_contents($cleanupUpload, $rawBody);
        $uploadedPath = $cleanupUpload;
    }

    if (!class_exists('PharData')) {
        throw new RuntimeException('PharData is not available in this PHP runtime.');
    }

    try {
        $archiveBytes = secureit_report_import_load_archive_bytes($uploadedPath);
        $tarPath = preg_replace('/\.gz$/i', '', $uploadedPath) ?: ($uploadedPath . '.tar');
        if (file_put_contents($tarPath, $archiveBytes) === false) {
            throw new RuntimeException('Unable to materialise the tar archive on disk.');
        }
        $decompressedPath = $tarPath;
        $archive = new PharData($tarPath);
    } catch (Throwable $archiveException) {
        throw new RuntimeException('The uploaded file is not a valid archive. ' . $archiveException->getMessage());
    }

    if (!$archive->extractTo($extractDir, null, true)) {
        throw new RuntimeException('The uploaded archive could not be extracted.');
    }

    $bundleRoot = secureit_report_import_detect_root($extractDir);
    $latestSummary = $bundleRoot . '/latest/summary.json';
    if (!file_exists($latestSummary)) {
        throw new RuntimeException('The imported bundle does not contain latest/summary.json.');
    }

    $destinationRoot = secureit_reports_root();
    $tenantDestination = $destinationRoot . '/' . $tenantKey;
    if (!is_dir($tenantDestination) && !mkdir($tenantDestination, 0775, true) && !is_dir($tenantDestination)) {
        throw new RuntimeException('Unable to create the tenant report directory.');
    }

    $latestSource = $bundleRoot . '/latest';
    $historySource = $bundleRoot . '/history';
    $latestDestination = $tenantDestination . '/latest';
    $historyDestination = $tenantDestination . '/history';

    secureit_report_import_remove_tree($latestDestination);
    secureit_report_import_copy_tree($latestSource, $latestDestination);
    secureit_report_import_copy_tree($historySource, $historyDestination);
    secureit_ensure_tenant_report_web_link($tenantKey);

    secureit_report_import_json_response(200, [
        'ok' => true,
        'tenantKey' => $tenantKey,
        'importedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
        'reportRoot' => $tenantDestination,
        'latestSummary' => 'latest/summary.json',
        'latestReport' => 'latest/index.html',
        'historyImported' => is_dir($historySource),
    ]);
}
catch (Throwable $exception) {
    secureit_report_import_json_response(400, [
        'ok' => false,
        'message' => $exception->getMessage(),
    ]);
}
finally {
    secureit_report_import_remove_tree($extractDir);
    if (!empty($cleanupUpload) && file_exists($cleanupUpload)) {
        @unlink($cleanupUpload);
    }
    if (!empty($decompressedPath) && file_exists($decompressedPath)) {
        @unlink($decompressedPath);
    }
}
