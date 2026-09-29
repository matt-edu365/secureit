<?php

function secureit_workflow_security_test_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$workflowPath = __DIR__ . '/../.github/workflows/secureit-production.yml';
$workflow = file_get_contents($workflowPath);
secureit_workflow_security_test_assert(is_string($workflow), 'The production workflow could not be read.');

$lines = preg_split('/\R/', $workflow) ?: [];
$runIndent = null;
foreach ($lines as $lineNumber => $line) {
    $indent = strlen($line) - strlen(ltrim($line, ' '));
    if ($runIndent !== null && trim($line) !== '' && $indent <= $runIndent) {
        $runIndent = null;
    }

    if (preg_match('/^\s*run:\s*\|\s*$/', $line) === 1) {
        $runIndent = $indent;
        continue;
    }

    if ($runIndent !== null) {
        secureit_workflow_security_test_assert(
            !str_contains($line, '${{ matrix.'),
            'Matrix data is interpolated directly into executable workflow source at line ' . ($lineNumber + 1) . '.'
        );
        secureit_workflow_security_test_assert(
            !str_contains($line, '${{ github.event.inputs.'),
            'Dispatch input is interpolated directly into executable workflow source at line ' . ($lineNumber + 1) . '.'
        );
    }
}

secureit_workflow_security_test_assert(
    str_contains($workflow, 'MATRIX_TENANT_NAME: ${{ matrix.tenantName }}')
    && str_contains($workflow, 'name = $env:MATRIX_TENANT_NAME'),
    'Tenant matrix values must cross into PowerShell through environment variables.'
);
secureit_workflow_security_test_assert(
    str_contains($workflow, "'SECUREIT_' + [Guid]::NewGuid().ToString('N')"),
    'GitHub environment values must use unpredictable multiline delimiters.'
);
secureit_workflow_security_test_assert(
    !str_contains($workflow, 'tenant_summary<<EOF'),
    'Untrusted tenant summary text must not use a fixed GitHub output delimiter.'
);

$apacheConfig = file_get_contents(__DIR__ . '/../docker/apache-site.conf');
secureit_workflow_security_test_assert(is_string($apacheConfig), 'The Apache configuration could not be read.');
secureit_workflow_security_test_assert(
    !str_contains($apacheConfig, 'AliasMatch'),
    'The mounted report tree must not be exposed through AliasMatch.'
);
secureit_workflow_security_test_assert(
    str_contains($apacheConfig, '/report-asset.php?tenant=$1&path=$2'),
    'Tenant report URLs must be routed through the authenticated report gateway.'
);

echo "SecureIT workflow security test passed.\n";
