param(
    [Parameter(Mandatory = $true)]
    [string]$TenantKey,

    [string]$ConfigPath = (Join-Path (Join-Path $PSScriptRoot '..') 'config/tenants.json')
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

if (-not (Test-Path -LiteralPath $ConfigPath)) {
    throw "Tenant config file not found: $ConfigPath"
}

$config = Get-Content -Raw -Path $ConfigPath | ConvertFrom-Json
if (-not $config.tenants) {
    throw 'No tenants array found in tenant config file.'
}

$tenant = @($config.tenants | Where-Object { $_.id -eq $TenantKey })
if ($tenant.Count -eq 0) {
    throw "Tenant '$TenantKey' was not found in $ConfigPath"
}
if ($tenant.Count -gt 1) {
    throw "Tenant '$TenantKey' is defined more than once in $ConfigPath"
}

$tenant[0] | ConvertTo-Json -Depth 10
