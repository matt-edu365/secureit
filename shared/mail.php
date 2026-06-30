<?php

function secureit_mail_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function secureit_mail_sender_mailbox(): string {
    return 'secureit@ict365.ky';
}

function secureit_mail_normalize_overview_stats(array $stats): array {
    $checks = max(0, (int) ($stats['checks'] ?? 0));
    $passed = max(0, (int) ($stats['passed'] ?? 0));
    $partial = max(0, (int) ($stats['partial'] ?? 0));
    $failed = max(0, (int) ($stats['failed'] ?? 0));
    $passRate = isset($stats['passRate'])
        ? max(0, min(100, (int) $stats['passRate']))
        : ($checks > 0 ? (int) round(($passed / $checks) * 100) : 0);

    $statusTone = strtolower(trim((string) ($stats['statusTone'] ?? '')));
    if ($statusTone === '') {
        if ($failed === 0) {
            $statusTone = 'good';
        } elseif ($failed <= 3) {
            $statusTone = 'warn';
        } else {
            $statusTone = 'bad';
        }
    }

    $statusLabel = trim((string) ($stats['statusLabel'] ?? ''));
    if ($statusLabel === '') {
        $statusLabel = match ($statusTone) {
            'warn' => 'Watch',
            'bad' => 'Needs attention',
            default => 'Healthy',
        };
    }

    return [
        'title' => trim((string) ($stats['title'] ?? 'Tenant overview snapshot')),
        'subtitle' => trim((string) ($stats['subtitle'] ?? '')),
        'summary' => trim((string) ($stats['summary'] ?? '')),
        'statusLabel' => $statusLabel,
        'statusTone' => $statusTone,
        'checks' => $checks,
        'passed' => $passed,
        'partial' => $partial,
        'failed' => $failed,
        'passRate' => $passRate,
    ];
}

function secureit_mail_status_colors(string $statusTone): array {
    return match ($statusTone) {
        'warn' => [
            'text' => '#a1600a',
            'background' => '#fff7ed',
        ],
        'bad' => [
            'text' => '#b42318',
            'background' => '#fff1f2',
        ],
        default => [
            'text' => '#0f766e',
            'background' => '#edf9f5',
        ],
    };
}

function secureit_mail_build_overview_html(array $stats, array $meta = []): string {
    $stats = secureit_mail_normalize_overview_stats($stats);
    $title = secureit_mail_escape((string) ($meta['headline'] ?? $stats['title']));
    $subtitle = secureit_mail_escape((string) ($meta['intro'] ?? $stats['subtitle']));
    $summary = secureit_mail_escape((string) ($meta['summary'] ?? $stats['summary']));
    $brandLabel = secureit_mail_escape((string) ($meta['brandLabel'] ?? 'SecureIT'));
    $eyebrow = secureit_mail_escape((string) ($meta['eyebrow'] ?? 'Tenant overview'));
    $summaryLabel = secureit_mail_escape((string) ($meta['summaryLabel'] ?? 'Summary'));
    $modeLabel = secureit_mail_escape((string) ($meta['modeLabel'] ?? 'HTML'));
    $generatedAt = secureit_mail_escape((string) ($meta['generatedAt'] ?? ''));
    $senderMailbox = secureit_mail_escape((string) ($meta['senderMailbox'] ?? secureit_mail_sender_mailbox()));
    $recipientMailbox = secureit_mail_escape((string) ($meta['recipientMailbox'] ?? ''));
    $footerNote = secureit_mail_escape((string) ($meta['footerNote'] ?? 'The numbers above are intended to validate HTML rendering, formatting, and Graph mail delivery.'));
    $statusColors = secureit_mail_status_colors((string) $stats['statusTone']);

    $metricCards = [
        [
            'label' => 'Checks',
            'value' => $stats['checks'],
            'background' => '#eaf6f4',
            'border' => '#cbe7df',
            'accent' => '#0f766e',
            'note' => 'Across the latest assessment',
        ],
        [
            'label' => 'Passed',
            'value' => $stats['passed'],
            'background' => '#edf9f5',
            'border' => '#c8eadb',
            'accent' => '#13795b',
            'note' => 'Controls already meeting the baseline',
        ],
        [
            'label' => 'Partially met',
            'value' => $stats['partial'],
            'background' => '#fff7e8',
            'border' => '#f3d7a7',
            'accent' => '#a1600a',
            'note' => 'Controls that need a small amount of follow-up',
        ],
        [
            'label' => 'Failed',
            'value' => $stats['failed'],
            'background' => '#fff1ef',
            'border' => '#f1c0b7',
            'accent' => '#b42318',
            'note' => 'Controls still needing attention',
        ],
    ];

    $cardRows = '';
    foreach (array_chunk($metricCards, 2) as $rowCards) {
        $cardRows .= '<tr>';
        foreach ($rowCards as $card) {
            $cardRows .= '<td width="50%" valign="top" style="padding:0 6px 12px 0;">';
            $cardRows .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background:' . secureit_mail_escape((string) $card['background']) . '; border:1px solid ' . secureit_mail_escape((string) $card['border']) . '; border-radius:18px;">';
            $cardRows .= '<tr><td style="padding:16px 16px 14px;">';
            $cardRows .= '<div style="font-size:12px; line-height:1.2; letter-spacing:0.12em; text-transform:uppercase; color:' . secureit_mail_escape((string) $card['accent']) . '; font-weight:700;">' . secureit_mail_escape((string) $card['label']) . '</div>';
            $cardRows .= '<div style="font-size:32px; line-height:1.05; margin-top:8px; font-weight:800; color:#163a37;">' . secureit_mail_escape((string) $card['value']) . '</div>';
            $cardRows .= '<div style="font-size:12px; line-height:1.45; margin-top:8px; color:#4f645f;">' . secureit_mail_escape((string) $card['note']) . '</div>';
            $cardRows .= '</td></tr></table>';
            $cardRows .= '</td>';
        }
        if (count($rowCards) === 1) {
            $cardRows .= '<td width="50%" valign="top" style="padding:0 0 12px 6px;"></td>';
        }
        $cardRows .= '</tr>';
    }

    return '<!doctype html>'
        . '<html><body style="margin:0; padding:0; background:#edf5f2; font-family:Arial,Helvetica,sans-serif; color:#163a37;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; background:linear-gradient(180deg,#0f2f2c 0%, #133f3a 100%);">'
        . '<tr><td align="center" style="padding:28px 16px;">'
        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="border-collapse:separate; max-width:640px; width:100%;">'
        . '<tr><td style="padding:0;">'
        . '<div style="background:#0f2f2c; color:#f3fbf9; border-radius:26px 26px 0 0; padding:28px 30px 24px; box-shadow:0 14px 30px rgba(8,36,33,0.28);">'
        . '<div style="font-size:12px; line-height:1.3; letter-spacing:0.18em; text-transform:uppercase; opacity:0.8;">' . $brandLabel . '</div>'
        . '<div style="margin-top:8px; font-size:26px; line-height:1.2; font-weight:800;">' . $title . '</div>'
        . '<div style="margin-top:10px; font-size:14px; line-height:1.5; max-width:520px; color:#d5ede8;">' . $subtitle . '</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:18px;"><tr>'
        . '<td style="padding:0 10px 0 0;"><div style="display:inline-block; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,0.12); color:#f3fbf9; font-size:12px; font-weight:700;">Mode: ' . $modeLabel . '</div></td>'
        . '<td style="padding:0 10px 0 0;"><div style="display:inline-block; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,0.12); color:#f3fbf9; font-size:12px; font-weight:700;">Recipient: ' . $recipientMailbox . '</div></td>'
        . '<td><div style="display:inline-block; padding:8px 12px; border-radius:999px; background:rgba(255,255,255,0.12); color:#f3fbf9; font-size:12px; font-weight:700;">Generated: ' . $generatedAt . '</div></td>'
        . '</tr></table>'
        . '</div>'
        . '</td></tr>'
        . '<tr><td style="background:#ffffff; border-radius:0 0 26px 26px; padding:28px 30px 30px; box-shadow:0 18px 40px rgba(18,50,46,0.12);">'
        . '<div style="font-size:12px; line-height:1.3; letter-spacing:0.14em; text-transform:uppercase; color:#53706a; font-weight:700;">' . $eyebrow . '</div>'
        . '<div style="margin-top:8px; font-size:22px; line-height:1.25; font-weight:800; color:#102d2a;">' . $title . '</div>'
        . '<div style="margin-top:8px; font-size:15px; line-height:1.6; color:#4f645f;">' . $subtitle . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; border-collapse:separate;">'
        . $cardRows
        . '</table>'
        . '<div style="margin-top:6px; padding:18px 18px 16px; border-radius:18px; background:#f3fbf9; border:1px solid #cae7de;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>'
        . '<td valign="middle" style="padding:0 12px 0 0;"><div style="font-size:13px; line-height:1.3; font-weight:700; color:#53706a; text-transform:uppercase; letter-spacing:0.1em;">' . $summaryLabel . '</div></td>'
        . '<td valign="middle" align="right"><div style="display:inline-block; font-size:14px; line-height:1.3; font-weight:800; color:' . secureit_mail_escape($statusColors['text']) . '; background:' . secureit_mail_escape($statusColors['background']) . '; border:1px solid rgba(0,0,0,0.05); border-radius:999px; padding:6px 12px;">' . secureit_mail_escape((string) $stats['statusLabel']) . '</div></td>'
        . '</tr></table>'
        . '<div style="margin-top:10px; height:12px; background:#d9ebe6; border-radius:999px; overflow:hidden;"><div style="width:' . (int) $stats['passRate'] . '%; height:12px; background:linear-gradient(90deg,#0f766e 0%, #2f8f84 100%); border-radius:999px;"></div></div>'
        . '<div style="margin-top:8px; font-size:13px; line-height:1.5; color:#4f645f;">' . (int) $stats['passRate'] . '% of the checks passed in this sample overview. ' . secureit_mail_escape(sprintf('%d passed, %d partially met, %d failed.', $stats['passed'], $stats['partial'], $stats['failed'])) . '</div>'
        . '</div>'
        . '<div style="margin-top:16px; padding:18px; border-radius:18px; background:#102d2a; color:#edf7f4;">'
        . '<div style="font-size:12px; line-height:1.3; letter-spacing:0.12em; text-transform:uppercase; color:#9ed8cf; font-weight:700;">Summary</div>'
        . '<div style="margin-top:8px; font-size:16px; line-height:1.6; font-weight:600;">' . $summary . '</div>'
        . '<div style="margin-top:10px; font-size:12px; line-height:1.5; color:#c5e4de;">Sender mailbox: ' . $senderMailbox . ' | Recipient mailbox: ' . $recipientMailbox . ' | Generated at: ' . $generatedAt . '</div>'
        . '</div>'
        . '<div style="margin-top:16px; font-size:12px; line-height:1.5; color:#6a817b;">' . $footerNote . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}
