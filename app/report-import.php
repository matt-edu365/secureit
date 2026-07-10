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
    $importedSummary = json_decode((string) file_get_contents($latestSummary), true);
    if (!is_array($importedSummary)) {
        $importedSummary = [];
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
    secureit_brand_report_html_tree($latestDestination, $tenantKey);
    secureit_brand_report_html_tree($historyDestination, $tenantKey);
    secureit_ensure_tenant_report_web_link($tenantKey);

    $notification = [
        'status' => 'skipped',
        'message' => 'No report email was sent.',
        'recipientMailbox' => '',
        'bundleTestTotal' => (int) ($importedSummary['total'] ?? 0),
        'bundleTestProfile' => trim((string) ($importedSummary['testProfile'] ?? '')),
    ];
    $tenant = secureit_find_tenant($tenantKey);
    $requestedRecipientMailbox = trim((string) secureit_request_header_value([
        'HTTP_X_SECUREIT_REPORT_RECIPIENT',
        'X-SecureIT-Report-Recipient',
    ]));
    if ($requestedRecipientMailbox === '') {
        $requestedRecipientMailbox = trim((string) ($_GET['email_to'] ?? $_POST['email_to'] ?? ''));
    }
    $recipientMailbox = $requestedRecipientMailbox !== ''
        ? $requestedRecipientMailbox
        : trim((string) ($tenant['emailTo'] ?? ''));
    $config = secureit_config();
    $mailTenantId = trim((string) ($config['entra_tenant_id'] ?? ''));
    $mailTenantIdSource = 'SECUREIT_ENTRA_TENANT_ID';
    if ($mailTenantId === '' && trim((string) ($config['key_vault_tenant_id'] ?? '')) !== '') {
        $mailTenantId = trim((string) ($config['key_vault_tenant_id'] ?? ''));
        $mailTenantIdSource = 'SECUREIT_KEY_VAULT_TENANT_ID';
    }

    if ($recipientMailbox === '') {
        $notification['message'] = 'No report recipient is configured for this tenant.';
    } elseif ($mailTenantId === '') {
        $notification['message'] = 'The report was imported, but no Graph mail tenant ID is configured so the HTML notification was skipped. Set SECUREIT_ENTRA_TENANT_ID, or keep SECUREIT_KEY_VAULT_TENANT_ID populated as a fallback.';
    } elseif (!secureit_entra_is_enabled()) {
        $notification['message'] = 'The report was imported, but the Entra client credentials are not configured so the HTML notification was skipped.';
    } else {
        try {
            $areaData = secureit_resolve_canonical_area_scores($tenantKey);
            $counts = secureit_check_summary_counts($areaData);
            $tenantName = trim((string) ($tenant['name'] ?? $tenantKey));
            $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
            $overviewStats = [
                'title' => 'Summary of the latest report',
                'subtitle' => 'SecureIT imported a new report bundle for ' . $tenantName . '.',
                'summary' => sprintf(
                    'The latest report for %s has been imported into SecureIT. %d checks are represented in the overview: %d passed, %d partially met, and %d failed.',
                    $tenantName,
                    $counts['total'],
                    $counts['passed'],
                    $counts['partial'],
                    $counts['failed']
                ),
                'checks' => $counts['total'],
                'passed' => $counts['passed'],
                'partial' => $counts['partial'],
                'failed' => $counts['failed'],
                'passRate' => $counts['passRate'],
            ];
            $bodyContent = secureit_mail_build_overview_html($overviewStats, [
                'brandLabel' => 'SecureIT report delivery',
                'eyebrow' => 'Tenant report',
                'headline' => 'Summary of the latest report',
                'intro' => 'The latest report has been imported into SecureIT and the overview below reflects the current posture snapshot.',
                'summaryLabel' => 'Latest report',
                'modeLabel' => 'HTML report',
                'generatedAt' => $generatedAt,
                'senderMailbox' => secureit_mail_sender_mailbox(),
                'recipientMailbox' => $recipientMailbox,
                'footerNote' => 'This notification was sent automatically after SecureIT ingested the latest report bundle.',
            ]);
            $mailResponse = secureit_entra_graph_send_mail(
                $mailTenantId,
                secureit_mail_sender_mailbox(),
                'SecureIT report summary - ' . $tenantName . ' - ' . $generatedAt,
                'HTML',
                $bodyContent,
                [$recipientMailbox],
                true
            );
            $notification = [
                'status' => 'sent',
                'message' => 'HTML report notification sent to ' . $recipientMailbox . '.',
                'recipientMailbox' => $recipientMailbox,
                'mailTenantIdSource' => $mailTenantIdSource,
                'graphRequestId' => (string) ($mailResponse['request-id'] ?? ($mailResponse['headers']['request-id'] ?? '')),
                'graphClientRequestId' => (string) ($mailResponse['clientRequestId'] ?? ''),
                'bundleTestTotal' => (int) ($importedSummary['total'] ?? 0),
                'bundleTestProfile' => trim((string) ($importedSummary['testProfile'] ?? '')),
                'scoredControlTotal' => (int) ($counts['total'] ?? 0),
            ];
        } catch (Throwable $exception) {
            $notification = [
                'status' => 'failed',
                'message' => 'The report was imported, but the HTML notification could not be sent: ' . $exception->getMessage(),
                'recipientMailbox' => $recipientMailbox,
                'mailTenantIdSource' => $mailTenantIdSource,
                'bundleTestTotal' => (int) ($importedSummary['total'] ?? 0),
                'bundleTestProfile' => trim((string) ($importedSummary['testProfile'] ?? '')),
            ];
        }
    }

    secureit_report_import_json_response(200, [
        'ok' => true,
        'tenantKey' => $tenantKey,
        'importedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
        'reportRoot' => $tenantDestination,
        'latestSummary' => 'latest/summary.json',
        'latestReport' => 'latest/index.html',
        'bundleTestTotal' => (int) ($importedSummary['total'] ?? 0),
        'bundleTestProfile' => trim((string) ($importedSummary['testProfile'] ?? '')),
        'historyImported' => is_dir($historySource),
        'notification' => $notification,
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
