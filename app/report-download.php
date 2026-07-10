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
        'pass' => 'PASS',
        'partial' => 'PARTIAL',
        'fail' => 'FAIL',
        'healthy' => 'HEALTHY',
        default => $status === '' ? 'UNKNOWN' : strtoupper($status),
    };
}

function secureit_report_pdf_status_color(string $status): array {
    $status = strtolower(trim($status));
    return match ($status) {
        'pass', 'healthy' => [0.0, 0.69, 0.31],
        'partial' => [0.95, 0.75, 0.0],
        'fail' => [0.93, 0.0, 0.0],
        default => [0.45, 0.55, 0.63],
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

function secureit_report_pdf_text_width(string $text, float $size): float {
    return strlen($text) * $size * 0.52;
}

function secureit_report_pdf_start_page(array &$pages, string $tenantName, string $generatedAt, int $pageNumber): void {
    $ops = [];
    $ops[] = '0.00 0.39 0.37 rg';
    $ops[] = '0 0 595 16 re f';
    $ops[] = '0.92 0.95 0.95 rg';
    $ops[] = '36 792 523 30 re f';
    $ops[] = '0.85 0.90 0.90 RG';
    $ops[] = '0.8 w';
    $ops[] = '36 785 m 559 785 l S';
    $ops[] = '0 0 0 rg';
    $ops[] = 'BT /F4 18 Tf 1 0 0 1 36 804 Tm (SecureIT Report) Tj ET';
    $tenantHeader = secureit_report_pdf_escape(secureit_report_pdf_sanitize($tenantName));
    $ops[] = 'BT /F3 10 Tf 1 0 0 1 ' . max(36, 559 - (int) ceil(secureit_report_pdf_text_width($tenantName, 10))) . ' 805 Tm (' . $tenantHeader . ') Tj ET';
    $ops[] = 'BT /F3 8 Tf 1 0 0 1 36 770 Tm (Generated ' . secureit_report_pdf_escape(secureit_report_pdf_sanitize($generatedAt)) . ') Tj ET';
    $ops[] = 'BT /F3 8 Tf 1 0 0 1 504 770 Tm (Page ' . $pageNumber . ') Tj ET';
    $ops[] = '0.88 0.91 0.91 RG';
    $ops[] = '0.7 w';
    $ops[] = '36 756 m 559 756 l S';

    $pages[] = [
        'ops' => implode("\n", $ops) . "\n",
        'cursorY' => 740,
        'pageNumber' => $pageNumber,
    ];
}

function secureit_report_pdf_current_index(array &$pages): int {
    return count($pages) - 1;
}

function secureit_report_pdf_emit(array &$pages, string $command): void {
    $index = secureit_report_pdf_current_index($pages);
    $pages[$index]['ops'] .= $command . "\n";
}

function secureit_report_pdf_new_page_if_needed(array &$pages, string $tenantName, string $generatedAt): void {
    if (empty($pages)) {
        secureit_report_pdf_start_page($pages, $tenantName, $generatedAt, 1);
    }
}

function secureit_report_pdf_break_page(array &$pages, string $tenantName, string $generatedAt): void {
    $pageNumber = count($pages) + 1;
    secureit_report_pdf_start_page($pages, $tenantName, $generatedAt, $pageNumber);
}

function secureit_report_pdf_ensure_space(array &$pages, string $tenantName, string $generatedAt, float $needed): void {
    secureit_report_pdf_new_page_if_needed($pages, $tenantName, $generatedAt);
    $index = secureit_report_pdf_current_index($pages);
    if (($pages[$index]['cursorY'] ?? 0) - $needed < 56) {
        secureit_report_pdf_break_page($pages, $tenantName, $generatedAt);
    }
}

function secureit_report_pdf_text(array &$pages, float $x, float $y, float $size, string $text, bool $bold = false, array $color = [0, 0, 0]): void {
    $font = $bold ? '/F4' : '/F3';
    $escaped = secureit_report_pdf_escape(secureit_report_pdf_sanitize($text));
    $cmd = sprintf('%.3F %.3F %.3F rg BT %s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $color[0], $color[1], $color[2], $font, $size, $x, $y, $escaped);
    secureit_report_pdf_emit($pages, $cmd);
}

function secureit_report_pdf_rect(array &$pages, float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 0.7): void {
    $cmd = [];
    if ($stroke !== null) {
        $cmd[] = sprintf('%.3F %.3F %.3F RG %.2f w', $stroke[0], $stroke[1], $stroke[2], $lineWidth);
    }
    if ($fill !== null) {
        $cmd[] = sprintf('%.3F %.3F %.3F rg %.2f %.2f %.2f %.2f re f', $fill[0], $fill[1], $fill[2], $x, $y, $w, $h);
    } else {
        $cmd[] = sprintf('%.2f %.2f %.2f %.2f re S', $x, $y, $w, $h);
    }
    if ($fill !== null && $stroke !== null) {
        $cmd[] = sprintf('%.3F %.3F %.3F RG %.2f w %.2f %.2f %.2f %.2f re S', $stroke[0], $stroke[1], $stroke[2], $lineWidth, $x, $y, $w, $h);
    }
    secureit_report_pdf_emit($pages, implode("\n", $cmd));
}

function secureit_report_pdf_hline(array &$pages, float $x1, float $x2, float $y, array $color = [0, 0, 0], float $lineWidth = 0.7): void {
    secureit_report_pdf_emit(
        $pages,
        sprintf('%.3F %.3F %.3F RG %.2f w %.2f %.2f m %.2f %.2f l S', $color[0], $color[1], $color[2], $lineWidth, $x1, $y, $x2, $y)
    );
}

function secureit_report_pdf_wrapped_text_lines(string $text, float $width, float $size): array {
    $approxChars = max(18, (int) floor($width / max(1, $size * 0.52)));
    return secureit_report_pdf_wrap($text, $approxChars);
}

function secureit_report_pdf_paragraph(array &$pages, float $x, float $topY, float $width, string $text, float $size = 10.5, bool $bold = false, array $color = [0, 0, 0]): float {
    $lines = secureit_report_pdf_wrapped_text_lines($text, $width, $size);
    $lineHeight = $size * 1.35;
    $y = $topY;
    foreach ($lines as $line) {
        secureit_report_pdf_text($pages, $x, $y, $size, $line, $bold, $color);
        $y -= $lineHeight;
    }
    return $topY - $y;
}

function secureit_report_pdf_section_title(array &$pages, string $title): void {
    $index = secureit_report_pdf_current_index($pages);
    $cursorY = $pages[$index]['cursorY'];
    secureit_report_pdf_text($pages, 36, $cursorY, 16, $title, true, [0.0, 0.39, 0.37]);
    secureit_report_pdf_hline($pages, 36, 559, $cursorY - 4, [0.0, 0.39, 0.37], 1.0);
    $pages[$index]['cursorY'] = $cursorY - 20;
}

function secureit_report_pdf_summary_card(array &$pages, float $x, float $y, float $w, float $h, string $label, string $value, array $fill): void {
    secureit_report_pdf_rect($pages, $x, $y, $w, $h, [0.98, 0.99, 0.99], [0.87, 0.91, 0.91], 0.7);
    secureit_report_pdf_rect($pages, $x, $y + $h - 18, $w, 18, $fill, $fill, 0.7);
    secureit_report_pdf_text($pages, $x + 8, $y + $h - 12, 8.5, $label, true, [1, 1, 1]);
    $lines = secureit_report_pdf_wrapped_text_lines($value, $w - 16, 10.5);
    $textY = $y + $h - 34;
    foreach ($lines as $line) {
        secureit_report_pdf_text($pages, $x + 8, $textY, 10.5, $line, false, [0.10, 0.18, 0.17]);
        $textY -= 12.5;
    }
}

function secureit_report_pdf_draw_table_header(array &$pages, float $topY, float $leftX, array $widths): void {
    $height = 22;
    $x = $leftX;
    $headers = ['Test name', 'Status', 'Description'];
    foreach ($headers as $i => $header) {
        secureit_report_pdf_rect($pages, $x, $topY - $height, $widths[$i], $height, [0.0, 0.39, 0.37], [0.0, 0.39, 0.37], 0.7);
        secureit_report_pdf_text($pages, $x + 6, $topY - 13, 9.2, $header, true, [1, 1, 1]);
        $x += $widths[$i];
    }
}

function secureit_report_pdf_draw_control_row(array &$pages, array $control, float $topY, float $leftX, array $widths): float {
    $title = (string) ($control['title'] ?? $control['id'] ?? 'Check');
    $status = secureit_report_pdf_status_label((string) ($control['status'] ?? 'unknown'));
    $statusColor = secureit_report_pdf_status_color((string) ($control['status'] ?? 'unknown'));
    $details = trim((string) ($control['details'] ?? 'SecureIT reviews the matching imported report evidence and uses the result to set this check status.'));

    $titleLines = secureit_report_pdf_wrapped_text_lines($title, $widths[0] - 12, 10);
    $detailsLines = secureit_report_pdf_wrapped_text_lines($details, $widths[2] - 12, 9);
    $rowHeight = max(30, (count($titleLines) * 12), (count($detailsLines) * 10) + 4);
    $bottomY = $topY - $rowHeight;

    $x = $leftX;
    $fill = [1, 1, 1];
    $line = [0.86, 0.89, 0.89];
    for ($i = 0; $i < 3; $i++) {
        secureit_report_pdf_rect($pages, $x, $bottomY, $widths[$i], $rowHeight, $fill, $line, 0.55);
        $x += $widths[$i];
    }

    $textY = $topY - 13;
    foreach ($titleLines as $lineText) {
        secureit_report_pdf_text($pages, $leftX + 6, $textY, 9.8, $lineText, true, [0.10, 0.18, 0.17]);
        $textY -= 11.5;
    }

    $statusW = $widths[1] - 12;
    $badgeX = $leftX + $widths[0] + 6;
    $badgeY = $topY - 19;
    secureit_report_pdf_rect($pages, $badgeX, $badgeY, min(74, $statusW), 14, $statusColor, $statusColor, 0.6);
    secureit_report_pdf_text($pages, $badgeX + 7, $badgeY + 10, 8.2, $status, true, [1, 1, 1]);

    $detailY = $topY - 12;
    foreach ($detailsLines as $lineText) {
        secureit_report_pdf_text($pages, $leftX + $widths[0] + $widths[1] + 6, $detailY, 8.8, $lineText, false, [0.19, 0.25, 0.28]);
        $detailY -= 10;
    }

    return $rowHeight;
}

function secureit_report_pdf_page_stream(array $page): string {
    return $page['ops'];
}

function secureit_report_pdf_render(array $pages): string {
    $objects = [];
    $pageObjectIds = [];
    $nextObjectId = 5;

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

    foreach ($pages as $page) {
        $contentId = $nextObjectId++;
        $pageId = $nextObjectId++;
        $pageObjectIds[] = $pageId;

        $content = secureit_report_pdf_page_stream($page);
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

$pages = [];
secureit_report_pdf_start_page($pages, $tenantName, $generatedAt, 1);

$index = secureit_report_pdf_current_index($pages);
$y = $pages[$index]['cursorY'];

secureit_report_pdf_section_title($pages, 'Executive Summary');
$index = secureit_report_pdf_current_index($pages);
$y = $pages[$index]['cursorY'];
$y -= secureit_report_pdf_paragraph($pages, 36, $y, 523, $analysisText, 10.5, false, [0.12, 0.18, 0.19]) + 8;
secureit_report_pdf_text(
    $pages,
    36,
    $y,
    10,
    'Checks: ' . $counts['total'] . '   Passed: ' . $counts['passed'] . '   Partially met: ' . $counts['partial'] . '   Failed: ' . $counts['failed'],
    true,
    [0.10, 0.18, 0.17]
);
$pages[secureit_report_pdf_current_index($pages)]['cursorY'] = $y - 22;

secureit_report_pdf_section_title($pages, 'Latest Security Posture');
$index = secureit_report_pdf_current_index($pages);
$y = $pages[$index]['cursorY'];
$currentScore = (int) round(($counts['passRate'] ?? 0));
secureit_report_pdf_summary_card($pages, 36, $y - 62, 158, 62, 'Overall score', $currentScore . '%', [0.0, 0.39, 0.37]);
secureit_report_pdf_summary_card($pages, 195, $y - 62, 158, 62, 'Checks', (string) $counts['total'], [0.0, 0.39, 0.37]);
secureit_report_pdf_summary_card($pages, 354, $y - 62, 158, 62, 'Pass rate', $counts['passRate'] . '%', [0.0, 0.39, 0.37]);
$pages[$index]['cursorY'] = $y - 84;

secureit_report_pdf_section_title($pages, 'Area Breakdown');
$areas = is_array($areaData['areas'] ?? null) ? $areaData['areas'] : [];
foreach ($areas as $area) {
    $areaName = (string) ($area['name'] ?? 'Functional area');
    secureit_report_pdf_ensure_space($pages, $tenantName, $generatedAt, 120);
    $index = secureit_report_pdf_current_index($pages);
    $y = $pages[$index]['cursorY'];
    secureit_report_pdf_text($pages, 36, $y, 13, $areaName, true, [0.10, 0.18, 0.17]);
    $pages[$index]['cursorY'] = $y - 16;

    $description = secureit_report_pdf_area_description($areaName);
    $pages[$index]['cursorY'] -= secureit_report_pdf_paragraph($pages, 36, $pages[$index]['cursorY'], 523, $description, 9.6, false, [0.24, 0.28, 0.31]) + 4;

    $controlsTotal = (int) ($area['controlsTotal'] ?? 0);
    $controlsPassing = (int) ($area['controlsPassing'] ?? 0);
    $controlsPartial = (int) ($area['controlsPartial'] ?? 0);
    $controlsFailing = (int) ($area['controlsFailing'] ?? 0);
    $testsTotal = (int) ($area['testsTotal'] ?? 0);
    $testsPassed = (int) ($area['testsPassed'] ?? 0);
    $testsFailed = (int) ($area['testsFailed'] ?? 0);
    $testsSkipped = (int) ($area['testsSkipped'] ?? 0);
    $score = $area['score'];
    $scoreText = $score === null ? 'Score unavailable' : 'Score ' . $score . '%';
    $summaryLine = 'Status: ' . ($area['status'] ?? 'Unknown') . '   ' . $scoreText . '   Checks: ' . $controlsTotal . '   Passed: ' . $controlsPassing . '   Partial: ' . $controlsPartial . '   Failed: ' . $controlsFailing;
    $pages[$index]['cursorY'] -= secureit_report_pdf_paragraph($pages, 36, $pages[$index]['cursorY'], 523, $summaryLine, 9.4, true, [0.10, 0.18, 0.17]) + 2;

    if ($testsTotal > 0) {
        $testsLine = 'Underlying items: ' . $testsTotal . ' total | ' . $testsPassed . ' passed | ' . $testsFailed . ' failed | ' . $testsSkipped . ' skipped';
        $pages[$index]['cursorY'] -= secureit_report_pdf_paragraph($pages, 36, $pages[$index]['cursorY'], 523, $testsLine, 9.2, false, [0.35, 0.35, 0.35]) + 4;
    }

    $tableTop = $pages[$index]['cursorY'];
    $colWidths = [172, 92, 251];
    secureit_report_pdf_draw_table_header($pages, $tableTop, 36, $colWidths);
    $pages[$index]['cursorY'] = $tableTop - 22;
    $controls = is_array($area['controls'] ?? null) ? $area['controls'] : [];
    foreach ($controls as $control) {
        $rowHeight = secureit_report_pdf_draw_control_row($pages, $control, $pages[$index]['cursorY'], 36, $colWidths);
        $pages[$index]['cursorY'] -= $rowHeight;
        if ($pages[$index]['cursorY'] < 70) {
            secureit_report_pdf_break_page($pages, $tenantName, $generatedAt);
            $index = secureit_report_pdf_current_index($pages);
            $newTop = $pages[$index]['cursorY'];
            secureit_report_pdf_text($pages, 36, $newTop, 12, $areaName . ' - continued', true, [0.10, 0.18, 0.17]);
            $pages[$index]['cursorY'] = $newTop - 18;
            secureit_report_pdf_draw_table_header($pages, $pages[$index]['cursorY'], 36, $colWidths);
            $pages[$index]['cursorY'] -= 22;
        }
    }
    $pages[$index]['cursorY'] -= 16;
}

$pdf = secureit_report_pdf_render($pages);
$downloadName = 'secureit-latest-report-' . $tenantKey . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
