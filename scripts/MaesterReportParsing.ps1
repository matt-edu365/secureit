function Get-SecureItEmbeddedMaesterSummary {
    param(
        [Parameter(Mandatory = $true)]
        [string]$HtmlContent
    )

    $summaryPattern = 'var\s+[A-Za-z_$][\w$]*\s*=\s*(\{"Result".*?\})\s*;'
    $summaryMatch = [regex]::Match(
        $HtmlContent,
        $summaryPattern,
        [System.Text.RegularExpressions.RegexOptions]::Singleline
    )

    if (-not $summaryMatch.Success) {
        return $null
    }

    try {
        return $summaryMatch.Groups[1].Value | ConvertFrom-Json -ErrorAction Stop
    }
    catch {
        return $null
    }
}
