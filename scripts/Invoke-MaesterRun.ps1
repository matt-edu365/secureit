param(
    [Parameter(Mandatory = $true)]
    [string]$TenantKey,
    [string]$TenantName,
    [string]$OutputRoot = (Join-Path (Join-Path $PSScriptRoot '..') 'output'),
    [string]$TenantId,
    [string]$ClientId,
    [ValidateSet('oidc','certificate','client-secret')]
    [string]$AuthMode = 'client-secret',
    [string]$ClientSecret,
    [string]$CertificateBase64,
    [string]$CertificatePassword,
    [string]$WebsiteBaseUrl = '',
    [string]$ConfigPath = (Join-Path (Join-Path $PSScriptRoot '..') 'config/tenants.json')
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Ensure-DirectoryClean {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (Test-Path -LiteralPath $Path) {
        Get-ChildItem -LiteralPath $Path -Force | Remove-Item -Recurse -Force
    }
    else {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

function Connect-MaesterTenant {
    param(
        [Parameter(Mandatory = $true)][string]$TenantId,
        [Parameter(Mandatory = $true)][string]$ClientId,
        [Parameter(Mandatory = $true)][string]$AuthMode,
        [string]$ClientSecret,
        [string]$CertificateBase64,
        [string]$CertificatePassword
    )

    Write-Host "Connecting to tenant using auth mode: $AuthMode"

    switch ($AuthMode) {
        'oidc' {
            throw 'OIDC auth is not implemented in this scaffold yet.'
        }
        'client-secret' {
            if (-not $ClientSecret) {
                throw 'ClientSecret is required for client-secret auth.'
            }

            $secureSecret = ConvertTo-SecureString -String $ClientSecret -AsPlainText -Force
            $credential = New-Object System.Management.Automation.PSCredential($ClientId, $secureSecret)

            Connect-MgGraph -TenantId $TenantId -ClientSecretCredential $credential -NoWelcome
            $context = Get-MgContext
            if (-not $context) {
                throw 'Connect-MgGraph did not create a Graph context for client-secret authentication.'
            }
            Write-Host "Connected to Microsoft Graph app-only context for tenant $TenantId"
        }
        'certificate' {
            if (-not $CertificateBase64) {
                throw 'CertificateBase64 is required for certificate auth.'
            }

            $certBytes = [Convert]::FromBase64String($CertificateBase64)
            $securePassword = if ($CertificatePassword) {
                ConvertTo-SecureString -String $CertificatePassword -AsPlainText -Force
            }
            else {
                ConvertTo-SecureString -String '' -AsPlainText -Force
            }

            $certificate = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2
            $certificate.Import($certBytes, $securePassword, [System.Security.Cryptography.X509Certificates.X509KeyStorageFlags]::Exportable)

            Connect-MgGraph -TenantId $TenantId -ClientId $ClientId -Certificate $certificate -NoWelcome
            $context = Get-MgContext
            if (-not $context) {
                throw 'Connect-MgGraph did not create a Graph context for certificate authentication.'
            }
            Write-Host "Connected to Microsoft Graph certificate context for tenant $TenantId"
        }
        default {
            throw "Unsupported auth mode: $AuthMode"
        }
    }
}

function Get-MaesterTestsPath {
    $maesterModule = Get-Module -ListAvailable -Name Maester | Sort-Object Version -Descending | Select-Object -First 1
    if (-not $maesterModule) {
        throw 'Maester module is not installed.'
    }

    $moduleRoot = Split-Path -Parent $maesterModule.Path
    $candidates = @(
        (Join-Path $moduleRoot 'tests'),
        (Join-Path $moduleRoot 'Test'),
        (Join-Path $moduleRoot 'Tests'),
        (Join-Path $HOME '.maester/tests'),
        (Join-Path $HOME '.config/maester/tests')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            $testFiles = Get-ChildItem -LiteralPath $candidate -Recurse -Include '*.Tests.ps1','*.ps1' -File -ErrorAction SilentlyContinue
            if ($testFiles) {
                return $candidate
            }
        }
    }

    throw "Unable to find installed Maester tests. Checked: $($candidates -join ', ')"
}

if (-not $TenantName -or -not $TenantId -or -not $ClientId -or (($AuthMode -eq 'client-secret') -and -not $ClientSecret) -or (($AuthMode -eq 'certificate') -and -not $CertificateBase64)) {
    $resolvedJson = & (Join-Path $PSScriptRoot 'Get-ResolvedTenantConfig.ps1') -TenantKey $TenantKey -ConfigPath $ConfigPath
    $resolved = $resolvedJson | ConvertFrom-Json

    if (-not $TenantName) { $TenantName = [string]$resolved.name }
    if (-not $TenantId) { $TenantId = [string]$resolved.tenantId }
    if (-not $ClientId) { $ClientId = [string]$resolved.clientId }
    if (-not $AuthMode) { $AuthMode = [string]$resolved.authMode }
    if (-not $ClientSecret) { $ClientSecret = [string]$resolved.clientSecret }
    if (-not $CertificateBase64) { $CertificateBase64 = [string]$resolved.certificateBase64 }
    if (-not $CertificatePassword) { $CertificatePassword = [string]$resolved.certificatePassword }
    if (-not $WebsiteBaseUrl) { $WebsiteBaseUrl = [string]$resolved.reportBaseUrl }
}

if (-not $TenantName) { throw 'TenantName could not be resolved.' }
if (-not $TenantId) { throw 'TenantId could not be resolved.' }
if (-not $ClientId) { throw 'ClientId could not be resolved.' }
if ($AuthMode -eq 'client-secret' -and -not $ClientSecret) { throw 'ClientSecret could not be resolved.' }
if ($AuthMode -eq 'certificate' -and -not $CertificateBase64) { throw 'CertificateBase64 could not be resolved.' }

$timestamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$datedFolderName = Get-Date -Format 'yyyy-MM-dd'
$tenantRoot = Join-Path $OutputRoot $TenantKey
$historyDir = Join-Path $tenantRoot "history/$datedFolderName/$timestamp"
$latestDir = Join-Path $tenantRoot 'latest'
$tempLatestDir = Join-Path $tenantRoot '_latest_build'

New-Item -ItemType Directory -Force -Path $historyDir | Out-Null
New-Item -ItemType Directory -Force -Path $tenantRoot | Out-Null
Ensure-DirectoryClean -Path $tempLatestDir

$htmlReportPath = Join-Path $historyDir 'index.html'
$jsonReportPath = Join-Path $historyDir 'results.json'
$summaryPath = Join-Path $historyDir 'summary.json'

Import-Module Microsoft.Graph.Authentication -ErrorAction Stop
Import-Module Maester -ErrorAction Stop

Connect-MaesterTenant -TenantId $TenantId -ClientId $ClientId -AuthMode $AuthMode -ClientSecret $ClientSecret -CertificateBase64 $CertificateBase64 -CertificatePassword $CertificatePassword

$testsPath = Get-MaesterTestsPath
Write-Host "Running Maester for tenant [$TenantKey] $TenantName using tests at: $testsPath"
$result = Invoke-Maester -Path $testsPath -PassThru

if (-not $result) {
    throw "Invoke-Maester returned no results for tests path '$testsPath'."
}

$result | ConvertTo-Json -Depth 20 | Set-Content -Path $jsonReportPath -Encoding UTF8

$html = Get-MtHtmlReport -MaesterResults $result
if (-not $html) {
    throw 'Get-MtHtmlReport returned no HTML output.'
}
$html | Set-Content -Path $htmlReportPath -Encoding UTF8

$failed = @($result.TestResult | Where-Object { $_.Result -eq 'Failed' }).Count
$passed = @($result.TestResult | Where-Object { $_.Result -eq 'Passed' }).Count
$skipped = @($result.TestResult | Where-Object { $_.Result -eq 'Skipped' }).Count
$total = @($result.TestResult).Count

$reportUrl = ''
if ($WebsiteBaseUrl) {
    $reportUrl = ($WebsiteBaseUrl.TrimEnd('/') + '/latest/index.html')
}

$summary = [ordered]@{
    tenantKey = $TenantKey
    tenantName = $TenantName
    generatedAt = (Get-Date).ToString('o')
    timestamp = $timestamp
    total = $total
    passed = $passed
    failed = $failed
    skipped = $skipped
    changed = 0
    critical = 0
    reportUrl = $reportUrl
    notes = @()
}

$summary | ConvertTo-Json -Depth 10 | Set-Content -Path $summaryPath -Encoding UTF8

Ensure-DirectoryClean -Path $tempLatestDir
Copy-Item -Path (Join-Path $historyDir '*') -Destination $tempLatestDir -Recurse -Force

Ensure-DirectoryClean -Path $latestDir
Copy-Item -Path (Join-Path $tempLatestDir '*') -Destination $latestDir -Recurse -Force

"MAESTER_TENANT_KEY=$TenantKey" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_TENANT_NAME=$TenantName" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_HISTORY_DIR=$historyDir" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_LATEST_DIR=$latestDir" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_SUMMARY_PATH=$summaryPath" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_FAILED_COUNT=$failed" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_TOTAL_COUNT=$total" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
"MAESTER_REPORT_URL=$reportUrl" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8

Write-Host "Maester run complete for [$TenantKey]. Total: $total, Passed: $passed, Failed: $failed, Skipped: $skipped"
