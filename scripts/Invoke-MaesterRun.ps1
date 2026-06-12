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
    [string]$ConfigPath = (Join-Path (Join-Path $PSScriptRoot '..') 'config/tenants.json'),
    [ValidateSet('full','light','exchange-online')]
    [string]$TestProfile = 'light'
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
        [string]$CertificatePassword,
        [bool]$RequireExchangeOnline = $false
    )

    Write-Host "Connecting to tenant using auth mode: $AuthMode"

    $certificate = $null

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

            if ($RequireExchangeOnline) {
                Write-Warning 'Exchange Online connection requested, but current auth mode is client-secret. ExchangeOnlineManagement app-only auth typically requires certificate-based authentication. EXO tests may still be skipped until certificate auth is configured.'
            }
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

            $certificate = [System.Security.Cryptography.X509Certificates.X509Certificate2]::new(
                $certBytes,
                $securePassword,
                [System.Security.Cryptography.X509Certificates.X509KeyStorageFlags]::Exportable
            )

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

    if ($RequireExchangeOnline) {
        if (-not (Get-Module -ListAvailable -Name ExchangeOnlineManagement)) {
            throw 'Exchange Online connection requested but ExchangeOnlineManagement module is not installed.'
        }

        Import-Module ExchangeOnlineManagement -Force

        if ($AuthMode -eq 'certificate') {
            Write-Host "Connecting to Exchange Online using certificate-based app authentication"
            Connect-ExchangeOnline -AppId $ClientId -Certificate $certificate -Organization $TenantId -ShowBanner:$false
            Write-Host "Connected to Exchange Online app-only context for tenant $TenantId"
        }
        elseif ($AuthMode -eq 'client-secret') {
            Write-Warning 'Skipping Connect-ExchangeOnline because certificate-based app authentication is not configured in this workflow yet.'
        }
    }
}

function Get-MaesterSelectedTestsPath {
    param(
        [Parameter(Mandatory = $true)][string]$TestsRoot,
        [Parameter(Mandatory = $true)][string]$Profile,
        [Parameter(Mandatory = $true)][string]$WorkingRoot
    )

    if ($Profile -eq 'full') {
        return $TestsRoot
    }

    $profilePatterns = @{
        'light' = @(
            'exchange',
            'exo',
            'mailbox',
            'transport',
            'accepteddomain',
            'dkim',
            'dmarc',
            'spf',
            'defender for office',
            'safe attachment',
            'safe links',
            'anti-phish',
            'anti spam',
            'authentication policy',
            'conditional access',
            'mfa',
            'security default',
            'tenant'
        )
        'exchange-online' = @(
            'exchange',
            'exo',
            'mailbox',
            'transport',
            'accepteddomain',
            'dkim',
            'dmarc',
            'spf',
            'defender for office',
            'safe attachment',
            'safe links',
            'anti-phish',
            'anti spam',
            'outbound spam',
            'inbound spam',
            'quarantine',
            'authentication policy'
        )
    }

    $selectedPatterns = $profilePatterns[$Profile]
    if (-not $selectedPatterns) {
        throw "Unsupported test profile '$Profile'."
    }

    $selectedRoot = Join-Path $WorkingRoot '_selected_tests'
    Ensure-DirectoryClean -Path $selectedRoot

    $candidateFiles = Get-ChildItem -Path $TestsRoot -Recurse -Include '*.Tests.ps1','*.ps1' -File -ErrorAction SilentlyContinue |
        Where-Object {
            $_.FullName -notmatch '[\\/](unit|internal|examples?|demo|sample|harness|manifest|module|help|build|testdata)[\\/]' -and
            $_.Name -notmatch '^(Failure|Help|Module|Manifest|PSScriptAnalyzer)\.Tests\.ps1$'
        }

    $matchedFiles = foreach ($file in $candidateFiles) {
        $content = $null
        try {
            $content = Get-Content -Raw -LiteralPath $file.FullName -ErrorAction Stop
        }
        catch {
            $content = ''
        }

        $haystacks = @(
            $file.FullName,
            $file.Name,
            $content
        ) -join "`n"

        if ($selectedPatterns | Where-Object { $haystacks -match [regex]::Escape($_) }) {
            $file
        }
    }

    $matchedFiles = @($matchedFiles | Sort-Object FullName -Unique)
    if (-not $matchedFiles) {
        $available = Get-ChildItem -Path $TestsRoot -Recurse -Include '*.Tests.ps1','*.ps1' -File -ErrorAction SilentlyContinue |
            Select-Object -First 50 -ExpandProperty FullName
        throw "No Maester test files matched profile '$Profile' from root '$TestsRoot'. Sample available files: $($available -join ', ')"
    }

    foreach ($file in $matchedFiles) {
        $relativePath = [System.IO.Path]::GetRelativePath($TestsRoot, $file.FullName)
        $destinationPath = Join-Path $selectedRoot $relativePath
        $destinationDir = Split-Path -Parent $destinationPath
        New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
        Copy-Item -LiteralPath $file.FullName -Destination $destinationPath -Force
    }

    Write-Host "Selected $($matchedFiles.Count) Maester test files for profile '$Profile'."
    $matchedFiles | Select-Object -ExpandProperty FullName | ForEach-Object { Write-Host " - $_" }

    return $selectedRoot
}

function Get-MaesterTestsPath {
    $maesterModule = Get-Module -ListAvailable -Name Maester | Sort-Object Version -Descending | Select-Object -First 1
    if (-not $maesterModule) {
        throw 'Maester module is not installed.'
    }

    $moduleRoot = Split-Path -Parent $maesterModule.Path
    $repoRoot = Split-Path -Parent $PSScriptRoot
    $candidates = @(
        (Join-Path $repoRoot 'maester-tests'),
        (Join-Path (Get-Location) 'maester-tests'),
        (Join-Path $moduleRoot 'maester-tests'),
        (Join-Path $HOME '.maester/maester-tests'),
        (Join-Path $HOME '.config/maester/maester-tests')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            $testFiles = Get-ChildItem -Path $candidate -Recurse -Include '*.Tests.ps1','*.ps1' -File -ErrorAction SilentlyContinue
            if ($testFiles) {
                return $candidate
            }
        }
    }

    throw "Unable to find installed Maester tenant-facing tests. Checked: $($candidates -join ', ')"
}

function Write-MaesterOutputs {
    param(
        [Parameter(Mandatory = $true)][hashtable]$Summary,
        [Parameter(Mandatory = $true)][string]$SummaryPath,
        [Parameter(Mandatory = $true)][string]$JsonPath,
        [Parameter(Mandatory = $true)][string]$HistoryDir,
        [Parameter(Mandatory = $true)][string]$TempLatestDir,
        [Parameter(Mandatory = $true)][string]$LatestDir,
        [Parameter(Mandatory = $true)][string]$TenantKey,
        [Parameter(Mandatory = $true)][string]$TenantName
    )

    $Summary | ConvertTo-Json -Depth 10 | Set-Content -Path $SummaryPath -Encoding UTF8
    $Summary | ConvertTo-Json -Depth 10 | Set-Content -Path $JsonPath -Encoding UTF8

    Ensure-DirectoryClean -Path $TempLatestDir
    Copy-Item -Path (Join-Path $HistoryDir '*') -Destination $TempLatestDir -Recurse -Force
    Ensure-DirectoryClean -Path $LatestDir
    Copy-Item -Path (Join-Path $TempLatestDir '*') -Destination $LatestDir -Recurse -Force

    "MAESTER_TENANT_KEY=$TenantKey" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_TENANT_NAME=$TenantName" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_HISTORY_DIR=$HistoryDir" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_LATEST_DIR=$LatestDir" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_SUMMARY_PATH=$SummaryPath" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_FAILED_COUNT=$($Summary.failed)" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_TOTAL_COUNT=$($Summary.total)" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
    "MAESTER_REPORT_URL=$($Summary.reportUrl)" | Out-File -FilePath $env:GITHUB_ENV -Append -Encoding utf8
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

$legacyPester = Join-Path $HOME '.local/share/powershell/Modules/Pester/4.0.3'
if (Test-Path -LiteralPath $legacyPester) {
    Remove-Item -LiteralPath $legacyPester -Recurse -Force -ErrorAction SilentlyContinue
}

Import-Module Microsoft.Graph.Authentication -ErrorAction Stop
Import-Module Pester -RequiredVersion 5.7.1 -ErrorAction Stop
Import-Module Maester -ErrorAction Stop

$requireExchangeOnline = $TestProfile -eq 'exchange-online'
Connect-MaesterTenant -TenantId $TenantId -ClientId $ClientId -AuthMode $AuthMode -ClientSecret $ClientSecret -CertificateBase64 $CertificateBase64 -CertificatePassword $CertificatePassword -RequireExchangeOnline:$requireExchangeOnline

$testsPath = Get-MaesterTestsPath
$selectedTestsPath = Get-MaesterSelectedTestsPath -TestsRoot $testsPath -Profile $TestProfile -WorkingRoot $tenantRoot
Write-Host "Running Maester for tenant [$TenantKey] $TenantName using test profile '$TestProfile' at: $selectedTestsPath"
$env:CI = 'true'
$env:BROWSER = '/bin/true'
$global:LASTEXITCODE = 0
$result = $null
$invokeMaesterFailed = $false
$invokeParams = @{
    Path = $selectedTestsPath
    PassThru = $true
}
if ($TestProfile -eq 'light') {
    $invokeParams['ExcludeTag'] = @('Preview')
}
try {
    $result = Invoke-Maester @invokeParams
}
catch {
    $invokeMaesterFailed = $true
    Write-Warning ("Invoke-Maester raised an exception but generated artifacts may still be usable: " + $_.Exception.Message)
}


$htmlCandidate = Get-ChildItem -Path (Join-Path (Get-Location) 'test-results') -Filter 'TestResults-*.html' -File -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($htmlCandidate) {
    Copy-Item -Path $htmlCandidate.FullName -Destination $htmlReportPath -Force
}

$testResults = @()
if ($null -ne $result) {
    if ($result.PSObject.Properties.Name -contains 'TestResult') {
        $testResults = @($result.TestResult)
    }
    elseif ($result -is [System.Array]) {
        $testResults = @($result)
    }
}

if (-not $testResults) {
    Write-Warning 'Invoke-Maester returned no consumable test result records; attempting fallback parse from generated report artifacts.'
    if (-not $htmlCandidate) {
        throw 'Invoke-Maester returned no consumable test result records and no HTML report artifact was found for fallback.'
    }

    $htmlFallback = Get-Content -Raw -Path $htmlCandidate.FullName

    $summaryMap = @{
        'passed' = 0
        'failed' = 0
        'investigate' = 0
        'skipped' = 0
        'error' = 0
        'not run' = 0
    }

    $patterns = @{
        'passed' = 'Tests Passed[^:]*:\s*(\d+)'
        'failed' = 'Failed[^:]*:\s*(\d+)'
        'investigate' = 'Investigate[^:]*:\s*(\d+)'
        'skipped' = 'Skipped[^:]*:\s*(\d+)'
        'error' = 'Error[^:]*:\s*(\d+)'
        'not run' = 'Not Run[^:]*:\s*(\d+)'
    }

    foreach ($key in $patterns.Keys) {
        $m = [regex]::Match($htmlFallback, $patterns[$key], [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
        if ($m.Success) {
            $summaryMap[$key] = [int]$m.Groups[1].Value
        }
    }

    $reportUrl = ''
    if ($WebsiteBaseUrl) {
        $reportUrl = ($WebsiteBaseUrl.TrimEnd('/') + '/latest/index.html')
    }

    $summary = [ordered]@{
        tenantKey = $TenantKey
        tenantName = $TenantName
        generatedAt = (Get-Date).ToString('o')
        timestamp = $timestamp
        total = ($summaryMap['passed'] + $summaryMap['failed'] + $summaryMap['investigate'] + $summaryMap['skipped'] + $summaryMap['error'] + $summaryMap['not run'])
        passed = $summaryMap['passed']
        failed = $summaryMap['failed']
        skipped = $summaryMap['skipped']
        changed = 0
        critical = 0
        reportUrl = $reportUrl
        notes = @('Fallback summary extracted from generated HTML report because structured Maester results were unavailable.')
    }

    Write-MaesterOutputs -Summary $summary -SummaryPath $summaryPath -JsonPath $jsonReportPath -HistoryDir $historyDir -TempLatestDir $tempLatestDir -LatestDir $latestDir -TenantKey $TenantKey -TenantName $TenantName
    $global:LASTEXITCODE = 0
    Write-Host "Maester fallback report handling complete for [$TenantKey]. Total: $($summary.total), Passed: $($summary.passed), Failed: $($summary.failed), Skipped: $($summary.skipped)"
    exit 0
}

$flatResults = @()
foreach ($test in $testResults) {
    $flatResults += [ordered]@{
        Name = if ($test.Name) { [string]$test.Name } else { '' }
        Describe = if ($test.Describe) { [string]$test.Describe } else { '' }
        Context = if ($test.Context) { [string]$test.Context } else { '' }
        Result = if ($test.Result) { [string]$test.Result } else { '' }
        FailureMessage = if ($test.FailureMessage) { [string]$test.FailureMessage } else { '' }
        Path = if ($test.Path) { [string]$test.Path } else { '' }
        Tag = if ($test.Tag) { @($test.Tag) } else { @() }
    }
}

$failed = @($testResults | Where-Object { $_.Result -eq 'Failed' }).Count
$passed = @($testResults | Where-Object { $_.Result -eq 'Passed' }).Count
$skipped = @($testResults | Where-Object { $_.Result -eq 'Skipped' }).Count
$total = @($testResults).Count

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

$persistedResult = [ordered]@{
    TenantKey = $TenantKey
    TenantName = $TenantName
    GeneratedAt = (Get-Date).ToString('o')
    Summary = [ordered]@{
        Total = $total
        Passed = $passed
        Failed = $failed
        Skipped = $skipped
        Error = @($testResults | Where-Object { $_.Result -eq 'Error' }).Count
        Investigate = @($testResults | Where-Object { $_.Result -eq 'Investigate' }).Count
    }
    Tests = $flatResults
}

$persistedResult | ConvertTo-Json -Depth 10 | Set-Content -Path $jsonReportPath -Encoding UTF8
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

$global:LASTEXITCODE = 0
Write-Host "Maester run complete for [$TenantKey]. Total: $total, Passed: $passed, Failed: $failed, Skipped: $skipped"
exit 0
