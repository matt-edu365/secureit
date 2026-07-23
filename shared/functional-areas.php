<?php
function secureit_functional_area_catalog(): array {
    return [
        [
            'name' => 'Identity & Access Management',
            'description' => 'User identities, authentication, access policies, admin roles, guest access, security groups, and sign-in controls.',
        ],
        [
            'name' => 'Email & Calendaring',
            'description' => 'Mailboxes, shared mailboxes, distribution lists, calendars, mail flow, anti-spam, anti-malware, retention, and email archiving.',
        ],
        [
            'name' => 'Collaboration & Communication',
            'description' => 'Chat, meetings, calling, webinars, channels, team collaboration, internal communities, and real-time communication.',
        ],
        [
            'name' => 'Files, Intranet & Content Management',
            'description' => 'Document libraries, intranet sites, file sharing, version control, metadata, document automation, records, and structured business lists.',
        ],
        [
            'name' => 'Endpoint & Device Management',
            'description' => 'Device enrolment, compliance policies, app deployment, patching, mobile device management, security baselines, and BYOD controls.',
        ],
        [
            'name' => 'Security Operations & Threat Protection',
            'description' => 'Threat protection across email, endpoints, identities, cloud apps, phishing, malware, incidents, alerts, investigation, and response.',
        ],
        [
            'name' => 'Compliance, Governance & Data Protection',
            'description' => 'Sensitivity labels, data loss prevention, retention policies, legal hold, audit logs, compliance reporting, data governance, and risk management.',
        ],
    ];
}

function secureit_functional_area_status_from_score(?int $score): array {
    if ($score === null) {
        return [
            'status' => 'No data',
            'tone' => 'neutral',
            'scoreLabel' => 'Score unavailable',
        ];
    }

    if ($score >= 85) {
        return [
            'status' => 'Healthy',
            'tone' => 'good',
            'scoreLabel' => 'Score: ' . $score . '%',
        ];
    }

    if ($score >= 70) {
        return [
            'status' => 'Watch',
            'tone' => 'warn',
            'scoreLabel' => 'Score: ' . $score . '%',
        ];
    }

    return [
        'status' => 'Needs attention',
        'tone' => 'bad',
        'scoreLabel' => 'Score: ' . $score . '%',
    ];
}

function secureit_is_pass_result(string $result): bool {
    return in_array(strtolower($result), ['pass', 'passed', 'success', 'healthy'], true);
}

function secureit_is_fail_result(string $result): bool {
    return in_array(strtolower(trim($result)), ['fail', 'failed', 'critical'], true);
}

function secureit_is_neutral_result(string $result): bool {
    return in_array(strtolower(trim($result)), [
        'skipped',
        'skip',
        'notapplicable',
        'not applicable',
        'not_applicable',
        'notrun',
        'not run',
        'not_run',
        'neutral',
        'pending',
        'error',
        'errored',
        'unknown',
    ], true);
}
