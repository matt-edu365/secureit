param(
    [Parameter(Mandatory = $true)]
    [string]$TenantKey,

    [Parameter(Mandatory = $true)]
    [string]$SourcePath,

    [string]$DestinationRoot = (Join-Path (Join-Path $PSScriptRoot '..') 'data/reports'),

    [switch]$Clean
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

if (-not (Test-Path -LiteralPath $SourcePath)) {
    throw "SourcePath not found: $SourcePath"
}

$sourceItem = Get-Item -LiteralPath $SourcePath
$tenantDestination = Join-Path $DestinationRoot $TenantKey
Ensure-Directory -Path $DestinationRoot

if ($Clean -and (Test-Path -LiteralPath $tenantDestination)) {
    Remove-Item -LiteralPath $tenantDestination -Recurse -Force
}

Ensure-Directory -Path $tenantDestination

if ($sourceItem.PSIsContainer) {
    Copy-Item -Path (Join-Path $SourcePath '*') -Destination $tenantDestination -Recurse -Force
}
else {
    throw 'SourcePath must be a directory containing the tenant report bundle.'
}

$latestSummary = Join-Path $tenantDestination 'latest/summary.json'
if (-not (Test-Path -LiteralPath $latestSummary)) {
    throw "Imported bundle does not contain latest/summary.json at: $latestSummary"
}

Write-Host "Imported tenant report bundle for [$TenantKey] into $tenantDestination"
