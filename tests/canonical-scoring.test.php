<?php

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
secureit_contract_test_assert(count($catalog['controls'] ?? []) === 101, 'The production catalog must contain 101 controls.');

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
    ($sourceEvidenceControls['MTCISABLOCKLEGACYAUTH']['status'] ?? '') === 'pass',
    'A Maester result must match its explicit source-file evidence mapping.'
);
secureit_contract_test_assert(
    ($sourceEvidenceControls['MTMDEANTIVIRUSPOLICY']['status'] ?? '') === 'partial',
    'Multiple results from one explicitly mapped source file must be evaluated together.'
);
secureit_contract_test_assert(
    count($sourceEvidenceControls['MTMDEANTIVIRUSPOLICY']['matchedIds'] ?? []) === 2,
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

foreach (['fabrikam-prod' => 100, 'contoso-prod' => 73] as $tenantKey => $expectedScore) {
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
                if (($control['id'] ?? '') === 'INSPECTDOMAINEXPIRATION') {
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
    'SecureIT-Production-101 must not include Conditional Access What If in its production allowlist.'
);
secureit_contract_test_assert(
    str_contains($productionWorkflowScript, 'TODO: Add Conditional Access What If as a separate dedicated feature/profile'),
    'The Conditional Access What If production exclusion should remain explicitly tracked as a follow-up feature.'
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
