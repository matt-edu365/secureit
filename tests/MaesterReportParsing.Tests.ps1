BeforeAll {
    . (Join-Path (Join-Path $PSScriptRoot '..') 'scripts/MaesterReportParsing.ps1')
}

Describe 'Get-SecureItEmbeddedMaesterSummary' {
    It 'parses a summary assigned to the legacy ws variable name' {
        $html = @'
<script>var ws={"Result":"Failed","FailedCount":2,"PassedCount":3,"ErrorCount":1,"InvestigateCount":0,"SkippedCount":4,"NotRunCount":5,"TotalCount":15,"Tests":[]};</script>
'@

        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent $html

        $summary.PassedCount | Should -Be 3
        $summary.FailedCount | Should -Be 2
        $summary.TotalCount | Should -Be 15
    }

    It 'parses a summary assigned to a different minified variable name' {
        $html = @'
<script>var Ns={"Result":"Failed","FailedCount":7,"PassedCount":11,"ErrorCount":2,"InvestigateCount":1,"SkippedCount":6,"NotRunCount":8,"TotalCount":35,"Tests":[]};</script>
'@

        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent $html

        $summary.PassedCount | Should -Be 11
        $summary.FailedCount | Should -Be 7
        $summary.TotalCount | Should -Be 35
    }

    It 'returns no summary when the report has no embedded result object' {
        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent '<html>no report data</html>'

        $summary | Should -BeNullOrEmpty
    }
}
