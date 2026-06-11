param(
    [Parameter(Mandatory = $true)]
    [string]$SourcePath,

    [Parameter(Mandatory = $true)]
    [string]$WebsiteHost,

    [Parameter(Mandatory = $true)]
    [string]$Username,

    [Parameter(Mandatory = $true)]
    [string]$Password,

    [string]$RemotePath = '/maester/'
)

$ErrorActionPreference = 'Stop'

Write-Host 'TODO: Implement SFTP/FTPS or API-based upload of report files to website.'
Write-Host "SourcePath: $SourcePath"
Write-Host "WebsiteHost: $WebsiteHost"
Write-Host "RemotePath: $RemotePath"

throw 'Publish-WebsiteReport.ps1 is scaffolded only and still needs implementation.'
