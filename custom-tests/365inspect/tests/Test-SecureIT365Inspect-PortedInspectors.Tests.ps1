$customTestsRoot = Split-Path -Parent $PSScriptRoot
$inspectorsPath = Join-Path (Join-Path $customTestsRoot 'data') 'inspectors.json'
$inspectors = (Get-Content -Raw -LiteralPath $inspectorsPath | ConvertFrom-Json).inspectors

. (Join-Path (Join-Path $customTestsRoot 'support') 'Invoke-SecureIT365InspectInspector.ps1')

Describe 'SecureIT 365Inspect - ported inspectors' {
    foreach ($inspector in $inspectors) {
        $inspectorJsonPath = Join-Path (Join-Path $customTestsRoot 'inspectors') ($inspector.inspector + '.json')
        $inspectorTitle = $inspector.inspector
        if (Test-Path -LiteralPath $inspectorJsonPath) {
            try {
                $inspectorMetadata = Get-Content -Raw -LiteralPath $inspectorJsonPath | ConvertFrom-Json
                $candidateTitle = [string]($inspectorMetadata.FindingName ?? '')
                if ($candidateTitle.Trim() -ne '') {
                    $inspectorTitle = $candidateTitle.Trim()
                }
            }
            catch {
            }
        }

        It "$($inspector.inspector): $inspectorTitle" {
            (Invoke-SecureIT365InspectInspector -InspectorName $inspector.inspector) | Should -BeNullOrEmpty
        }
    }
}
