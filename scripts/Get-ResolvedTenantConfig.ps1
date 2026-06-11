param(
    [Parameter(Mandatory = $true)]
    [string]$TenantKey,

    [string]$ConfigPath = (Join-Path (Join-Path $PSScriptRoot '..') 'config/tenants.json')
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$tenantJson = & (Join-Path $PSScriptRoot 'Get-TenantConfig.ps1') -TenantKey $TenantKey -ConfigPath $ConfigPath
$tenant = $tenantJson | ConvertFrom-Json

$certificateBase64 = $null
$certificatePassword = $null
$clientSecret = $null

if ($tenant.certificateSecretName) {
    $certificateBase64 = [Environment]::GetEnvironmentVariable([string]$tenant.certificateSecretName)
}
if ($tenant.certificatePasswordSecretName) {
    $certificatePassword = [Environment]::GetEnvironmentVariable([string]$tenant.certificatePasswordSecretName)
}
if ($tenant.clientSecretName) {
    $clientSecret = [Environment]::GetEnvironmentVariable([string]$tenant.clientSecretName)
}

$result = [ordered]@{
    id = [string]$tenant.id
    name = [string]$tenant.name
    tenantId = [string]$tenant.tenantId
    clientId = [string]$tenant.clientId
    authMode = if ($tenant.authMode) { [string]$tenant.authMode } else { 'client-secret' }
    clientSecretName = if ($tenant.clientSecretName) { [string]$tenant.clientSecretName } else { '' }
    clientSecret = $clientSecret
    certificateBase64 = $certificateBase64
    certificatePassword = $certificatePassword
    reportBaseUrl = if ($tenant.reportBaseUrl) { [string]$tenant.reportBaseUrl } else { '' }
    emailTo = if ($tenant.emailTo) { [string]$tenant.emailTo } else { '' }
}

$result | ConvertTo-Json -Depth 10
