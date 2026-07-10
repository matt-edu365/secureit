function Invoke-SecureIT365InspectInspector {
    param(
        [Parameter(Mandatory = $true)]
        [string]$InspectorName
    )

    $repoRoot = Split-Path -Parent $PSScriptRoot
    $inspectorPath = Join-Path (Join-Path $repoRoot 'inspectors') ($InspectorName + '.ps1')
    if (-not (Test-Path -LiteralPath $inspectorPath)) {
        throw "SecureIT 365Inspect inspector file was not found: $inspectorPath"
    }

    $outRoot = Join-Path $TestDrive '365inspect-output'
    New-Item -ItemType Directory -Force -Path $outRoot | Out-Null
    Set-Variable -Name out_path -Value $outRoot -Scope Global -Force

    & $inspectorPath
}
