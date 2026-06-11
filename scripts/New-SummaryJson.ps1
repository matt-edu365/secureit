param(
    [string]$TenantName = 'Target Tenant',
    [string]$OutputPath,
    [int]$Total = 0,
    [int]$Passed = 0,
    [int]$Failed = 0,
    [int]$Skipped = 0,
    [int]$Changed = 0,
    [int]$Critical = 0,
    [string]$ReportUrl = '',
    [string[]]$Notes = @()
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

if (-not $OutputPath) {
    throw 'OutputPath is required.'
}

$summary = [ordered]@{
    tenantName = $TenantName
    generatedAt = (Get-Date).ToString('o')
    total = $Total
    passed = $Passed
    failed = $Failed
    skipped = $Skipped
    changed = $Changed
    critical = $Critical
    reportUrl = $ReportUrl
    notes = $Notes
}

$summary | ConvertTo-Json -Depth 5 | Set-Content -Path $OutputPath -Encoding UTF8
Write-Host "Wrote summary JSON to $OutputPath"
