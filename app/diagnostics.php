<?php
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/keyvault.php';
secureit_require_admin_access();

function secureit_diag_yes_no(bool $value): string {
    return $value ? 'yes' : 'no';
}

function secureit_diag_set(string $value): string {
    return trim($value) !== '' ? 'set' : 'not set';
}

function secureit_diag_path_line(string $label, string $path): string {
    $exists = file_exists($path);
    $isDir = is_dir($path);
    $probePath = $exists ? $path : dirname($path);
    $readable = is_readable($probePath);
    $writable = is_writable($probePath);

    return sprintf(
        '%s: %s | exists=%s | type=%s | readable=%s | writable=%s',
        $label,
        $path,
        secureit_diag_yes_no($exists),
        $isDir ? 'dir' : 'file',
        secureit_diag_yes_no($readable),
        secureit_diag_yes_no($writable)
    );
}

function secureit_diag_env_line(string $label, string $value, bool $redact = false): string {
    if ($redact) {
        return sprintf('%s: %s', $label, secureit_diag_set($value));
    }

    return sprintf('%s: %s', $label, trim($value) !== '' ? $value : '[not set]');
}

function secureit_diag_json_line(string $label, mixed $value): string {
    if ($value === null || $value === '' || $value === []) {
        return sprintf('%s: [not set]', $label);
    }

    if (is_bool($value)) {
        return sprintf('%s: %s', $label, secureit_diag_yes_no($value));
    }

    if (is_scalar($value)) {
        return sprintf('%s: %s', $label, (string) $value);
    }

    return sprintf('%s: %s', $label, json_encode($value, JSON_UNESCAPED_SLASHES));
}

function secureit_diag_header_value(array $headers, string $name): string {
    $name = strtolower(trim($name));
    if ($name === '') {
        return '';
    }

    foreach ($headers as $headerName => $headerValue) {
        if (strtolower((string) $headerName) === $name) {
            return trim((string) $headerValue);
        }
    }

    return '';
}

function secureit_diag_default_email_recipient(): string {
    return 'secureit@ict365.ky';
}

function secureit_diag_email_overview_stats(): array {
    $checks = 84;
    $passed = 72;
    $partial = 9;
    $failed = 3;
    $passRate = $checks > 0 ? (int) round(($passed / $checks) * 100) : 0;
    $statusLabel = 'Watch';
    $statusTone = 'warn';

    return [
        'title' => 'Tenant overview snapshot',
        'subtitle' => 'Dummy statistics for HTML rendering tests. These mirror the tenant overview summary cards.',
        'summary' => 'Most controls are healthy, with a few items still needing review before the next weekly run.',
        'statusLabel' => $statusLabel,
        'statusTone' => $statusTone,
        'checks' => $checks,
        'passed' => $passed,
        'partial' => $partial,
        'failed' => $failed,
        'passRate' => $passRate,
    ];
}

function secureit_diag_build_email_body(string $modeLabel, string $generatedAt, string $senderMailbox, string $recipientMailbox): string {
    $lines = [
        'SecureIT diagnostics email test',
        'Mode: ' . $modeLabel,
        'Generated at: ' . $generatedAt,
        'Sender mailbox: ' . $senderMailbox,
        'Recipient mailbox: ' . $recipientMailbox,
        'This is a Graph app-only sendMail test from the SecureIT diagnostics page.',
    ];

    return implode("\n", $lines) . "\n";
}

function secureit_diag_build_email_html_body(string $modeLabel, string $generatedAt, string $senderMailbox, string $recipientMailbox, array $overviewStats): string {
    return secureit_mail_build_overview_html($overviewStats, [
        'brandLabel' => 'SecureIT diagnostics',
        'eyebrow' => 'Tenant overview',
        'headline' => 'Illustrative tenant overview',
        'intro' => 'This is a dummy summary that mirrors the SecureIT tenant overview cards and pass-rate block so you can verify HTML rendering in the mailbox.',
        'summaryLabel' => 'Summary',
        'modeLabel' => $modeLabel,
        'generatedAt' => $generatedAt,
        'senderMailbox' => $senderMailbox,
        'recipientMailbox' => $recipientMailbox,
        'footerNote' => 'SecureIT diagnostics mail test. The numbers above are dummy values and are intended only to validate HTML rendering, formatting, and Graph mail delivery.',
    ]);
}

function secureit_diag_build_email_test_report(array $state): string {
    $lines = [];
    $lines[] = 'SecureIT email send test';
    $lines[] = 'Mode: ' . ($state['modeLabel'] ?? '[not set]');
    $lines[] = 'Outcome: ' . ($state['outcomeLabel'] ?? '[not set]');
    $lines[] = 'Attempted at: ' . ($state['attemptedAt'] ?? '[not set]');
    $lines[] = 'Mail tenant source: ' . ($state['mailTenantSource'] ?? '[not set]');
    $lines[] = 'Mail tenant ID: ' . ($state['mailTenantId'] !== '' ? $state['mailTenantId'] : '[not set]');
    $lines[] = 'Sender mailbox: ' . ($state['senderMailbox'] ?? '[not set]');
    $lines[] = 'Recipient mailbox: ' . ($state['recipientMailbox'] ?? '[not set]');
    $lines[] = 'Graph endpoint: ' . ($state['endpoint'] ?? '[not set]');
    $lines[] = 'Subject: ' . ($state['subject'] ?? '[not set]');
    $lines[] = 'Body content type: ' . ($state['bodyContentType'] ?? '[not set]');
    $lines[] = 'Save to sent items: ' . secureit_diag_yes_no((bool) ($state['saveToSentItems'] ?? false));
    $lines[] = 'Body length: ' . strlen((string) ($state['bodyContent'] ?? ''));
    $lines[] = '';
    $lines[] = '[Message body]';
    $bodyContent = trim((string) ($state['bodyContent'] ?? ''));
    $lines[] = $bodyContent !== '' ? $bodyContent : '[not set]';

    if (!empty($state['errors'])) {
        $lines[] = '';
        $lines[] = '[Errors]';
        foreach ((array) $state['errors'] as $error) {
            $lines[] = '- ' . $error;
        }
    }

    if (!empty($state['response']) && is_array($state['response'])) {
        $response = $state['response'];
        $responseHeaders = is_array($response['headers'] ?? null) ? $response['headers'] : [];
        $lines[] = '';
        $lines[] = '[Graph response]';
        $lines[] = 'HTTP status: ' . ($response['status'] ?? '[not set]');
        $lines[] = 'Request ID: ' . (secureit_diag_header_value($responseHeaders, 'request-id') ?: '[not set]');
        $lines[] = 'Client request ID: ' . (secureit_diag_header_value($responseHeaders, 'client-request-id') ?: ($response['clientRequestId'] ?? '[not set]'));
        $lines[] = 'Date: ' . (secureit_diag_header_value($responseHeaders, 'date') ?: '[not set]');
        $lines[] = 'Response headers:';
        $lines[] = json_encode($responseHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[not available]';
        $lines[] = 'Response body:';
        $responseBody = trim((string) ($response['body'] ?? ''));
        $lines[] = $responseBody !== '' ? $responseBody : '[empty]';
    }

    return implode("\n", $lines) . "\n";
}

function secureit_diag_tenant_registry_status(array $tenant): array {
    $requiredFields = [
        'id' => 'tenant key',
        'tenantId' => 'Microsoft 365 tenant ID',
        'clientId' => 'Microsoft 365 application ID',
        'authMode' => 'authentication mode',
        'reportBaseUrl' => 'report base URL',
    ];

    $present = [];
    $missing = [];
    foreach ($requiredFields as $field => $label) {
        $value = trim((string) ($tenant[$field] ?? ''));
        if ($value === '') {
            $missing[] = $label;
        } else {
            $present[] = $label;
        }
    }

    $authMode = strtolower(trim((string) ($tenant['authMode'] ?? '')));
    $secretFields = [];
    if ($authMode === 'client-secret') {
        $secretFields = [
            'clientSecretName' => 'client secret name',
        ];
    } elseif ($authMode === 'certificate') {
        $secretFields = [
            'certificateSecretName' => 'certificate secret name',
            'certificatePasswordSecretName' => 'certificate password secret name',
        ];
    } elseif ($authMode === '') {
        $missing[] = 'authentication mode';
    } else {
        $missing[] = 'authentication mode (unsupported: ' . $authMode . ')';
    }

    foreach ($secretFields as $field => $label) {
        $value = trim((string) ($tenant[$field] ?? ''));
        if ($value === '') {
            $missing[] = $label;
        } else {
            $present[] = $label;
        }
    }

    $tenantDomain = trim((string) ($tenant['tenantDomain'] ?? ''));
    $officialTenantName = trim((string) ($tenant['m365TenantName'] ?? ''));
    $resolvedTenantDomain = '';
    $resolvedTenantDomainMessage = '';
    $resolvedOfficialTenantName = '';
    $resolvedOfficialTenantNameMessage = '';
    if ($tenantDomain === '' || $officialTenantName === '') {
        $tenantId = trim((string) ($tenant['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $tenantIdentityLookup = secureit_entra_resolve_tenant_identity($tenantId);
            $resolvedTenantDomain = trim((string) ($tenantIdentityLookup['domain'] ?? ''));
            $resolvedOfficialTenantName = trim((string) ($tenantIdentityLookup['displayName'] ?? ''));
            $resolvedTenantDomainMessage = trim((string) ($tenantIdentityLookup['message'] ?? ''));
            $resolvedOfficialTenantNameMessage = trim((string) ($tenantIdentityLookup['message'] ?? ''));
        }
    } else {
        $resolvedTenantDomain = $tenantDomain;
        $resolvedTenantDomainMessage = 'Loaded from the tenant registry.';
        $resolvedOfficialTenantName = $officialTenantName;
        $resolvedOfficialTenantNameMessage = 'Loaded from the tenant registry.';
    }

    $effectiveTenantDomain = $tenantDomain !== '' ? $tenantDomain : $resolvedTenantDomain;
    $effectiveOfficialTenantName = $officialTenantName !== '' ? $officialTenantName : $resolvedOfficialTenantName;
    $tenantDomainSource = 'not available';
    $tenantDomainLookupStatus = 'not attempted';
    $officialTenantNameSource = 'not available';
    $officialTenantNameLookupStatus = 'not attempted';
    if ($tenantDomain !== '') {
        $tenantDomainSource = 'stored in tenant registry';
        $tenantDomainLookupStatus = 'not needed';
    } elseif ($resolvedTenantDomain !== '') {
        $tenantDomainSource = 'resolved from Microsoft Graph';
        $tenantDomainLookupStatus = 'succeeded';
    } elseif ($tenantId !== '') {
        $tenantDomainSource = 'lookup attempted';
        $tenantDomainLookupStatus = 'failed';
    }
    if ($officialTenantName !== '') {
        $officialTenantNameSource = 'stored in tenant registry';
        $officialTenantNameLookupStatus = 'not needed';
    } elseif ($resolvedOfficialTenantName !== '') {
        $officialTenantNameSource = 'resolved from Microsoft Graph';
        $officialTenantNameLookupStatus = 'succeeded';
    } elseif ($tenantId !== '') {
        $officialTenantNameSource = 'lookup attempted';
        $officialTenantNameLookupStatus = 'failed';
    }

    $emailTo = trim((string) ($tenant['emailTo'] ?? ''));
    $recommendedMissing = [];
    if ($emailTo === '') {
        $recommendedMissing[] = 'report recipient email';
    }
    if ($effectiveTenantDomain === '') {
        $recommendedMissing[] = 'tenant domain (optional, but useful for Exchange-specific runs)';
    }
    if ($effectiveOfficialTenantName === '') {
        $recommendedMissing[] = 'official Microsoft 365 tenant name (optional but useful for onboarding records)';
    }

    $tenantName = trim((string) ($tenant['name'] ?? ''));
    if ($tenantName === '') {
        $recommendedMissing[] = 'dashboard label';
    }

    return [
        'tenantKey' => trim((string) ($tenant['id'] ?? '')),
        'tenantName' => $tenantName,
        'tenantId' => trim((string) ($tenant['tenantId'] ?? '')),
        'clientId' => trim((string) ($tenant['clientId'] ?? '')),
        'officialTenantName' => $officialTenantName,
        'authMode' => $authMode,
        'ready' => $missing === [],
        'missing' => array_values(array_unique($missing)),
        'present' => array_values(array_unique($present)),
        'recommendedMissing' => array_values(array_unique($recommendedMissing)),
        'clientSecretName' => trim((string) ($tenant['clientSecretName'] ?? '')),
        'certificateSecretName' => trim((string) ($tenant['certificateSecretName'] ?? '')),
        'certificatePasswordSecretName' => trim((string) ($tenant['certificatePasswordSecretName'] ?? '')),
        'tenantDomain' => $tenantDomain,
        'resolvedTenantDomain' => $resolvedTenantDomain,
        'effectiveTenantDomain' => $effectiveTenantDomain,
        'resolvedOfficialTenantName' => $resolvedOfficialTenantName,
        'effectiveOfficialTenantName' => $effectiveOfficialTenantName,
        'tenantDomainSource' => $tenantDomainSource,
        'tenantDomainLookupStatus' => $tenantDomainLookupStatus,
        'officialTenantNameSource' => $officialTenantNameSource,
        'officialTenantNameLookupStatus' => $officialTenantNameLookupStatus,
        'resolvedTenantDomainMessage' => $resolvedTenantDomainMessage,
        'resolvedOfficialTenantNameMessage' => $resolvedOfficialTenantNameMessage,
        'reportBaseUrl' => trim((string) ($tenant['reportBaseUrl'] ?? '')),
        'emailTo' => $emailTo,
    ];
}

$config = secureit_config();
$dataRoot = dirname((string) ($config['tenants_file'] ?? '/var/www/data/tenants.json'));
$tenantsPath = (string) ($config['tenants_file'] ?? ($dataRoot . '/tenants.json'));
$adminConfigPath = $dataRoot . '/admin-config.json';
$canonicalControlsPath = (string) ($config['canonical_controls_file'] ?? ($dataRoot . '/canonical-controls.json'));

$phpExtensions = [
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl'),
];

$seedMessages = [];
$seedErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_runtime_files'])) {
    $createdFiles = [];

    if (!file_exists($tenantsPath)) {
        $dir = dirname($tenantsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($tenantsPath, json_encode(['tenants' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $createdFiles[] = 'tenants.json';
    }

    if (!file_exists($adminConfigPath)) {
        $adminPayload = [
            'azure' => [
                'keyVaultName' => trim((string) ($config['key_vault_name'] ?? '')),
                'keyVaultUri' => trim((string) ($config['key_vault_uri'] ?? '')),
                'certificateStorageMode' => 'key-vault',
            ],
            'notifications' => [
                'defaultFromName' => 'ICT365 SecureIT Reporting',
                'defaultReplyTo' => '',
            ],
            'reports' => [
                'baseSiteUrl' => trim((string) ($config['base_url'] ?? '')),
            ],
        ];
        $dir = dirname($adminConfigPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($adminConfigPath, json_encode($adminPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $createdFiles[] = 'admin-config.json';
    }

    if (!file_exists($canonicalControlsPath)) {
        $canonicalControlsSeed = '';
        foreach ([
            '/usr/local/share/secureit/canonical-controls.json',
            (string) ($config['canonical_controls_example_file'] ?? ''),
        ] as $sourcePath) {
            if (!$sourcePath || !file_exists($sourcePath)) {
                continue;
            }
            $canonicalControlsSeed = (string) file_get_contents($sourcePath);
            break;
        }

        if (trim($canonicalControlsSeed) !== '') {
            $dir = dirname($canonicalControlsPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($canonicalControlsPath, rtrim($canonicalControlsSeed) . PHP_EOL);
            $createdFiles[] = 'canonical-controls.json';
        } else {
            $seedErrors[] = 'canonical-controls.json could not be seeded because no source template was available.';
        }
    }

    if ($createdFiles !== []) {
        $seedMessages[] = 'Seeded runtime files successfully: ' . implode(', ', $createdFiles) . '.';
    } elseif ($seedErrors === []) {
        $seedMessages[] = 'No files needed seeding. All runtime files already exist.';
    }
}

$tenantsConfig = secureit_load_tenants();
$tenants = $tenantsConfig['tenants'] ?? [];
$adminConfig = file_exists($adminConfigPath) ? json_decode((string) file_get_contents($adminConfigPath), true) : [];
$adminConfig = is_array($adminConfig) ? $adminConfig : [];
$registryCheckMessages = [];
$registryCheckErrors = [];
$registryCheckTenantKey = '';
$registryCheckTargetTenant = null;
$registryCheckStatus = null;
$registryUpdateMessages = [];
$registryUpdateErrors = [];
$registryUpdateStatus = null;

if ($tenants !== []) {
    $registryCheckTenantKey = (string) ($tenants[0]['id'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_registry_tenant'])) {
    $registryCheckTenantKey = trim(strtolower((string) ($_POST['registry_tenant_key'] ?? '')));
    $registryCheckTargetTenant = $registryCheckTenantKey !== '' ? secureit_find_tenant($registryCheckTenantKey) : null;

    if (!$registryCheckTargetTenant) {
        $registryCheckErrors[] = 'Select a valid tenant before checking registry readiness.';
    } else {
        $registryCheckStatus = secureit_diag_tenant_registry_status($registryCheckTargetTenant);
        $registryCheckMessages[] = $registryCheckStatus['ready']
            ? 'This tenant already has the data needed for a weekly workflow.'
            : 'This tenant still needs additional registry data before a weekly workflow can be automated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_registry_tenant_identity'])) {
    $registryCheckTenantKey = trim(strtolower((string) ($_POST['registry_tenant_key'] ?? '')));
    $registryCheckTargetTenant = $registryCheckTenantKey !== '' ? secureit_find_tenant($registryCheckTenantKey) : null;

    if (!$registryCheckTargetTenant) {
        $registryUpdateErrors[] = 'Select a valid tenant before updating tenant identity values.';
    } else {
        $tenantId = trim((string) ($registryCheckTargetTenant['tenantId'] ?? ''));
        if ($tenantId === '') {
            $registryUpdateErrors[] = 'The selected tenant does not have a tenant ID yet, so Graph lookup cannot run.';
        } else {
            try {
                $tenantIdentityLookup = secureit_entra_resolve_tenant_identity($tenantId);
                $resolvedTenantDomain = trim((string) ($tenantIdentityLookup['domain'] ?? ''));
                $resolvedOfficialTenantName = trim((string) ($tenantIdentityLookup['displayName'] ?? ''));

                $tenantConfig = secureit_load_tenants();
                $tenantUpdated = false;
                $newOfficialTenantName = '';
                $newTenantDomain = '';

                foreach (($tenantConfig['tenants'] ?? []) as &$tenantItem) {
                    if (($tenantItem['id'] ?? '') !== $registryCheckTenantKey) {
                        continue;
                    }

                    if ($resolvedOfficialTenantName !== '' && ($tenantItem['m365TenantName'] ?? '') !== $resolvedOfficialTenantName) {
                        $tenantItem['m365TenantName'] = $resolvedOfficialTenantName;
                        $newOfficialTenantName = $resolvedOfficialTenantName;
                        $tenantUpdated = true;
                    } elseif (trim((string) ($tenantItem['m365TenantName'] ?? '')) !== '') {
                        $newOfficialTenantName = trim((string) ($tenantItem['m365TenantName'] ?? ''));
                    }

                    if ($resolvedTenantDomain !== '' && ($tenantItem['tenantDomain'] ?? '') !== $resolvedTenantDomain) {
                        $tenantItem['tenantDomain'] = $resolvedTenantDomain;
                        $newTenantDomain = $resolvedTenantDomain;
                        $tenantUpdated = true;
                    } elseif (trim((string) ($tenantItem['tenantDomain'] ?? '')) !== '') {
                        $newTenantDomain = trim((string) ($tenantItem['tenantDomain'] ?? ''));
                    }
                    break;
                }
                unset($tenantItem);

                if ($tenantUpdated) {
                    secureit_save_tenants($tenantConfig);
                    $registryUpdateMessages[] = 'Updated the selected tenant record with Graph-derived identity values.';
                } else {
                    $registryUpdateMessages[] = 'The selected tenant record already contained the latest Graph-derived identity values, or Graph did not return new values.';
                }

                $registryUpdateStatus = [
                    'tenantKey' => $registryCheckTenantKey,
                    'tenantName' => (string) ($registryCheckTargetTenant['name'] ?? ''),
                    'officialTenantName' => $newOfficialTenantName,
                    'tenantDomain' => $newTenantDomain,
                    'lookupMessage' => trim((string) ($tenantIdentityLookup['message'] ?? '')),
                ];

                if ($registryUpdateStatus['officialTenantName'] === '' && $registryUpdateStatus['tenantDomain'] === '') {
                    $registryUpdateMessages[] = 'Microsoft Graph lookup did not return values to store.';
                } else {
                    $storedBits = [];
                    if ($registryUpdateStatus['officialTenantName'] !== '') {
                        $storedBits[] = 'official tenant name: ' . $registryUpdateStatus['officialTenantName'];
                    }
                    if ($registryUpdateStatus['tenantDomain'] !== '') {
                        $storedBits[] = 'tenant domain: ' . $registryUpdateStatus['tenantDomain'];
                    }
                    $registryUpdateMessages[] = 'Stored ' . implode(', ', $storedBits) . '.';
                }

                if ($registryUpdateStatus['lookupMessage'] !== '') {
                    $registryUpdateMessages[] = 'Lookup note: ' . $registryUpdateStatus['lookupMessage'];
                }

                $registryCheckTargetTenant = secureit_find_tenant($registryCheckTenantKey);
                $registryCheckStatus = $registryCheckTargetTenant ? secureit_diag_tenant_registry_status($registryCheckTargetTenant) : null;
            } catch (Throwable $exception) {
                $registryUpdateErrors[] = 'The tenant identity values could not be refreshed from Microsoft Graph: ' . $exception->getMessage();
            }
        }
    }
}

$registryStatuses = [];
foreach ($tenants as $tenantItem) {
    if (!is_array($tenantItem)) {
        continue;
    }
    $registryStatuses[] = secureit_diag_tenant_registry_status($tenantItem);
}

$registryReadyCount = count(array_filter($registryStatuses, static fn(array $status): bool => !empty($status['ready'])));
$registryMissingCount = count($registryStatuses) - $registryReadyCount;

$secretWriteMessages = [];
$secretWriteErrors = [];
$secretWriteTenantKey = '';
$secretWriteSecretName = '';
$secretWriteValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['write_tenant_secret'])) {
    $secretWriteTenantKey = trim(strtolower((string) ($_POST['secret_tenant_key'] ?? '')));
    $secretWriteSecretName = trim((string) ($_POST['secret_client_secret_name'] ?? ''));
    $secretWriteValue = (string) ($_POST['secret_value'] ?? '');
    $targetTenant = $secretWriteTenantKey !== '' ? secureit_find_tenant($secretWriteTenantKey) : null;

    if (!$targetTenant) {
        $secretWriteErrors[] = 'Select a valid tenant before writing a secret to Key Vault.';
    }

    if ($secretWriteSecretName === '' && is_array($targetTenant)) {
        $secretWriteSecretName = trim((string) ($targetTenant['clientSecretName'] ?? ''));
    }

    if ($secretWriteSecretName === '' && $secretWriteTenantKey !== '') {
        $secretWriteSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $secretWriteTenantKey));
    }

    if (trim($secretWriteValue) === '') {
        $secretWriteErrors[] = 'A client secret value is required to write to Azure Key Vault.';
    }

    if ($secretWriteErrors === []) {
        if (!secureit_keyvault_enabled()) {
            $secretWriteErrors[] = 'Azure Key Vault settings are not configured for this environment.';
        } else {
            try {
                secureit_keyvault_set_secret($secretWriteSecretName, $secretWriteValue);
                $tenantConfig = secureit_load_tenants();
                $tenantUpdated = false;
                foreach (($tenantConfig['tenants'] ?? []) as &$tenantItem) {
                    if (($tenantItem['id'] ?? '') !== $secretWriteTenantKey) {
                        continue;
                    }

                    if (($tenantItem['clientSecretName'] ?? '') !== $secretWriteSecretName) {
                        $tenantItem['clientSecretName'] = $secretWriteSecretName;
                        $tenantUpdated = true;
                    }
                    break;
                }
                unset($tenantItem);
                if ($tenantUpdated) {
                    secureit_save_tenants($tenantConfig);
                }
                $tenantLabel = (string) ($targetTenant['name'] ?? $secretWriteTenantKey);
                $secretWriteMessages[] = 'Stored the client secret for ' . $tenantLabel . ' in Azure Key Vault as ' . $secretWriteSecretName . '.';
                if ($tenantUpdated) {
                    $secretWriteMessages[] = 'Tenant metadata was updated to match the secret name.';
                }
                $secretWriteMessages[] = 'This updated the secret value only. The tenant record was not recreated.';
            } catch (Throwable $exception) {
                $secretWriteErrors[] = 'The secret could not be written to Azure Key Vault: ' . $exception->getMessage();
            }
        }
    }
}

$mailTestRecipientMailbox = trim((string) ($_POST['email_test_recipient'] ?? secureit_diag_default_email_recipient()));
if ($mailTestRecipientMailbox === '') {
    $mailTestRecipientMailbox = secureit_diag_default_email_recipient();
}
$mailTestRecipientError = '';
if (!filter_var($mailTestRecipientMailbox, FILTER_VALIDATE_EMAIL)) {
    $mailTestRecipientError = 'Enter a valid recipient email address before sending a test email.';
}

$mailTestTenantId = trim((string) ($config['entra_tenant_id'] ?? ''));
$mailTestTenantIdSource = '';
if ($mailTestTenantId !== '') {
    $mailTestTenantIdSource = 'SECUREIT_ENTRA_TENANT_ID';
} elseif (trim((string) ($config['key_vault_tenant_id'] ?? '')) !== '') {
    $mailTestTenantId = trim((string) ($config['key_vault_tenant_id'] ?? ''));
    $mailTestTenantIdSource = 'SECUREIT_KEY_VAULT_TENANT_ID';
}
$mailTestSenderMailbox = 'secureit@ict365.ky';
$plainMailTestReport = '';
$plainMailTestErrors = [];
$plainMailTestSummary = '';
$htmlMailTestReport = '';
$htmlMailTestErrors = [];
$htmlMailTestSummary = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_plain_test_email'])) {
    $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    $modeLabel = 'Plain text';
    $subject = 'SecureIT diagnostics email test - plain text - ' . $generatedAt;
    $bodyContent = secureit_diag_build_email_body($modeLabel, $generatedAt, $mailTestSenderMailbox, $mailTestRecipientMailbox);
    $plainMailTestSummary = 'Plain text email test was not sent.';

    if ($mailTestRecipientError !== '') {
        $plainMailTestErrors[] = $mailTestRecipientError;
    } elseif ($mailTestTenantId === '') {
        $plainMailTestErrors[] = 'No Entra tenant ID is configured for Graph app-only mail sending. Set SECUREIT_ENTRA_TENANT_ID, or keep SECUREIT_KEY_VAULT_TENANT_ID populated as a fallback.';
    } elseif (!secureit_entra_is_enabled()) {
        $plainMailTestErrors[] = 'Entra client credentials are not configured, so SecureIT cannot request a Graph token.';
    } else {
        try {
            $response = secureit_entra_graph_send_mail(
                $mailTestTenantId,
                $mailTestSenderMailbox,
                $subject,
                'Text',
                $bodyContent,
                [$mailTestRecipientMailbox],
                true
            );
            $plainMailTestSummary = 'Plain text email was accepted by Microsoft Graph.';
            $plainMailTestReport = secureit_diag_build_email_test_report([
                'modeLabel' => $modeLabel,
                'outcomeLabel' => 'success',
                'attemptedAt' => $generatedAt,
                'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
                'mailTenantId' => $mailTestTenantId,
                'senderMailbox' => $mailTestSenderMailbox,
                'recipientMailbox' => $mailTestRecipientMailbox,
                'endpoint' => (string) ($response['endpoint'] ?? ''),
                'subject' => $subject,
                'bodyContentType' => 'Text',
                'bodyContent' => $bodyContent,
                'saveToSentItems' => (bool) ($response['saveToSentItems'] ?? true),
                'response' => $response,
            ]);
        } catch (Throwable $exception) {
            $plainMailTestErrors[] = 'Plain text email could not be sent through Microsoft Graph: ' . $exception->getMessage();
            $plainMailTestReport = secureit_diag_build_email_test_report([
                'modeLabel' => $modeLabel,
                'outcomeLabel' => 'failure',
                'attemptedAt' => $generatedAt,
                'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
                'mailTenantId' => $mailTestTenantId,
                'senderMailbox' => $mailTestSenderMailbox,
                'recipientMailbox' => $mailTestRecipientMailbox,
                'endpoint' => 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($mailTestSenderMailbox) . '/sendMail',
                'subject' => $subject,
                'bodyContentType' => 'Text',
                'bodyContent' => $bodyContent,
                'saveToSentItems' => true,
                'errors' => [
                    'Plain text email could not be sent through Microsoft Graph: ' . $exception->getMessage(),
                ],
            ]);
        }
    }

    if ($plainMailTestReport === '') {
        $plainMailTestReport = secureit_diag_build_email_test_report([
            'modeLabel' => $modeLabel,
            'outcomeLabel' => 'not sent',
            'attemptedAt' => $generatedAt,
            'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
            'mailTenantId' => $mailTestTenantId,
            'senderMailbox' => $mailTestSenderMailbox,
            'recipientMailbox' => $mailTestRecipientMailbox,
            'endpoint' => 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($mailTestSenderMailbox) . '/sendMail',
            'subject' => $subject,
            'bodyContentType' => 'Text',
            'bodyContent' => $bodyContent,
            'saveToSentItems' => true,
            'errors' => $plainMailTestErrors,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_html_test_email'])) {
    $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    $modeLabel = 'HTML';
    $subject = 'SecureIT diagnostics email test - HTML - ' . $generatedAt;
    $overviewStats = secureit_diag_email_overview_stats();
    $bodyContent = secureit_diag_build_email_html_body($modeLabel, $generatedAt, $mailTestSenderMailbox, $mailTestRecipientMailbox, $overviewStats);
    $htmlMailTestSummary = 'HTML email test was not sent.';

    if ($mailTestRecipientError !== '') {
        $htmlMailTestErrors[] = $mailTestRecipientError;
    } elseif ($mailTestTenantId === '') {
        $htmlMailTestErrors[] = 'No Entra tenant ID is configured for Graph app-only mail sending. Set SECUREIT_ENTRA_TENANT_ID, or keep SECUREIT_KEY_VAULT_TENANT_ID populated as a fallback.';
    } elseif (!secureit_entra_is_enabled()) {
        $htmlMailTestErrors[] = 'Entra client credentials are not configured, so SecureIT cannot request a Graph token.';
    } else {
        try {
            $response = secureit_entra_graph_send_mail(
                $mailTestTenantId,
                $mailTestSenderMailbox,
                $subject,
                'HTML',
                $bodyContent,
                [$mailTestRecipientMailbox],
                true
            );
            $htmlMailTestSummary = 'HTML email was accepted by Microsoft Graph.';
            $htmlMailTestReport = secureit_diag_build_email_test_report([
                'modeLabel' => $modeLabel,
                'outcomeLabel' => 'success',
                'attemptedAt' => $generatedAt,
                'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
                'mailTenantId' => $mailTestTenantId,
                'senderMailbox' => $mailTestSenderMailbox,
                'recipientMailbox' => $mailTestRecipientMailbox,
                'endpoint' => (string) ($response['endpoint'] ?? ''),
                'subject' => $subject,
                'bodyContentType' => 'HTML',
                'bodyContent' => $bodyContent,
                'saveToSentItems' => (bool) ($response['saveToSentItems'] ?? true),
                'response' => $response,
            ]);
        } catch (Throwable $exception) {
            $htmlMailTestErrors[] = 'HTML email could not be sent through Microsoft Graph: ' . $exception->getMessage();
            $htmlMailTestReport = secureit_diag_build_email_test_report([
                'modeLabel' => $modeLabel,
                'outcomeLabel' => 'failure',
                'attemptedAt' => $generatedAt,
                'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
                'mailTenantId' => $mailTestTenantId,
                'senderMailbox' => $mailTestSenderMailbox,
                'recipientMailbox' => $mailTestRecipientMailbox,
                'endpoint' => 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($mailTestSenderMailbox) . '/sendMail',
                'subject' => $subject,
                'bodyContentType' => 'HTML',
                'bodyContent' => $bodyContent,
                'saveToSentItems' => true,
                'errors' => [
                    'HTML email could not be sent through Microsoft Graph: ' . $exception->getMessage(),
                ],
            ]);
        }
    }

    if ($htmlMailTestReport === '') {
        $htmlMailTestReport = secureit_diag_build_email_test_report([
            'modeLabel' => $modeLabel,
            'outcomeLabel' => 'not sent',
            'attemptedAt' => $generatedAt,
            'mailTenantSource' => $mailTestTenantIdSource !== '' ? $mailTestTenantIdSource : '[not set]',
            'mailTenantId' => $mailTestTenantId,
            'senderMailbox' => $mailTestSenderMailbox,
            'recipientMailbox' => $mailTestRecipientMailbox,
            'endpoint' => 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($mailTestSenderMailbox) . '/sendMail',
            'subject' => $subject,
            'bodyContentType' => 'HTML',
            'bodyContent' => $bodyContent,
            'saveToSentItems' => true,
            'errors' => $htmlMailTestErrors,
        ]);
    }
}

if ($secretWriteTenantKey === '' && $tenants !== []) {
    $secretWriteTenantKey = (string) ($tenants[0]['id'] ?? '');
}

$secretWriteTargetTenant = $secretWriteTenantKey !== '' ? secureit_find_tenant($secretWriteTenantKey) : null;
if ($secretWriteSecretName === '' && is_array($secretWriteTargetTenant)) {
    $secretWriteSecretName = trim((string) ($secretWriteTargetTenant['clientSecretName'] ?? ''));
}
if ($secretWriteSecretName === '' && $secretWriteTenantKey !== '') {
    $secretWriteSecretName = 'AZURE-CLIENT-SECRET-' . strtoupper(preg_replace('/[^A-Za-z0-9-]/', '-', $secretWriteTenantKey));
}

$keyVaultEnabled = secureit_keyvault_enabled();
$keyVaultBaseUri = '[not configured]';
try {
    $keyVaultBaseUri = secureit_keyvault_base_uri();
} catch (Throwable $exception) {
    $keyVaultBaseUri = '[not configured]';
}

$rawLines = [];
$rawLines[] = 'SecureIT live diagnostics';
$rawLines[] = 'Generated: ' . date(DATE_ATOM);
$rawLines[] = '';
$rawLines[] = '[Runtime]';
$rawLines[] = secureit_diag_json_line('Request host', (string) ($_SERVER['HTTP_HOST'] ?? ''));
$rawLines[] = secureit_diag_json_line('Request URI', (string) ($_SERVER['REQUEST_URI'] ?? ''));
$rawLines[] = secureit_diag_json_line('PHP version', PHP_VERSION);
$rawLines[] = secureit_diag_json_line('PHP SAPI', PHP_SAPI);
$rawLines[] = 'Extensions: curl=' . secureit_diag_yes_no($phpExtensions['curl']) . ', json=' . secureit_diag_yes_no($phpExtensions['json']) . ', openssl=' . secureit_diag_yes_no($phpExtensions['openssl']);
$rawLines[] = secureit_diag_json_line('Base URL', (string) ($config['base_url'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Mounted data]';
$rawLines[] = secureit_diag_path_line('Data root', $dataRoot);
$rawLines[] = secureit_diag_path_line('Tenants file', (string) $config['tenants_file']);
$rawLines[] = 'Tenant count: ' . count($tenants);
$rawLines[] = secureit_diag_path_line('Reports root', (string) $config['reports_root']);
$rawLines[] = secureit_diag_path_line('Admin config', $adminConfigPath);
$rawLines[] = secureit_diag_path_line('Canonical controls', (string) $config['canonical_controls_file']);
$rawLines[] = secureit_diag_path_line('Local identity seeds', (string) ($config['identity_seeds_file'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Application registry]';
$rawLines[] = 'Registry source: mounted tenants.json';
$rawLines[] = 'Registry tenant count: ' . count($tenants);
$rawLines[] = 'Weekly workflow ready tenants: ' . $registryReadyCount;
$rawLines[] = 'Weekly workflow not ready tenants: ' . $registryMissingCount;
foreach ($registryStatuses as $status) {
    $rawLines[] = 'Tenant ' . ($status['tenantKey'] !== '' ? $status['tenantKey'] : '[unnamed]') . ': ready=' . secureit_diag_yes_no((bool) $status['ready']);
    $rawLines[] = '  Dashboard label: ' . ($status['tenantName'] !== '' ? $status['tenantName'] : '[not set]');
    $rawLines[] = '  Tenant ID: ' . ($status['tenantId'] !== '' ? $status['tenantId'] : '[not set]');
    $rawLines[] = '  Client ID: ' . ($status['clientId'] !== '' ? $status['clientId'] : '[not set]');
    $rawLines[] = '  Stored official Microsoft 365 tenant name: ' . ($status['officialTenantName'] !== '' ? $status['officialTenantName'] : '[not set]');
    $rawLines[] = '  Resolved official Microsoft 365 tenant name: ' . ($status['resolvedOfficialTenantName'] !== '' ? $status['resolvedOfficialTenantName'] : '[not set]');
    $rawLines[] = '  Effective official Microsoft 365 tenant name: ' . ($status['effectiveOfficialTenantName'] !== '' ? $status['effectiveOfficialTenantName'] : '[not set]');
    $rawLines[] = '  Auth mode: ' . ($status['authMode'] !== '' ? $status['authMode'] : '[not set]');
    $rawLines[] = '  Stored tenant domain: ' . ($status['tenantDomain'] !== '' ? $status['tenantDomain'] : '[not set]');
    $rawLines[] = '  Resolved tenant domain: ' . ($status['resolvedTenantDomain'] !== '' ? $status['resolvedTenantDomain'] : '[not set]');
    $rawLines[] = '  Effective tenant domain: ' . ($status['effectiveTenantDomain'] !== '' ? $status['effectiveTenantDomain'] : '[not set]');
    $rawLines[] = '  Tenant domain source: ' . ($status['tenantDomainSource'] !== '' ? $status['tenantDomainSource'] : '[not available]');
    $rawLines[] = '  Tenant domain lookup status: ' . ($status['tenantDomainLookupStatus'] !== '' ? $status['tenantDomainLookupStatus'] : '[not available]');
    $rawLines[] = '  Official tenant name source: ' . ($status['officialTenantNameSource'] !== '' ? $status['officialTenantNameSource'] : '[not available]');
    $rawLines[] = '  Official tenant name lookup status: ' . ($status['officialTenantNameLookupStatus'] !== '' ? $status['officialTenantNameLookupStatus'] : '[not available]');
    $rawLines[] = '  Domain lookup note: ' . ($status['resolvedTenantDomainMessage'] !== '' ? $status['resolvedTenantDomainMessage'] : '[not available]');
    $rawLines[] = '  Official tenant name lookup note: ' . ($status['resolvedOfficialTenantNameMessage'] !== '' ? $status['resolvedOfficialTenantNameMessage'] : '[not available]');
    $rawLines[] = '  Report base URL: ' . ($status['reportBaseUrl'] !== '' ? $status['reportBaseUrl'] : '[not set]');
    $rawLines[] = '  Secret reference: ' . (
        $status['authMode'] === 'client-secret'
            ? ($status['clientSecretName'] !== '' ? $status['clientSecretName'] : '[not set]')
            : ($status['authMode'] === 'certificate'
                ? (
                    $status['certificateSecretName'] !== '' ? $status['certificateSecretName'] : '[not set]'
                )
                : '[not set]')
    );
    $rawLines[] = '  Missing required fields: ' . (empty($status['missing']) ? '[none]' : implode(', ', $status['missing']));
    $rawLines[] = '  Recommended follow-up: ' . (empty($status['recommendedMissing']) ? '[none]' : implode(', ', $status['recommendedMissing']));
}
$rawLines[] = '';
$rawLines[] = '[Shared component / Key Vault]';
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_TENANT_ID', (string) ($config['key_vault_tenant_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_CLIENT_ID', (string) ($config['key_vault_client_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_CLIENT_SECRET', (string) ($config['key_vault_client_secret'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_NAME', (string) ($config['key_vault_name'] ?? ''));
$rawLines[] = secureit_diag_env_line('SECUREIT_KEY_VAULT_URI', (string) ($config['key_vault_uri'] ?? ''));
$rawLines[] = 'Key Vault helper enabled: ' . secureit_diag_yes_no($keyVaultEnabled);
$rawLines[] = 'Key Vault base URI: ' . $keyVaultBaseUri;
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.keyVaultName', (string) ($adminConfig['azure']['keyVaultName'] ?? ''));
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.keyVaultUri', (string) ($adminConfig['azure']['keyVaultUri'] ?? ''));
$rawLines[] = secureit_diag_json_line('Saved admin-config.azure.certificateStorageMode', (string) ($adminConfig['azure']['certificateStorageMode'] ?? ''));
$rawLines[] = '';
$rawLines[] = '[Entra sign-in]';
$rawLines[] = secureit_diag_env_line('SECUREIT_ENTRA_CLIENT_ID', (string) ($config['entra_client_id'] ?? ''), true);
$rawLines[] = secureit_diag_env_line('SECUREIT_ENTRA_CLIENT_SECRET', (string) ($config['entra_client_secret'] ?? ''), true);
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_TENANT_ID', (string) ($config['entra_tenant_id'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_AUTHORITY', (string) ($config['entra_authority'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_REDIRECT_URI', (string) ($config['entra_redirect_uri'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_POST_LOGOUT_REDIRECT_URI', (string) ($config['entra_post_logout_redirect_uri'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_ADMIN_EMAIL_DOMAINS', (string) ($config['entra_admin_email_domains'] ?? ''));
$rawLines[] = secureit_diag_json_line('SECUREIT_ENTRA_ALLOWED_TENANT_IDS', trim((string) ($config['entra_allowed_tenant_ids'] ?? '')) !== '' ? 'set' : 'not set');
$rawLines[] = '';
$rawLines[] = '[Workflow sync]';
$rawLines[] = secureit_diag_env_line('SECUREIT_WORKFLOW_SYNC_TOKEN', (string) ($config['workflow_sync_token'] ?? ''), true);
$rawLines[] = secureit_diag_json_line('SECUREIT_WORKFLOW_SYNC_TOKEN_FINGERPRINT', secureit_workflow_sync_token_fingerprint());

$rawOutput = implode("\n", $rawLines) . "\n";
$rawMode = in_array(strtolower((string) ($_GET['format'] ?? '')), ['raw', 'text', 'plain'], true) || isset($_GET['raw']);

if ($rawMode) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $rawOutput;
    exit;
}

ob_start();
?>
<section class="section">
  <div class="section-heading">
    <h2 class="section-title" style="text-align:center; font-size:clamp(2rem, 4vw, 3.15rem);">Live diagnostics</h2>
    <p class="section-intro" style="max-width:960px; margin-left:auto; margin-right:auto; text-align:center;">Admin-only readiness checks for the live host before onboarding a customer. No secrets are printed. Use the raw text view if you want to copy and paste the output.</p>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Copy-friendly output</h3>
        <div class="muted">Open the raw text version to paste the results into chat or notes.</div>
      </div>
      <a class="textlink" href="diagnostics.php?format=raw">Open raw text</a>
    </div>
    <pre style="white-space:pre-wrap; margin:0;"><?php echo htmlspecialchars($rawOutput); ?></pre>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Seed runtime files</h3>
        <div class="muted">Create the mounted JSON files only if they are missing, then re-check after a redeploy.</div>
      </div>
    </div>

    <?php foreach ($seedErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($seedMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <form method="post">
      <button type="submit" name="seed_runtime_files" value="1">Create missing files</button>
      <p class="field-note" style="margin-top:10px;">This creates `tenants.json`, `admin-config.json`, and `canonical-controls.json` if they do not already exist. Re-run the diagnostics view afterwards to confirm the result.</p>
    </form>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Application registry readiness</h3>
        <div class="muted">Checks the live `tenants.json` registry for the fields needed to automate a weekly tenant workflow. If tenant domain is missing, SecureIT will try to resolve it from Microsoft Graph using the tenant ID.</div>
      </div>
    </div>

    <div class="stats-row" style="margin-bottom:14px;">
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) count($registryStatuses)); ?></strong><span>Total</span></div>
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $registryReadyCount); ?></strong><span>Ready</span></div>
      <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $registryMissingCount); ?></strong><span>Not ready</span></div>
    </div>

    <?php foreach ($registryCheckErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($registryCheckMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($registryUpdateErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($registryUpdateMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php if ($tenants === []): ?>
      <div class="empty-state">
        <strong>No tenants are available yet.</strong>
        <p class="muted" style="margin:8px 0 0;">Onboard at least one tenant before checking registry readiness.</p>
      </div>
    <?php else: ?>
      <form method="post" style="margin-bottom:16px;">
        <label for="registry_tenant_key">Tenant to inspect</label>
        <select id="registry_tenant_key" name="registry_tenant_key">
          <?php foreach ($tenants as $tenantItem): ?>
            <?php $tenantId = (string) ($tenantItem['id'] ?? ''); ?>
            <option value="<?php echo htmlspecialchars($tenantId); ?>"<?php echo $registryCheckTenantKey === $tenantId ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($tenantItem['name'] ?? $tenantId)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="field-note">Use this to confirm whether the live registry already contains the fields needed for a weekly GitHub workflow.</p>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <button type="submit" name="inspect_registry_tenant" value="1">Check registry readiness</button>
          <button type="submit" name="update_registry_tenant_identity" value="1">Refresh tenant identity from Graph</button>
        </div>
      </form>

      <?php if ($registryCheckStatus): ?>
        <div class="empty-state" style="margin-bottom:0;">
          <strong><?php echo htmlspecialchars(($registryCheckStatus['tenantName'] !== '' ? $registryCheckStatus['tenantName'] : $registryCheckStatus['tenantKey']) . ' readiness: ' . ($registryCheckStatus['ready'] ? 'ready' : 'not ready')); ?></strong>
          <p class="muted" style="margin:8px 0 0;">
            <?php echo htmlspecialchars($registryCheckStatus['ready'] ? 'The selected tenant already has the fields needed for a scheduled run.' : 'The selected tenant still needs registry updates before the workflow can be scheduled cleanly.'); ?>
          </p>
          <div class="kv" style="margin-top:14px;">
            <div class="kv-row"><div class="kv-label">Tenant key</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantKey'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Dashboard label</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantName'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Tenant ID</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantId'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Client ID</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['clientId'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Official Microsoft 365 tenant name</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['officialTenantName'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Resolved official tenant name</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedOfficialTenantName'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Effective official tenant name</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['effectiveOfficialTenantName'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Stored tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Resolved tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedTenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Effective tenant domain</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['effectiveTenantDomain'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Tenant domain source</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomainSource'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Lookup status</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['tenantDomainLookupStatus'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Official tenant name source</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['officialTenantNameSource'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Official name lookup status</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['officialTenantNameLookupStatus'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Domain lookup note</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedTenantDomainMessage'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Official name lookup note</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['resolvedOfficialTenantNameMessage'] ?: '[not available]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Auth mode</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['authMode'] ?: '[not set]'); ?></div></div>
            <div class="kv-row"><div class="kv-label">Secret reference</div><div class="kv-value"><?php echo htmlspecialchars($registryCheckStatus['authMode'] === 'client-secret' ? ($registryCheckStatus['clientSecretName'] ?: '[not set]') : ($registryCheckStatus['authMode'] === 'certificate' ? ($registryCheckStatus['certificateSecretName'] ?: '[not set]') : '[not set]')); ?></div></div>
            <div class="kv-row"><div class="kv-label">Missing required fields</div><div class="kv-value"><?php echo htmlspecialchars(empty($registryCheckStatus['missing']) ? '[none]' : implode(', ', $registryCheckStatus['missing'])); ?></div></div>
            <div class="kv-row"><div class="kv-label">Recommended follow-up</div><div class="kv-value"><?php echo htmlspecialchars(empty($registryCheckStatus['recommendedMissing']) ? '[none]' : implode(', ', $registryCheckStatus['recommendedMissing'])); ?></div></div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Write tenant secret</h3>
        <div class="muted">Temporary admin tool for updating an existing tenant's Key Vault secret without deleting or recreating the tenant.</div>
      </div>
    </div>

    <?php foreach ($secretWriteErrors as $error): ?>
      <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <?php foreach ($secretWriteMessages as $message): ?>
      <div class="success" style="margin-bottom:12px;"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php if ($tenants === []): ?>
      <div class="empty-state" style="margin-bottom:12px;">
        <strong>No tenants are available yet.</strong>
        <p class="muted" style="margin:8px 0 0;">Onboard at least one tenant before using this temporary Key Vault write tool.</p>
      </div>
    <?php else: ?>
      <form method="post">
        <label for="secret_tenant_key">Tenant</label>
        <select id="secret_tenant_key" name="secret_tenant_key">
          <?php foreach ($tenants as $tenantItem): ?>
            <?php $tenantId = (string) ($tenantItem['id'] ?? ''); ?>
            <option value="<?php echo htmlspecialchars($tenantId); ?>"<?php echo $secretWriteTenantKey === $tenantId ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars((string) ($tenantItem['name'] ?? $tenantId)); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="field-note">Select the tenant whose Key Vault secret you want to update.</p>

        <label for="secret_client_secret_name">Key Vault secret name</label>
        <input id="secret_client_secret_name" name="secret_client_secret_name" value="<?php echo htmlspecialchars($secretWriteSecretName); ?>" placeholder="AZURE-CLIENT-SECRET-NCVO">
        <p class="field-note">This should match the tenant's stored client secret name.</p>

        <label for="secret_value">Client secret value</label>
        <input id="secret_value" name="secret_value" type="password" autocomplete="new-password" placeholder="Paste the secret to store in Key Vault">
        <p class="field-note">The value is written to Azure Key Vault and is not saved in the app.</p>

        <button type="submit" name="write_tenant_secret" value="1">Write secret to Key Vault</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card panel" style="margin-bottom:18px;">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">Email send test</h3>
        <div class="muted">Send a test message from the shared mailbox <code>secureit@ict365.ky</code> using the app's Graph Mail.Send permission. Both tests target the shared mailbox itself so you can inspect the result in one place.</div>
      </div>
    </div>

    <form method="post">
      <label for="email_test_recipient">Recipient email address</label>
      <input id="email_test_recipient" name="email_test_recipient" type="email" autocomplete="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($mailTestRecipientMailbox); ?>" required>
      <p class="field-note">The shared mailbox remains <code>secureit@ict365.ky</code>. Enter the recipient address you want to test.</p>

      <?php if ($mailTestRecipientError !== ''): ?>
        <div class="error" style="margin-bottom:12px;"><?php echo htmlspecialchars($mailTestRecipientError); ?></div>
      <?php endif; ?>

      <div class="split" style="grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); gap:16px;">
        <div class="empty-state" style="margin:0; align-self:start;">
          <h4 class="section-title" style="font-size:1.15rem; margin-bottom:8px;">Plain text email</h4>
          <p class="muted" style="margin:0 0 12px;">Uses a `text/plain` message body and writes the same diagnostic block into the message body.</p>
          <button type="submit" name="send_plain_test_email" value="1">Send plain text test email</button>
          <?php if ($plainMailTestReport === '' && $plainMailTestErrors === []): ?>
            <div class="empty-state" style="margin:12px 0 12px;">
              <strong>Awaiting test run</strong>
              <p class="muted" style="margin:8px 0 0;">Press the button above to generate a diagnostic report.</p>
            </div>
          <?php else: ?>
            <div class="<?php echo $plainMailTestErrors !== [] ? 'error' : 'success'; ?>" style="margin:12px 0;">
              <?php echo htmlspecialchars($plainMailTestErrors !== [] ? implode(' ', $plainMailTestErrors) : $plainMailTestSummary); ?>
            </div>
          <?php endif; ?>
          <label for="plain_email_diagnostic" style="margin-top:0;">Diagnostic output</label>
          <textarea id="plain_email_diagnostic" readonly spellcheck="false" style="width:100%; min-height:280px; resize:vertical; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;"><?php echo htmlspecialchars($plainMailTestReport !== '' ? $plainMailTestReport : "Press the button above to generate a diagnostic report.\n"); ?></textarea>
        </div>

        <div class="empty-state" style="margin:0; align-self:start;">
          <h4 class="section-title" style="font-size:1.15rem; margin-bottom:8px;">HTML email</h4>
          <p class="muted" style="margin:0 0 12px;">Uses a `text/html` message body with a dashboard-style overview and the same diagnostic content rendered as markup.</p>
          <button type="submit" name="send_html_test_email" value="1">Send HTML test email</button>
          <?php if ($htmlMailTestReport === '' && $htmlMailTestErrors === []): ?>
            <div class="empty-state" style="margin:12px 0 12px;">
              <strong>Awaiting test run</strong>
              <p class="muted" style="margin:8px 0 0;">Press the button above to generate a diagnostic report.</p>
            </div>
          <?php else: ?>
            <div class="<?php echo $htmlMailTestErrors !== [] ? 'error' : 'success'; ?>" style="margin:12px 0;">
              <?php echo htmlspecialchars($htmlMailTestErrors !== [] ? implode(' ', $htmlMailTestErrors) : $htmlMailTestSummary); ?>
            </div>
          <?php endif; ?>
          <label for="html_email_diagnostic" style="margin-top:0;">Diagnostic output</label>
          <textarea id="html_email_diagnostic" readonly spellcheck="false" style="width:100%; min-height:280px; resize:vertical; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;"><?php echo htmlspecialchars($htmlMailTestReport !== '' ? $htmlMailTestReport : "Press the button above to generate a diagnostic report.\n"); ?></textarea>
        </div>
      </div>
    </form>
  </div>

  <div class="card panel">
    <div class="section-header" style="margin-bottom:12px;">
      <div>
        <h3 class="section-title" style="font-size:1.35rem;">What to check first</h3>
        <div class="muted">The most important signals for shared component and onboarding readiness.</div>
      </div>
    </div>
    <ul style="margin:0 0 0 18px; padding:0; line-height:1.7; color:var(--eden);">
      <li>`/var/www/data` is writable and survives container recreates.</li>
      <li>`tenants.json`, `reports/`, and `admin-config.json` live under the mounted data volume.</li>
      <li>Key Vault environment values are set in the live stack, not saved inside the image.</li>
      <li>Entra client ID and secret are present before customer onboarding.</li>
      <li>Local identity seed data is not mounted on live.</li>
    </ul>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('Live Diagnostics - SecureIT', $content, [
    'pageTitle' => 'Live diagnostics',
    'pageIntro' => 'Check the mounted storage, Key Vault, and Entra readiness for the live host before onboarding a customer.',
    'eyebrow' => '',
    'heroBackground' => secureit_default_hero_background(),
    'heroTextAlign' => 'center',
    'navLinks' => [],
    'headerMenu' => [
        ['href' => 'dashboard.php', 'label' => 'Tenant overview dashboard'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'diagnostics.php', 'label' => 'Live diagnostics'],
    ],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'login.php', 'label' => 'Customer login'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Admin dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'diagnostics.php', 'label' => 'Live diagnostics'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
