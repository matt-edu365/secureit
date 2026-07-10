$customTestsRoot = Split-Path -Parent $PSScriptRoot
$inspectorsPath = Join-Path (Join-Path $customTestsRoot 'data') 'inspectors.json'
$inspectors = (Get-Content -Raw -LiteralPath $inspectorsPath | ConvertFrom-Json).inspectors

. (Join-Path (Join-Path $customTestsRoot 'support') 'Invoke-SecureIT365InspectInspector.ps1')

Describe 'SecureIT 365Inspect - ported inspectors' {
    foreach ($inspector in $inspectors) {
        It $($inspector.inspector) {
            (Invoke-SecureIT365InspectInspector -InspectorName $inspector.inspector) | Should -BeNullOrEmpty
        }
    }
}
