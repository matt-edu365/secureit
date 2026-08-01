function Get-SecureItEmbeddedMaesterSummary {
    param(
        [Parameter(Mandatory = $true)]
        [string]$HtmlContent
    )

    $assignmentPattern = '(?<![\w$])(?:var|let|const)\s+[A-Za-z_$][\w$]*\s*=\s*'
    $assignmentMatches = [regex]::Matches(
        $HtmlContent,
        $assignmentPattern,
        [System.Text.RegularExpressions.RegexOptions]::CultureInvariant
    )
    $requiredSummaryProperties = @(
        'Result',
        'FailedCount',
        'PassedCount',
        'ErrorCount',
        'InvestigateCount',
        'SkippedCount',
        'NotRunCount',
        'TotalCount',
        'Tests'
    )

    foreach ($assignmentMatch in $assignmentMatches) {
        $objectStart = $assignmentMatch.Index + $assignmentMatch.Length
        if ($objectStart -ge $HtmlContent.Length -or $HtmlContent[$objectStart] -ne '{') {
            continue
        }

        $depth = 0
        $inString = $false
        $escaped = $false
        $objectEnd = -1

        for ($index = $objectStart; $index -lt $HtmlContent.Length; $index++) {
            $character = $HtmlContent[$index]

            if ($inString) {
                if ($escaped) {
                    $escaped = $false
                    continue
                }
                if ($character -eq '\') {
                    $escaped = $true
                    continue
                }
                if ($character -eq '"') {
                    $inString = $false
                }
                continue
            }

            if ($character -eq '"') {
                $inString = $true
                continue
            }
            if ($character -eq '{') {
                $depth++
                continue
            }
            if ($character -eq '}') {
                $depth--
                if ($depth -eq 0) {
                    $objectEnd = $index
                    break
                }
            }
        }

        if ($objectEnd -lt $objectStart) {
            continue
        }

        $json = $HtmlContent.Substring($objectStart, $objectEnd - $objectStart + 1)
        try {
            $summary = $json | ConvertFrom-Json -ErrorAction Stop
        }
        catch {
            continue
        }

        $propertyNames = @($summary.PSObject.Properties.Name)
        $missingProperty = $requiredSummaryProperties |
            Where-Object { $_ -notin $propertyNames } |
            Select-Object -First 1
        if ($null -ne $missingProperty) {
            continue
        }

        return $summary
    }

    return $null
}
