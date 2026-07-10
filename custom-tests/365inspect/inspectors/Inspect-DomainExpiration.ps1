$ErrorActionPreference = "Stop"

$errorHandling = "$((Get-Item $PSScriptRoot).Parent.FullName)\Write-ErrorLog.ps1"

. $errorHandling


Function Inspect-DomainExpiration {
Try {

    function Try-ParseExpiryDate {
        param([Parameter(Mandatory = $false)]$Value)

        if ($null -eq $Value) {
            return $null
        }

        $parsedDate = $null
        if ([datetime]::TryParse([string]$Value, [ref]$parsedDate)) {
            return $parsedDate.Date
        }

        return $null
    }

    function Get-DomainExpiryFromRdap {
        param([Parameter(Mandatory = $true)][string]$Domain)

        $rdapResponse = Invoke-RestMethod -Uri "https://rdap.org/domain/$Domain" -ErrorAction Stop
        $expirationEvent = @($rdapResponse.events | Where-Object {
            $_.eventAction -match 'expiration|expiry|expire|registration expiration'
        } | Select-Object -First 1)

        if (-not $expirationEvent) {
            return $null
        }

        return Try-ParseExpiryDate -Value $expirationEvent[0].eventDate
    }

    function Get-DomainExpiryFromIcannLookup {
        param([Parameter(Mandatory = $true)][string]$Domain)

        $icannResponse = Invoke-WebRequest -Uri "https://lookup.icann.org/en/lookup?name=$Domain" -UseBasicParsing -ErrorAction Stop
        $patterns = @(
            'Registry Expiry Date:\s*([^<\r\n]+)',
            'Expiration Date:\s*([^<\r\n]+)',
            'Registry Expiration:\s*([^<\r\n]+)'
        )

        foreach ($pattern in $patterns) {
            $match = Select-String -InputObject $icannResponse.RawContent -Pattern $pattern -ErrorAction SilentlyContinue
            if ($match -and $match.Matches.Count -gt 0) {
                $parsedDate = Try-ParseExpiryDate -Value $match.Matches[0].Groups[1].Value
                if ($parsedDate) {
                    return $parsedDate
                }
            }
        }

        return $null
    }

    function Get-DomainExpiryFromWhoisCom {
        param([Parameter(Mandatory = $true)][string]$Domain)

        $whoisResponse = Invoke-WebRequest -Uri "https://whois.com/whois/$Domain" -UseBasicParsing -ErrorAction Stop
        $expiryMatch = Select-String -InputObject $whoisResponse.RawContent -Pattern 'Registry Expiry Date:\s*(.*)' -ErrorAction SilentlyContinue

        if ($null -eq $expiryMatch -or $expiryMatch.Matches.Count -eq 0) {
            return $null
        }

        return Try-ParseExpiryDate -Value $expiryMatch.Matches[0].Groups[1].Value
    }

    $domains = Get-AcceptedDomain |  Where-Object {$_.Name -notlike "*.onmicrosoft.com"}

    $results = @()

    foreach ($domain in $domains.DomainName){
        if ($domain -match '\.smtp\.') {
            Write-Verbose ("Skipping service-routing domain for expiry lookup: {0}" -f $domain)
            continue
        }

        $expDate = $null
        $lookupSourcesTried = New-Object System.Collections.Generic.List[string]

        try {
            $lookupSourcesTried.Add('RDAP')
            $expDate = Get-DomainExpiryFromRdap -Domain $domain
        }
        catch {
            Write-Verbose ("RDAP lookup failed for {0}: {1}" -f $domain, $_.Exception.Message)
        }

        if (-not $expDate) {
            try {
                $lookupSourcesTried.Add('lookup.icann.org')
                $expDate = Get-DomainExpiryFromIcannLookup -Domain $domain
            }
            catch {
                Write-Verbose ("ICANN lookup failed for {0}: {1}" -f $domain, $_.Exception.Message)
            }
        }

        if (-not $expDate) {
            try {
                $lookupSourcesTried.Add('whois.com')
                $expDate = Get-DomainExpiryFromWhoisCom -Domain $domain
            }
            catch {
                Write-Verbose ("whois.com lookup failed for {0}: {1}" -f $domain, $_.Exception.Message)
            }
        }

        if (-not $expDate) {
            Write-Warning "Unable to determine the domain expiry date for $domain. Sources tried: $($lookupSourcesTried -join ', ')."
            continue
        }

        $today = (Get-Date).Date

        If ($expDate -lt $today){
            $results += "$domain - $($expDate.ToString('yyyy-MM-dd'))"
        }
    }

    Return $results

}
Catch {
Write-Warning "Error message: $_"
$message = $_.ToString()
$exception = $_.Exception
$strace = $_.ScriptStackTrace
$failingline = $_.InvocationInfo.Line
$positionmsg = $_.InvocationInfo.PositionMessage
$pscommandpath = $_.InvocationInfo.PSCommandPath
$failinglinenumber = $_.InvocationInfo.ScriptLineNumber
$scriptname = $_.InvocationInfo.ScriptName
Write-Verbose "Write to log"
Write-ErrorLog -message $message -exception $exception -scriptname $scriptname
Write-Verbose "Errors written to log"
}

}

Return Inspect-DomainExpiration
