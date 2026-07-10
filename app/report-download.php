<?php
require __DIR__ . '/lib.php';

$tenantKey = trim((string) ($_GET['tenant'] ?? ''));
$tenant = secureit_find_tenant($tenantKey);

if (!$tenant || !secureit_valid_tenant_key($tenantKey)) {
    http_response_code(404);
    echo 'Tenant not found';
    exit;
}

secureit_require_tenant_access($tenantKey);

$summary = secureit_tenant_summary($tenantKey);
$embeddedSummary = secureit_tenant_embedded_summary($tenantKey);

if (!$summary) {
    http_response_code(404);
    echo 'No report summary is available yet for this tenant.';
    exit;
}

$areaData = secureit_resolve_canonical_area_scores($tenantKey);
$counts = secureit_check_summary_counts($areaData);
$analysisText = secureit_tenant_analysis_text($summary, $areaData);
$generatedAt = secureit_format_datetime($summary['generatedAt'] ?? null);
$tenantName = trim((string) ($tenant['name'] ?? $tenantKey));

function secureit_report_pdf_sanitize(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x80-\xFF]/', '?', $text) ?? $text;
}

function secureit_report_pdf_escape(string $text): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function secureit_report_pdf_wrap(string $text, int $maxChars): array {
    $text = trim(preg_replace('/\s+/', ' ', secureit_report_pdf_sanitize($text)) ?? '');
    if ($text === '') {
        return [''];
    }

    $words = preg_split('/\s+/', $text) ?: [];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        while (strlen($word) > $maxChars) {
            $chunk = substr($word, 0, $maxChars);
            $word = substr($word, $maxChars);
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            $lines[] = $chunk;
        }

        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (strlen($candidate) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines ?: [''];
}

function secureit_report_pdf_status_label(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'pass' => 'Pass',
        'partial' => 'Partially met',
        'fail' => 'Fail',
        'healthy' => 'Healthy',
        default => $status === '' ? 'Unknown' : ucfirst($status),
    };
}

function secureit_report_pdf_area_description(string $areaName): string {
    foreach (secureit_functional_area_catalog() as $area) {
        if (($area['name'] ?? '') === $areaName) {
            return (string) ($area['description'] ?? '');
        }
    }

    return 'SecureIT reviews the Microsoft 365 services, policies, checks, and related settings that map to this area.';
}

function secureit_report_pdf_build_pages(
    string $tenantName,
    string $tenantKey,
    string $generatedAt,
    string $analysisText,
    array $summary,
    array $areaData,
    array $counts
): array {
    $pages = [[]];
    $cursorY = 802;
    $topY = 802;
    $bottomY = 48;
    $usableWidth = 515;

    $ensurePage = function () use (&$pages, &$cursorY, $topY) : void {
        if (!isset($pages[count($pages) - 1])) {
            $pages[] = [];
        }
        if ($cursorY <= 0) {
            $pages[] = [];
            $cursorY = $topY;
        }
    };

    $addLine = function (string $text, int $size = 11, bool $bold = false, int $indent = 0, int $before = 0, int $after = 0) use (&$pages, &$cursorY, $topY, $bottomY, $usableWidth, $ensurePage): void {
        if ($before > 0) {
            $cursorY -= $before;
        }

        $maxChars = max(24, (int) floor(($usableWidth - $indent) / max(1, $size * 0.56)));
        $lines = secureit_report_pdf_wrap($text, $maxChars);

        foreach ($lines as $lineText) {
            if ($cursorY < $bottomY) {
                $pages[] = [];
                $cursorY = $topY;
            }
            $ensurePage();
            $pages[count($pages) - 1][] = [
                'text' => $lineText,
                'size' => $size,
                'bold' => $bold,
                'indent' => $indent,
                'y' => $cursorY,
            ];
            $cursorY -= max(14, $size + 4);
        }

        if ($after > 0) {
            $cursorY -= $after;
        }
    };

    $addLine('SecureIT Report', 20, true, 0, 0, 4);
    $addLine($tenantName . ' (' . $tenantKey . ')', 12, false, 0, 0, 3);
    $addLine('Generated ' . $generatedAt, 10, false, 0, 0, 10);

    $addLine('Executive Summary', 15, true, 0, 0, 6);
    $addLine($analysisText, 11, false, 0, 0, 6);
    $addLine(
        'Checks: ' . $counts['total'] . ' | Passed: ' . $counts['passed'] . ' | Partially met: ' . $counts['partial'] . ' | Failed: ' . $counts['failed'],
        11,
        true,
        0,
        0,
        8
    );

    $addLine('Latest Security Posture', 15, true, 0, 0, 6);
    foreach (($areaData['areas'] ?? []) as $area) {
        $areaName = (string) ($area['name'] ?? 'Functional area');
        $score = $area['score'];
        $status = secureit_functional_area_status_from_score($score);
        $summaryLine = $areaName . ': ' . ($score === null ? 'Score unavailable' : ('Score ' . $score . '%')) . ' | ' . $status['status'];
        $addLine($summaryLine, 11, false, 6, 0, 2);
    }
    $addLine('', 11, false, 0, 0, 4);

    $addLine('Area Breakdown', 15, true, 0, 0, 6);
    foreach (($areaData['areas'] ?? []) as $area) {
        $areaName = (string) ($area['name'] ?? 'Functional area');
        $score = $area['score'];
        $status = secureit_functional_area_status_from_score($score);
        $controlsTotal = (int) ($area['controlsTotal'] ?? 0);
        $controlsPassing = (int) ($area['controlsPassing'] ?? 0);
        $controlsPartial = (int) ($area['controlsPartial'] ?? 0);
        $controlsFailing = (int) ($area['controlsFailing'] ?? 0);
        $testsTotal = (int) ($area['testsTotal'] ?? 0);
        $testsPassed = (int) ($area['testsPassed'] ?? 0);
        $testsFailed = (int) ($area['testsFailed'] ?? 0);
        $testsSkipped = (int) ($area['testsSkipped'] ?? 0);

        $addLine($areaName, 13, true, 0, 8, 3);
        $addLine(secureit_report_pdf_area_description($areaName), 10, false, 8, 0, 3);
        $addLine(
            'Status: ' . $status['status'] . ' | Score: ' . ($score === null ? 'n/a' : $score . '%') . ' | Checks: ' . $controlsTotal,
            10,
            false,
            8,
            0,
            2
        );
        $addLine(
            'Passed: ' . $controlsPassing . ' | Partially met: ' . $controlsPartial . ' | Failed: ' . $controlsFailing,
            10,
            false,
            8,
            0,
            2
        );
        if ($testsTotal > 0) {
            $addLine(
                'Underlying items: ' . $testsTotal . ' total | ' . $testsPassed . ' passed | ' . $testsFailed . ' failed | ' . $testsSkipped . ' skipped',
                10,
                false,
                8,
                0,
                4
            );
        } else {
            $addLine('', 10, false, 0, 0, 2);
        }

        foreach (($area['controls'] ?? []) as $control) {
            $controlTitle = (string) ($control['title'] ?? $control['id'] ?? 'Check');
            $controlStatus = secureit_report_pdf_status_label((string) ($control['status'] ?? 'unknown'));
            $controlDetails = trim((string) ($control['details'] ?? 'SecureIT reviews the matching imported report evidence and uses the result to set this check status.'));

            $addLine('Check: ' . $controlTitle, 10, true, 14, 0, 1);
            $addLine('Status: ' . $controlStatus, 10, false, 14, 0, 1);
            $addLine('Details: ' . $controlDetails, 10, false, 14, 0, 3);
        }
    }

    return $pages;
}

function secureit_report_pdf_render(array $pages): string {
    $objects = [];
    $pageObjectIds = [];
    $nextObjectId = 5;

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    foreach ($pages as $pageLines) {
        $contentId = $nextObjectId++;
        $pageId = $nextObjectId++;
        $pageObjectIds[] = $pageId;

        $content = '';
        foreach ($pageLines as $line) {
            $font = !empty($line['bold']) ? '/F4' : '/F3';
            $size = (int) ($line['size'] ?? 11);
            $x = 40 + (int) ($line['indent'] ?? 0);
            $y = (float) ($line['y'] ?? 0);
            $content .= sprintf(
                "BT %s %d Tf %.2f %.2f Td (%s) Tj ET\n",
                $font,
                $size,
                $x,
                $y,
                secureit_report_pdf_escape(secureit_report_pdf_sanitize((string) ($line['text'] ?? '')))
            );
        }

        $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F3 3 0 R /F4 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    $maxId = max(array_keys($objects));
    for ($id = 1; $id <= $maxId; $id++) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $objects[$id] . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($id = 1; $id <= $maxId; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer << /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

    return $pdf;
}

$pages = secureit_report_pdf_build_pages($tenantName, $tenantKey, $generatedAt, $analysisText, $summary, $areaData, $counts);
$pdf = secureit_report_pdf_render($pages);
$downloadName = 'secureit-latest-report-' . $tenantKey . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
