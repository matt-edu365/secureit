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

    It 'parses the Maester 2 report format assigned with const and formatted JSON' {
        $html = @'
<script>
const testResults={
  "Result": "Failed",
  "FailedCount": 71,
  "PassedCount": 71,
  "ErrorCount": 0,
  "InvestigateCount": 0,
  "SkippedCount": 24,
  "NotRunCount": 15,
  "TotalCount": 181,
  "Tests": [
    {
      "Title": "Nested report object",
      "Details": "Text containing }; and an escaped quote: \"example\"",
      "Metadata": { "Source": "Maester" }
    }
  ],
  "Blocks": [{ "Name": "SecureIT", "Counts": { "Passed": 71, "Failed": 71 } }]
};
</script>
'@

        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent $html

        $summary.PassedCount | Should -Be 71
        $summary.FailedCount | Should -Be 71
        $summary.TotalCount | Should -Be 181
        $summary.Tests[0].Metadata.Source | Should -Be 'Maester'
        $summary.Blocks[0].Counts.Failed | Should -Be 71
    }

    It 'accepts let assignments and skips unrelated JavaScript objects' {
        $html = @'
<script>
const pageConfig = {"Result":"not a Maester summary","Tests":[]};
let reportPayload = {"Result":"Passed","FailedCount":0,"PassedCount":2,"ErrorCount":0,"InvestigateCount":0,"SkippedCount":0,"NotRunCount":0,"TotalCount":2,"Tests":[{"Result":"Passed"}]};
</script>
'@

        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent $html

        $summary.Result | Should -Be 'Passed'
        $summary.TotalCount | Should -Be 2
    }

    It 'continues past malformed candidate objects' {
        $html = @'
<script>
const incomplete = {"Result":"Failed";
const testResults = {"Result":"Passed","FailedCount":0,"PassedCount":1,"ErrorCount":0,"InvestigateCount":0,"SkippedCount":0,"NotRunCount":0,"TotalCount":1,"Tests":[]};
</script>
'@

        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent $html

        $summary.Result | Should -Be 'Passed'
        $summary.TotalCount | Should -Be 1
    }

    It 'returns no summary when the report has no embedded result object' {
        $summary = Get-SecureItEmbeddedMaesterSummary -HtmlContent '<html>no report data</html>'

        $summary | Should -BeNullOrEmpty
    }
}
