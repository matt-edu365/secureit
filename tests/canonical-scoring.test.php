<?php

putenv('SECUREIT_CANONICAL_CONTROLS_FILE=' . __DIR__ . '/../docker/secureit-assets/canonical-controls.json');
putenv('SECUREIT_REPORTS_ROOT=' . __DIR__ . '/fixtures/canonical-scoring/reports');
$appVersionFile = tempnam(sys_get_temp_dir(), 'secureit-app-version-');
file_put_contents($appVersionFile, "0.270.c4\n");
putenv('SECUREIT_APP_VERSION_FILE=' . $appVersionFile);
require __DIR__ . '/../app/lib.php';

function secureit_contract_test_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$catalog = secureit_load_canonical_controls();
$validationErrors = secureit_validate_canonical_controls($catalog);
secureit_contract_test_assert($validationErrors === [], 'The canonical control catalog is invalid: ' . implode(' ', $validationErrors));
secureit_contract_test_assert(count($catalog['controls'] ?? []) === 95, 'The catalog must contain 94 production controls plus the separate Conditional Access What If TODO control.');
secureit_contract_test_assert(secureit_total_canonical_control_count() === 94, 'The website production control total must exclude the separate TODO control and equal 94.');
secureit_contract_test_assert(($catalog['controls'][3]['id'] ?? '') === 'C0004', 'Authentication method baseline should use the fourth stable display/control ID.');
secureit_contract_test_assert(($catalog['controls'][3]['aliases'][0] ?? '') === 'AUTHENTICATIONMETHODBASELINE', 'Authentication method baseline must retain its upstream identifier as an alias.');
secureit_contract_test_assert(($catalog['controls'][3]['title'] ?? '') === 'Authentication method policies use valid groups', 'Authentication method baseline wording should describe its actual check.');
secureit_contract_test_assert(($catalog['controls'][44]['id'] ?? '') === 'C0044', 'Weak authentication methods should retain the established stable display/control ID.');
secureit_contract_test_assert(($catalog['controls'][44]['aliases'][0] ?? '') === 'MTCISWEAKAUTHENTICATIONMETHODSDISABLED', 'Weak authentication methods must retain its upstream identifier as an alias.');
$authRoute = secureit_control_remediation_route($catalog['controls'][3]);
secureit_contract_test_assert(
    str_contains((string) ($authRoute['path'] ?? ''), 'included and excluded groups'),
    'Authentication method baseline remediation must point to policy group assignments.'
);
$authDetails = secureit_control_details_for_resolved_control($catalog['controls'][3]);
secureit_contract_test_assert(
    str_contains($authDetails, 'valid group') && str_contains($authDetails, 'invalid group'),
    'Authentication method baseline detail text must describe valid group references.'
);

$legacyCatalog = $catalog;
$legacyCatalog['version'] = 1;
secureit_contract_test_assert(
    secureit_validate_canonical_controls($legacyCatalog) === [],
    'A structurally valid version 1 mounted catalog must remain loadable during the production migration.'
);
$invalidCatalog = $catalog;
$invalidCatalog['version'] = 0;
secureit_contract_test_assert(
    secureit_validate_canonical_controls($invalidCatalog) !== [],
    'A non-positive catalog version must be rejected.'
);
$invalidCatalog = $catalog;
$invalidCatalog['controls'][0]['scoring']['weight'] = 2;
secureit_contract_test_assert(
    secureit_validate_canonical_controls($invalidCatalog) !== [],
    'Legacy compatibility must not permit control weights other than 1.'
);

$controlIds = [];
foreach (($catalog['controls'] ?? []) as $control) {
    $controlId = (string) ($control['id'] ?? '');
    secureit_contract_test_assert($controlId !== '', 'Every control must have a stable ID.');
    secureit_contract_test_assert(!isset($controlIds[$controlId]), 'Canonical control IDs must be unique.');
    $controlIds[$controlId] = true;
    secureit_contract_test_assert(is_string($control['functionalArea'] ?? null), $controlId . ' must have one scoring functional area.');
    secureit_contract_test_assert(count($control['frameworkMappings'] ?? []) > 0, $controlId . ' must have explicit evidence mappings.');
    secureit_contract_test_assert(($control['scoring']['weight'] ?? null) === 1, $controlId . ' must have weight 1.');
}

$productionExcludedControlIds = [
    'APPREGISTRATIONS',
    'MTAPPREGISTRATIONOWNERSWITHOUTMFA',
    'MTHIGHRISKAPPPERMISSIONS',
    'XSPMDEVICES',
    'XSPMPRIVILEGEDIDENTITIES',
    'MTMDIHEALTHISSUES',
];
foreach ($productionExcludedControlIds as $controlId) {
    secureit_contract_test_assert(!isset($controlIds[$controlId]), $controlId . ' must remain outside the production catalog.');
}

$staleRuntimeControls = $catalog['controls'];
$staleControlTemplate = $staleRuntimeControls[0];
foreach ($productionExcludedControlIds as $controlId) {
    $staleControl = $staleControlTemplate;
    $staleControl['id'] = $controlId;
    $staleControl['title'] = $controlId;
    $staleControl['frameworkMappings'] = ['Test-' . $controlId . '.Tests.ps1'];
    $staleRuntimeControls[] = $staleControl;
}
$filteredRuntimeControls = secureit_filter_production_controls($staleRuntimeControls);
secureit_contract_test_assert(
    count($filteredRuntimeControls) === count($catalog['controls']),
    'An older mounted catalog must be filtered back to the current production contract.'
);
$filteredRuntimeControlIds = array_column($filteredRuntimeControls, 'id');
foreach ($productionExcludedControlIds as $controlId) {
    secureit_contract_test_assert(
        !in_array($controlId, $filteredRuntimeControlIds, true),
        $controlId . ' must be removed even when it survives in an older mounted catalog.'
    );
}

$summaryMailHtml = secureit_mail_build_overview_html([
    'checks' => secureit_total_canonical_control_count(),
    'passed' => 30,
    'partial' => 7,
    'failed' => 43,
    'errors' => 0,
    'skipped' => 14,
]);
secureit_contract_test_assert(
    preg_match('/>Checks<\/div><div[^>]*>94<\/div>/', $summaryMailHtml) === 1,
    'The completion email must advertise 94 checks.'
);

secureit_contract_test_assert(secureit_app_version() === '0.270.c4', 'The footer version should be read from the generated build metadata file.');
@unlink($appVersionFile);

$statusCases = [
    'Pass' => 'pass',
    'Failed' => 'fail',
    'Investigate' => 'partial',
    'NotApplicable' => 'not_applicable',
    'Not Run' => 'not_run',
    'Skipped' => 'skipped',
    'Error' => 'error',
];
foreach ($statusCases as $sourceStatus => $expectedStatus) {
    $actualStatus = secureit_evaluate_control_status([['result' => $sourceStatus]], 'direct');
    secureit_contract_test_assert($actualStatus === $expectedStatus, $sourceStatus . ' should resolve to ' . $expectedStatus . ', got ' . $actualStatus . '.');
}
secureit_contract_test_assert(secureit_evaluate_control_status([], 'direct') === 'unmapped', 'Missing evidence must resolve to unmapped.');
secureit_contract_test_assert(
    secureit_evaluate_control_status([['result' => 'Passed'], ['result' => 'Error']], 'majority-pass') === 'error',
    'An execution error must make aggregated control evidence non-scoreable rather than hiding behind a pass or partial result.'
);

$executionErrorArtifact = [
    'Tests' => [
        [
            'Id' => 'MT.1107',
            'Title' => 'Entitlement management deleted groups',
            'Result' => 'Failed',
            'ScriptBlockFile' => '/runner/tests/Test-MtEntitlementManagementDeletedGroups.Tests.ps1',
            'ErrorRecord' => [[
                'Exception' => ['Message' => 'Response status code does not indicate success: Forbidden (Forbidden).'],
                'FullyQualifiedErrorId' => 'Microsoft.PowerShell.Commands.WriteErrorException,Test-MtEntitlementManagementDeletedGroups',
            ]],
        ],
        [
            'Id' => 'CISA.TEST.NORMAL-FAILURE',
            'Title' => 'Normal security assertion failure',
            'Result' => 'Failed',
            'ScriptBlockFile' => '/runner/tests/Test-NormalSecurityControl.Tests.ps1',
            'ErrorRecord' => [[
                'Exception' => ['Message' => 'Expected true, but got false.'],
                'FullyQualifiedErrorId' => 'PesterAssertionFailed',
            ]],
        ],
        [
            'Id' => 'MT.1029',
            'Title' => 'Privileged assignment alert',
            'Result' => 'Error',
            'ScriptBlockFile' => '/runner/tests/Test-PrivilegedAssignments.Tests.ps1',
            'ErrorRecord' => [[
                'Exception' => ['Message' => 'Authorization failed due to missing permission scope RoleManagementAlert.Read.Directory,RoleManagementAlert.ReadWrite.Directory.'],
                'FullyQualifiedErrorId' => 'PermissionScopeNotGranted',
            ]],
        ],
        [
            'Id' => 'TEST.MISSING-SCOPE',
            'Title' => 'Skipped test with missing scope',
            'Result' => 'Skipped',
            'ScriptBlockFile' => '/runner/tests/Test-MissingScope.Tests.ps1',
            'ResultDetail' => [
                'SkippedReason' => 'Missing Scope AuditLog.Read.All',
            ],
        ],
    ],
];
$executionTests = [];
foreach (secureit_extract_tests_from_embedded_summary($executionErrorArtifact) as $test) {
    $executionTests[$test['id']] = $test;
}
secureit_contract_test_assert(($executionTests['MT.1107']['result'] ?? '') === 'error', 'A failed test backed by a 403 execution exception must resolve to error.');
secureit_contract_test_assert(
    ($executionTests['MT.1107']['executionError']['requiredPermissions'][0] ?? '') === 'EntitlementManagement.Read.All',
    'A known entitlement-management API error must identify EntitlementManagement.Read.All.'
);
secureit_contract_test_assert(($executionTests['CISA.TEST.NORMAL-FAILURE']['result'] ?? '') === 'failed', 'A normal Pester assertion failure must remain a security failure.');
secureit_contract_test_assert(
    ($executionTests['MT.1029']['executionError']['requiredPermissions'] ?? []) === ['RoleManagementAlert.Read.Directory'],
    'Permission errors must prefer the least-privilege permission in the pinned Maester manifest.'
);
secureit_contract_test_assert(
    ($executionTests['TEST.MISSING-SCOPE']['result'] ?? '') === 'error'
    && ($executionTests['TEST.MISSING-SCOPE']['executionError']['requiredPermissions'] ?? []) === ['AuditLog.Read.All'],
    'A test skipped because of a missing permission must be reclassified as Error and name the missing scope.'
);

$executionErrorData = secureit_resolve_canonical_area_scores_from_artifact($executionErrorArtifact, null);
$executionErrorCounts = secureit_check_summary_counts($executionErrorData);
secureit_contract_test_assert($executionErrorCounts['errors'] === 2, 'Mapped API failures must appear in the overall canonical error count.');
$entitlementErrorControl = null;
foreach (($executionErrorData['areas'] ?? []) as $area) {
    foreach (($area['controls'] ?? []) as $control) {
        if (($control['id'] ?? '') === 'C0008') {
            $entitlementErrorControl = $control;
        }
    }
}
secureit_contract_test_assert(($entitlementErrorControl['status'] ?? '') === 'error', 'The entitlement-management control must be marked Error rather than Failed.');
secureit_contract_test_assert(
    str_contains((string) ($entitlementErrorControl['reason'] ?? ''), 'EntitlementManagement.Read.All'),
    'The control error resolution must name the permission required to rerun the test.'
);

$sourceEvidenceArtifact = [
    'Tests' => [
        [
            'Id' => 'CISA.MS.AAD.1.1',
            'Title' => 'Legacy authentication is blocked',
            'Result' => 'Passed',
            'ScriptBlockFile' => '/runner/tests/Test-MtCisaBlockLegacyAuth.Tests.ps1',
        ],
        [
            'Id' => 'MT.1148',
            'Title' => 'Defender antivirus setting one',
            'Result' => 'Passed',
            'ScriptBlockFile' => 'C:\\runner\\tests\\Test-MtMdeAntivirusPolicy.Tests.ps1',
        ],
        [
            'Id' => 'MT.1149',
            'Title' => 'Defender antivirus setting two',
            'Result' => 'Failed',
            'ScriptBlockFile' => 'C:\\runner\\tests\\Test-MtMdeAntivirusPolicy.Tests.ps1',
        ],
    ],
];
$sourceEvidenceData = secureit_resolve_canonical_area_scores_from_artifact($sourceEvidenceArtifact, null);
$sourceEvidenceControls = [];
foreach (($sourceEvidenceData['areas'] ?? []) as $area) {
    foreach (($area['controls'] ?? []) as $control) {
        $sourceEvidenceControls[$control['id'] ?? ''] = $control;
    }
}
secureit_contract_test_assert(
    ($sourceEvidenceControls['C0055']['status'] ?? '') === 'pass',
    'A Maester result must match its explicit source-file evidence mapping.'
);
secureit_contract_test_assert(
    ($sourceEvidenceControls['C0002']['status'] ?? '') === 'partial',
    'Multiple results from one explicitly mapped source file must be evaluated together.'
);
secureit_contract_test_assert(
    count($sourceEvidenceControls['C0002']['matchedIds'] ?? []) === 2,
    'Every result emitted by an explicitly mapped source file must be retained as evidence.'
);

$scoreFixture = [
    ['status' => 'pass', 'weight' => 1],
    ['status' => 'partial', 'weight' => 1],
    ['status' => 'fail', 'weight' => 1],
    ['status' => 'not_applicable', 'weight' => 1],
    ['status' => 'not_run', 'weight' => 1],
    ['status' => 'skipped', 'weight' => 1],
    ['status' => 'unmapped', 'weight' => 1],
    ['status' => 'error', 'weight' => 1],
];
$scoreCalculation = secureit_calculate_control_score($scoreFixture);
secureit_contract_test_assert($scoreCalculation['score'] === 50, 'Pass, partial, and fail should score 50% with equal weights.');
secureit_contract_test_assert($scoreCalculation['assessedControls'] === 3, 'Only pass, partial, and fail should be in the denominator.');
secureit_contract_test_assert($scoreCalculation['excludedControls'] === 5, 'All non-assessed result types should be excluded.');

foreach (['fabrikam-prod' => 100, 'contoso-prod' => 70] as $tenantKey => $expectedScore) {
    $areaData = secureit_resolve_canonical_area_scores($tenantKey);
    $counts = secureit_check_summary_counts($areaData);
    secureit_contract_test_assert($counts['score'] === $expectedScore, $tenantKey . ' should have the expected assessed-control score.');

    $allControls = [];
    foreach (($areaData['areas'] ?? []) as $area) {
        $areaCalculation = secureit_calculate_control_score($area['controls'] ?? []);
        secureit_contract_test_assert($areaCalculation['score'] === ($area['score'] ?? null), 'Area scores must use the shared score function.');
        foreach (($area['controls'] ?? []) as $control) {
            $allControls[] = $control;
            $guidance = $control['guidance'] ?? [];
            secureit_contract_test_assert(trim((string) ($guidance['issue'] ?? '')) !== '', ($control['id'] ?? 'Control') . ' is missing an issue description.');
            secureit_contract_test_assert(trim((string) ($guidance['impact'] ?? '')) !== '', ($control['id'] ?? 'Control') . ' is missing impact guidance.');
            secureit_contract_test_assert(trim((string) ($guidance['recommendedAction'] ?? '')) !== '', ($control['id'] ?? 'Control') . ' is missing a recommended action.');
            secureit_contract_test_assert(count($guidance['steps'] ?? []) >= 3, ($control['id'] ?? 'Control') . ' is missing ordered remediation steps.');
        }
    }
    $overallCalculation = secureit_calculate_control_score($allControls);
    secureit_contract_test_assert($overallCalculation['score'] === $counts['score'], 'Overall scores must use the shared score function.');

    if ($tenantKey === 'fabrikam-prod') {
        $emailArea = null;
        $domainControl = null;
        foreach (($areaData['areas'] ?? []) as $area) {
            if (($area['name'] ?? '') === 'Email & Calendaring') {
                $emailArea = $area;
            }
            foreach (($area['controls'] ?? []) as $control) {
                if (($control['id'] ?? '') === 'C0079') {
                    $domainControl = $control;
                }
            }
        }
        secureit_contract_test_assert(($emailArea['score'] ?? null) === null, 'An area with only excluded controls must have no score.');
        secureit_contract_test_assert(($domainControl['status'] ?? '') === 'unmapped', 'A control must not score through a heuristic evidence match.');
    }
}

$remediationExpectations = [
    'MTCISSPOB2BINTEGRATION' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTCISSPODEFAULTSHARINGLINK' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTCISSPODEFAULTSHARINGLINKPERMISSION' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTCISSPOGUESTACCESSEXPIRY' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTCISSPOGUESTCANNOTSHAREUNOWNEDITEM' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTCISSPOPREVENTDOWNLOADMALICIOUSFILE' => ['portal' => 'SharePoint admin center', 'family' => 'PnP'],
    'MTMDIHEALTHISSUES' => ['portal' => 'Microsoft Defender portal', 'family' => 'Security Operations & Threat Protection'],
];

foreach ($remediationExpectations as $controlId => $expected) {
    $route = secureit_control_remediation_route([
        'id' => $controlId,
        'functionalArea' => $controlId === 'MTMDIHEALTHISSUES' ? 'Endpoint & Device Management' : 'Compliance, Governance & Data Protection',
    ]);
    secureit_contract_test_assert(($route['portal'] ?? '') === $expected['portal'], $controlId . ' should resolve to the expected remediation portal.');

    $families = secureit_runtime_families_for_control([
        'id' => $controlId,
        'title' => $controlId,
        'functionalArea' => $controlId === 'MTMDIHEALTHISSUES' ? 'Endpoint & Device Management' : 'Compliance, Governance & Data Protection',
        'frameworkMappings' => [],
    ]);
    secureit_contract_test_assert(($families[0] ?? '') === $expected['family'], $controlId . ' should resolve to the expected runtime family fallback.');
}

$requirementExpectations = [
    'APPREGISTRATIONS' => 'Application.Read.All',
    'MTAPPREGISTRATIONOWNERSWITHOUTMFA' => 'User.Read.All',
    'MTHIGHRISKAPPPERMISSIONS' => 'Policy.Read.All',
    'XSPMDEVICES' => 'Defender XDR / Exposure Management',
    'XSPMPRIVILEGEDIDENTITIES' => 'Defender XDR / Exposure Management',
];

foreach ($requirementExpectations as $controlId => $expectedFragment) {
    $requirements = secureit_control_assessment_requirements(['id' => $controlId]);
    secureit_contract_test_assert($requirements !== [], $controlId . ' should expose prerequisite metadata.');
    $flat = strtolower(implode(' ', array_merge([$requirements['summary'] ?? ''], $requirements['items'] ?? [])));
    secureit_contract_test_assert(str_contains($flat, strtolower($expectedFragment)), $controlId . ' should surface the expected prerequisite detail.');
}

$bucketExpectations = [
    'APPREGISTRATIONS' => 'missing_permissions',
    'MTAPPREGISTRATIONOWNERSWITHOUTMFA' => 'missing_permissions',
    'MTHIGHRISKAPPPERMISSIONS' => 'missing_permissions',
    'XSPMDEVICES' => 'missing_license',
    'XSPMPRIVILEGEDIDENTITIES' => 'missing_license',
    'CONDITIONALACCESSWHATIF' => 'separate_feature',
];

foreach ($bucketExpectations as $controlId => $expectedBucket) {
    secureit_contract_test_assert(
        secureit_control_non_scoreable_bucket(['id' => $controlId]) === $expectedBucket,
        $controlId . ' should resolve to the expected non-scoreable bucket.'
    );
}

$reasonExpectations = [
    ['id' => 'MTCISABLOCKHIGHRISKSIGNINS', 'status' => 'skipped', 'needle' => 'required license'],
    ['id' => 'MTCISAPERMANENTROLEASSIGNMENT', 'status' => 'skipped', 'needle' => 'Privileged Identity Management'],
    ['id' => 'CONDITIONALACCESSWHATIF', 'status' => 'not_run', 'needle' => 'separate feature'],
];

foreach ($reasonExpectations as $expectation) {
    $reason = secureit_control_non_assessed_reason_with_requirements([
        'id' => $expectation['id'],
        'status' => $expectation['status'],
    ]);
    secureit_contract_test_assert(
        str_contains(strtolower($reason), strtolower($expectation['needle'])),
        $expectation['id'] . ' should surface a more specific non-assessed reason.'
    );
}

$bucketGroups = secureit_group_non_scoreable_controls([
    ['id' => 'APPREGISTRATIONS', 'title' => 'App registrations', 'functionalArea' => 'Identity & Access Management'],
    ['id' => 'XSPMDEVICES', 'title' => 'XSPM devices', 'functionalArea' => 'Security Operations & Threat Protection'],
    ['id' => 'CONDITIONALACCESSWHATIF', 'title' => 'Conditional Access what-if analysis', 'functionalArea' => 'Compliance, Governance & Data Protection'],
]);
secureit_contract_test_assert(($bucketGroups[0]['bucket'] ?? '') === 'missing_permissions', 'Missing permissions bucket should sort first.');
secureit_contract_test_assert(($bucketGroups[1]['bucket'] ?? '') === 'missing_license', 'Missing license bucket should sort second.');
secureit_contract_test_assert(($bucketGroups[2]['bucket'] ?? '') === 'separate_feature', 'Separate feature bucket should sort third.');

$productionWorkflowScript = file_get_contents(__DIR__ . '/../scripts/Invoke-MaesterRun.ps1');
secureit_contract_test_assert(
    !str_contains($productionWorkflowScript, "'Test-ConditionalAccessWhatIf.Tests.ps1'"),
    'SecureIT-Production-94 must not include Conditional Access What If in its production allowlist.'
);
secureit_contract_test_assert(
    str_contains($productionWorkflowScript, 'TODO: Add Conditional Access What If as a separate dedicated feature/profile'),
    'The Conditional Access What If production exclusion should remain explicitly tracked as a follow-up feature.'
);
secureit_contract_test_assert(
    str_contains($productionWorkflowScript, "[ValidateSet('Maester-83','365Inspect-18','Certificate-Auth-Test','SecureIT-Production-94')]")
        && str_contains($productionWorkflowScript, '$_ -notin $productionExcludedTestFiles'),
    'The runner must expose the 94-control production profile and apply its explicit exclusion list.'
);
$productionExcludedFiles = [
    'Test-AppRegistrations.Tests.ps1',
    'Test-MtAppRegistrationOwnersWithoutMFA.Tests.ps1',
    'Test-MtHighRiskAppPermissions.Tests.ps1',
    'Test-XspmDevices.Tests.ps1',
    'Test-XspmPrivilegedIdentities.Tests.ps1',
    'Test-MtMdiHealthIssues.Tests.ps1',
];
foreach ($productionExcludedFiles as $testFile) {
    secureit_contract_test_assert(
        str_contains($productionWorkflowScript, "'{$testFile}'"),
        $testFile . ' must remain explicitly excluded from the production profile.'
    );
}

$baselineListMatched = preg_match("/'Maester-83'\\s*=\\s*@\\((.*?)\\R\\s*\\)/s", $productionWorkflowScript, $baselineListMatch);
$inspectorListMatched = preg_match('/\$productionInspectors\s*=\s*@\((.*?)\R\s*\)/s', $productionWorkflowScript, $inspectorListMatch);
$exclusionListMatched = preg_match('/\$productionExcludedTestFiles\s*=\s*@\((.*?)\R\s*\)/s', $productionWorkflowScript, $exclusionListMatch);
secureit_contract_test_assert(
    $baselineListMatched === 1 && $inspectorListMatched === 1 && $exclusionListMatched === 1,
    'The production runner lists could not be parsed for the 94-control contract check.'
);
preg_match_all("/'Test-[^']+\\.ps1'/", $baselineListMatch[1], $baselineTestFiles);
preg_match_all("/'Inspect-[^']+'/", $inspectorListMatch[1], $productionInspectors);
preg_match_all("/'Test-[^']+\\.ps1'/", $exclusionListMatch[1], $excludedTestFiles);
$selectedProductionFileCount = count($baselineTestFiles[0]) - count($excludedTestFiles[0]) + count($productionInspectors[0]);
secureit_contract_test_assert(
    $selectedProductionFileCount === 94,
    'The production runner must select exactly 94 test files; calculated ' . $selectedProductionFileCount . '.'
);

$productionWorkflow = file_get_contents(__DIR__ . '/../.github/workflows/secureit-production.yml');
secureit_contract_test_assert(
    str_contains($productionWorkflow, 'default: SecureIT-Production-94')
        && !str_contains($productionWorkflow, 'SecureIT-Production-101'),
    'The production workflow must default to the 94-control profile.'
);

$tenantPageSource = file_get_contents(__DIR__ . '/../app/tenant.php');
secureit_contract_test_assert(
    str_contains($tenantPageSource, 'secureit_tenant_area_history_series')
        && str_contains($tenantPageSource, "\$_GET['historyRange'] ?? '10'")
        && str_contains($tenantPageSource, 'Last 10 runs')
        && str_contains($tenantPageSource, 'Last 30 days')
        && str_contains($tenantPageSource, 'Last year')
        && str_contains($tenantPageSource, '<?php if (!$selectedDiagnostics && !$selectedArea): ?>'),
    'Functional-area views must render score history with 10-run, 30-day, and one-year ranges without the overview trend card.'
);
secureit_contract_test_assert(
    str_contains($tenantPageSource, 'secureit_tenant_analysis(')
        && !str_contains($tenantPageSource, 'Failures and Diagnostics')
        && str_contains($tenantPageSource, 'Analysis and actions')
        && str_contains($tenantPageSource, 'data-guidance-table')
        && str_contains($tenantPageSource, 'data-guidance-toggle-all')
        && str_contains($tenantPageSource, 'control-guidance-summary')
        && str_contains($tenantPageSource, 'row.open = false'),
    'The tenant overview must use the structured latest-analysis copy and omit the overview diagnostics tile.'
);
$areaRunHistoryPosition = strpos($tenantPageSource, '<?php if ($selectedArea && !$selectedDiagnostics): ?>');
$areaChecksPosition = strpos($tenantPageSource, 'Pass and fail detail for the selected functional area.');
secureit_contract_test_assert(
    $areaRunHistoryPosition !== false
        && $areaChecksPosition !== false
        && $areaRunHistoryPosition < $areaChecksPosition,
    'Functional-area Run History must appear above the selected area checks panel.'
);

$librarySource = file_get_contents(__DIR__ . '/../app/lib.php');
secureit_contract_test_assert(
    str_contains($librarySource, 'secureit_app_version()')
        && !str_contains($librarySource, 'SecureIT v0.269.c3')
        && str_contains($librarySource, 'returned data for %d of %d tests, with %d errors and %d skipped.')
        && str_contains($librarySource, 'The overall posture %s.')
        && str_contains($librarySource, 'The lowest-scoring area is currently %s at %s.')
        && str_contains($librarySource, 'The strongest area is %s at %s.'),
    'The footer must render the generated application version rather than a hard-coded release number.'
);

$dockerPublishWorkflow = file_get_contents(__DIR__ . '/../.github/workflows/docker-publish.yml');
secureit_contract_test_assert(
    str_contains($dockerPublishWorkflow, 'GITHUB_RUN_NUMBER')
        && str_contains($dockerPublishWorkflow, 'SECUREIT_APP_VERSION=${{ steps.version.outputs.app_version }}')
        && str_contains($dockerPublishWorkflow, 'canonical-controls.version'),
    'The container publish workflow must pass its generated application version into the Docker build.'
);

$loginPageSource = file_get_contents(__DIR__ . '/../app/login.php');
secureit_contract_test_assert(
    !str_contains($loginPageSource, 'enquiry_submit')
        && !str_contains($loginPageSource, 'Not a subscriber?')
        && !str_contains($loginPageSource, '<aside')
        && str_contains($loginPageSource, 'max-width:680px; margin:0 auto;'),
    'The login page must contain only one centered login panel and no subscriber enquiry form.'
);
secureit_contract_test_assert(
    preg_match('/<input\\b[^>]*\\bname="m365_email"[^>]*>/s', $loginPageSource) === 1
        && preg_match('/<input\\b[^>]*\\bname="m365_email"[^>]*\\brequired\\b[^>]*>/s', $loginPageSource) !== 1,
    'The Microsoft sign-in email field must remain optional so the sign-in button can be clicked immediately.'
);
secureit_contract_test_assert(
    !str_contains($loginPageSource, "'pageTitle' => 'Sign in to SecureIT'")
        && !str_contains($loginPageSource, "'pageIntro' => '")
        && !str_contains($loginPageSource, "'navCta' =>")
        && !str_contains($loginPageSource, '<strong>Existing customers</strong>')
        && str_contains($loginPageSource, 'Use your M365 account to login to your SecureIT portal.')
        && str_contains($loginPageSource, 'SecureIT will redirect you to Microsoft after you press the button.')
        && !str_contains($loginPageSource, 'Microsoft Entra authentication is enabled for this environment.')
        && !str_contains($loginPageSource, 'Existing customers use your business / school email address'),
    'The login page must not render the redundant hero, header CTA, customer card, or environment-specific authentication copy.'
);

$maesterManifest = secureit_maester_runtime_manifest();
$maesterPermissions = secureit_maester_graph_application_permissions();
secureit_contract_test_assert(($maesterManifest['maesterVersion'] ?? '') === '2.2.0', 'The SecureIT runtime manifest should pin Maester 2.2.0.');
secureit_contract_test_assert(count($maesterPermissions) === 25, 'The Maester 2.2.0 manifest should expose all 25 default read-only Graph permissions.');
$maesterPermissionNames = array_column($maesterPermissions, 'name');
secureit_contract_test_assert(in_array('RoleEligibilitySchedule.Read.Directory', $maesterPermissionNames, true), 'The manifest should use the read-only role eligibility permission.');
secureit_contract_test_assert(!in_array('RoleEligibilitySchedule.ReadWrite.Directory', $maesterPermissionNames, true), 'The manifest should not request the obsolete read-write role eligibility permission.');

$syntheticToken = secureit_base64url_encode(json_encode(['alg' => 'none'], JSON_THROW_ON_ERROR))
    . '.' . secureit_base64url_encode(json_encode(['roles' => $maesterPermissionNames], JSON_THROW_ON_ERROR))
    . '.test-signature';
$syntheticRoles = secureit_entra_graph_application_roles_from_token($syntheticToken);
secureit_contract_test_assert(count($syntheticRoles) === 25, 'Graph application-role inspection should retain every granted permission.');
secureit_contract_test_assert(in_array('ThreatHunting.Read.All', $syntheticRoles, true), 'Graph application-role inspection should expose granted Maester permissions.');

$workflowDefinition = file_get_contents(__DIR__ . '/../.github/workflows/secureit-production.yml');
secureit_contract_test_assert(str_contains($workflowDefinition, 'config/maester-runtime.json'), 'The production workflow should load the shared Maester runtime manifest.');
secureit_contract_test_assert(!str_contains($workflowDefinition, 'MAESTER_TESTS_REF'), 'The production workflow should not mix an independent test-suite ref with the pinned module.');
secureit_contract_test_assert(!str_contains($workflowDefinition, 'maester365/maester-tests.git'), 'The production workflow should use the pinned module bundled tests only.');
secureit_contract_test_assert(str_contains($workflowDefinition, 'Get-MtGraphScope'), 'The production workflow should validate the permission manifest against the loaded Maester module.');
secureit_contract_test_assert(str_contains($productionWorkflowScript, 'The pinned Maester package and SecureIT catalogue are not aligned.'), 'Production test selection should fail closed when an allowlisted test is absent.');
secureit_contract_test_assert(str_contains($productionWorkflowScript, 'SecureIT does not mix independently versioned test sources with the pinned module.'), 'The runner should require the test suite bundled with the loaded module.');

$onboardingPage = file_get_contents(__DIR__ . '/../app/onboard.php');
secureit_contract_test_assert(str_contains($onboardingPage, 'secureit_maester_graph_application_permissions()'), 'Onboarding should render permissions from the Maester runtime manifest.');
secureit_contract_test_assert(str_contains($onboardingPage, 'secureit_entra_validate_maester_graph_permissions('), 'Onboarding should validate Graph permissions before saving a tenant.');
secureit_contract_test_assert(!str_contains($onboardingPage, '<tr><td>Policy.Read.All</td>'), 'Onboarding should not contain the legacy hard-coded seven-permission table.');

echo "SecureIT canonical scoring test passed.\n";
