$ErrorActionPreference = "Stop"

$errorHandling = "$((Get-Item $PSScriptRoot).Parent.FullName)\Write-ErrorLog.ps1"

. $errorHandling


$path = @($out_path)

Function Get-InternalMailboxForwarding {
    Try {
        $mailboxes = Get-Mailbox -RecipientTypeDetails UserMailbox, SharedMailbox -ResultSize Unlimited

        $knownDomains = (Invoke-GraphRequest -method get -uri "https://$(@($global:graphURI))/beta/organization?$select=verifiedDomains").Value.verifiedDomains.name
        $knownDomainSet = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
        foreach ($knownDomain in $knownDomains) {
            if (-not [string]::IsNullOrWhiteSpace($knownDomain)) {
                [void]$knownDomainSet.Add($knownDomain)
            }
        }

        $rulesEnabled = @()

        $internalRulesEnabled = @()

        if ((Test-path "$path\Exchange") -eq $true) {
            $path = "$path\Exchange"
        }
        Else {
            $path = New-Item -ItemType Directory -Force -Path "$($path)\Exchange"
        }

        foreach ($mailbox in $mailboxes) {
            try {
                $mailboxRules = Get-InboxRule -Mailbox $mailbox.UserPrincipalName -ErrorAction Stop -WarningAction SilentlyContinue |
                    Where-Object { ($null -ne $_.ForwardTo) -or ($null -ne $_.ForwardAsAttachmentTo) -or ($null -ne $_.RedirectTo) } |
                    Select-Object MailboxOwnerId, RuleIdentity, Name, ForwardTo, RedirectTo, ForwardAsAttachmentTo

                if ($mailboxRules) {
                    $rulesEnabled += $mailboxRules
                }
            }
            catch {
                Write-Warning "Skipping inbox-rule inspection for mailbox $($mailbox.UserPrincipalName): $($_.Exception.Message)"
            }
        }
        
        if ($rulesEnabled.Count -gt 0) {
            foreach ($rule in $rulesEnabled) {
                $isInternalRule = $false

                foreach ($target in @($rule.ForwardTo) + @($rule.ForwardAsAttachmentTo) + @($rule.RedirectTo)) {
                    if ([string]::IsNullOrWhiteSpace($target)) {
                        continue
                    }

                    if ($target -match 'EX:/o=') {
                        $isInternalRule = $true
                        break
                    }

                    $targetDomain = if ($target -match '@') { ((($target -split '@')[1] -split '"')[0]).Trim() } else { $null }
                    if (-not [string]::IsNullOrWhiteSpace($targetDomain) -and $knownDomainSet.Contains($targetDomain)) {
                        $isInternalRule = $true
                        break
                    }
                }

                if ($isInternalRule) {
                    $internalRulesEnabled += $rule
                }
            }
        }

        if ($internalRulesEnabled.count -gt 0) {
            $internalRulesEnabled | Export-Csv "$($path)\ExchangeMailboxeswithInternalForwardingRules.csv" -Delimiter ';' -NoTypeInformation -Append
            Return $internalRulesenabled.MailboxOwnerID | Select-Object -Unique
        }
        Else {
        }
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
        Write-ErrorLog -message $message -exception $exception -scriptname $scriptname -failinglinenumber $failinglinenumber -failingline $failingline -pscommandpath $pscommandpath -positionmsg $pscommandpath -stacktrace $strace
        Write-Verbose "Errors written to log"
    }
}

Get-InternalMailboxForwarding
