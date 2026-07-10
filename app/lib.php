<?php
require_once __DIR__ . '/../shared/functional-areas.php';
require_once __DIR__ . '/../shared/mail.php';
function secureit_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function secureit_current_request_base_url(): string {
    $forwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme = 'http';
    if ($forwardedProto !== '') {
        $scheme = strtolower(trim(explode(',', $forwardedProto)[0]));
    } elseif (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }

    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    $host = $forwardedHost !== '' ? trim(explode(',', $forwardedHost)[0]) : trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        $baseUrl = trim((string) (secureit_config()['base_url'] ?? ''));
        return rtrim($baseUrl, '/');
    }

    return $scheme . '://' . $host;
}

function secureit_auth_request_base_url(): string {
    $baseUrl = secureit_current_request_base_url();
    $parts = parse_url($baseUrl);
    if (!is_array($parts)) {
        return rtrim($baseUrl, '/');
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '127.0.0.1') {
        $parts['host'] = 'localhost';
    }

    $scheme = (string) ($parts['scheme'] ?? 'http');
    $authority = (string) ($parts['host'] ?? '');
    if (isset($parts['port'])) {
        $authority .= ':' . $parts['port'];
    }

    return $scheme . '://' . $authority;
}

function secureit_request_header_value(array $names): string {
    foreach ($names as $name) {
        $value = trim((string) ($_SERVER[$name] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function secureit_request_bearer_token(): string {
    $authorization = secureit_request_header_value([
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
    ]);
    if ($authorization === '') {
        return '';
    }

    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    return '';
}

function secureit_entra_config(): array {
    $config = secureit_config();
    $allowedTenantIds = secureit_parse_comma_list((string) ($config['entra_allowed_tenant_ids'] ?? ''));

    return [
        'authority' => trim((string) ($config['entra_authority'] ?? 'organizations')),
        'tenantId' => trim((string) ($config['entra_tenant_id'] ?? '')),
        'clientId' => trim((string) ($config['entra_client_id'] ?? '')),
        'clientSecret' => trim((string) ($config['entra_client_secret'] ?? '')),
        'redirectUri' => trim((string) ($config['entra_redirect_uri'] ?? '')),
        'postLogoutRedirectUri' => trim((string) ($config['entra_post_logout_redirect_uri'] ?? '')),
        'allowedTenantIds' => array_values(array_unique($allowedTenantIds)),
        'adminEmailDomains' => secureit_parse_comma_list((string) ($config['entra_admin_email_domains'] ?? 'ict365.ky')),
        'adminAppRole' => trim((string) ($config['entra_admin_app_role'] ?? 'SecureIT.Admin')),
    ];
}

function secureit_workflow_sync_token(): string {
    $config = secureit_config();
    return trim((string) ($config['workflow_sync_token'] ?? ''));
}

function secureit_workflow_sync_token_fingerprint(): string {
    $token = secureit_workflow_sync_token();
    if ($token === '') {
        return '';
    }

    return strtoupper(substr(hash('sha256', $token), 0, 12));
}

function secureit_workflow_sync_authorized(): bool {
    $configuredToken = secureit_workflow_sync_token();
    if ($configuredToken === '') {
        return false;
    }

    $requestToken = secureit_request_header_value([
        'HTTP_X_SECUREIT_WORKFLOW_TOKEN',
        'X-SecureIT-Workflow-Token',
    ]);
    if ($requestToken === '') {
        $requestToken = secureit_request_bearer_token();
    }
    if ($requestToken === '') {
        return false;
    }

    return hash_equals($configuredToken, $requestToken);
}

function secureit_entra_is_enabled(): bool {
    $config = secureit_entra_config();
    return $config['clientId'] !== '' && $config['clientSecret'] !== '';
}

function secureit_entra_oidc_base(): string {
    $config = secureit_entra_config();
    return 'https://login.microsoftonline.com/' . rawurlencode($config['authority']) . '/v2.0';
}

function secureit_entra_discovery_url(): string {
    return secureit_entra_oidc_base() . '/.well-known/openid-configuration';
}

function secureit_entra_jwks_url(): string {
    $response = secureit_http_get_json(secureit_entra_discovery_url());
    $jwksUrl = trim((string) ($response['jwks_uri'] ?? ''));
    return $jwksUrl !== '' ? $jwksUrl : secureit_entra_oidc_base() . '/discovery/v2.0/keys';
}

function secureit_entra_oauth_base(): string {
    $config = secureit_entra_config();
    return 'https://login.microsoftonline.com/' . rawurlencode($config['authority']) . '/oauth2/v2.0';
}

function secureit_entra_logout_url(): string {
    $query = http_build_query([
        'post_logout_redirect_uri' => secureit_entra_post_logout_redirect_uri(),
    ], '', '&', PHP_QUERY_RFC3986);

    return secureit_entra_oauth_base() . '/logout?' . $query;
}

function secureit_entra_redirect_uri(): string {
    $config = secureit_entra_config();
    if ($config['redirectUri'] !== '') {
        return $config['redirectUri'];
    }

    return rtrim(secureit_auth_request_base_url(), '/') . '/auth/callback';
}

function secureit_entra_post_logout_redirect_uri(): string {
    $config = secureit_entra_config();
    if ($config['postLogoutRedirectUri'] !== '') {
        return $config['postLogoutRedirectUri'];
    }

    return rtrim(secureit_auth_request_base_url(), '/') . '/login.php';
}

function secureit_is_local_identity_environment(): bool {
    $host = strtolower((string) (parse_url(secureit_current_request_base_url(), PHP_URL_HOST) ?: ''));
    return in_array($host, ['localhost', '127.0.0.1'], true);
}

function secureit_http_get_json(string $url): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return [];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        return [];
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

function secureit_http_describe_json_error(int $status, string $body, string $fallback): string {
    $body = trim($body);
    if ($body === '') {
        return $fallback;
    }

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $error = $decoded['error'] ?? [];
        if (is_array($error)) {
            $code = trim((string) ($error['code'] ?? ''));
            $message = trim((string) ($error['message'] ?? ''));
            if ($code !== '' && $message !== '') {
                return 'HTTP ' . $status . ' Graph error ' . $code . ': ' . $message;
            }
            if ($message !== '') {
                return 'HTTP ' . $status . ' Graph error: ' . $message;
            }
            if ($code !== '') {
                return 'HTTP ' . $status . ' Graph error code: ' . $code;
            }
        }
    }

    $snippet = preg_replace('/\s+/', ' ', $body);
    $snippet = trim(substr((string) $snippet, 0, 240));
    if ($snippet !== '') {
        return 'HTTP ' . $status . ' Graph response: ' . $snippet;
    }

    return $fallback;
}

function secureit_http_get_json_with_bearer(string $url, string $bearerToken): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'body' => '',
            'error' => 'Unable to initialise the Graph request.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'body' => '',
            'error' => $curlError !== '' ? $curlError : 'The Graph request failed before a response body was returned.',
        ];
    }

    $data = json_decode($body, true);
    if ($status >= 400) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => is_array($data) ? $data : [],
            'body' => $body,
            'error' => secureit_http_describe_json_error(
                $status,
                $body,
                'Microsoft Graph rejected the request with HTTP ' . $status . '.'
            ),
        ];
    }

    if (!is_array($data)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'body' => $body,
            'error' => 'Microsoft Graph returned a non-JSON response.',
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'data' => $data,
        'body' => $body,
        'error' => '',
    ];
}

function secureit_http_post_form(string $url, array $fields): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return [];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        return [];
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

function secureit_http_describe_api_error(int $status, string $body, string $fallback): string {
    $body = trim($body);
    if ($body === '') {
        return $fallback;
    }

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $error = $decoded['error'] ?? null;
        if (is_array($error)) {
            $code = trim((string) ($error['code'] ?? ''));
            $message = trim((string) ($error['message'] ?? ''));
            if ($code !== '' && $message !== '') {
                return 'HTTP ' . $status . ' API error ' . $code . ': ' . $message;
            }
            if ($message !== '') {
                return 'HTTP ' . $status . ' API error: ' . $message;
            }
            if ($code !== '') {
                return 'HTTP ' . $status . ' API error code: ' . $code;
            }
        }

        $message = trim((string) ($decoded['message'] ?? ''));
        if ($message !== '') {
            return 'HTTP ' . $status . ' API error: ' . $message;
        }

        $errors = $decoded['errors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            $firstError = $errors[0] ?? null;
            if (is_array($firstError)) {
                $message = trim((string) ($firstError['message'] ?? $firstError['detail'] ?? ''));
                if ($message !== '') {
                    return 'HTTP ' . $status . ' API error: ' . $message;
                }
            } elseif (is_scalar($firstError)) {
                $message = trim((string) $firstError);
                if ($message !== '') {
                    return 'HTTP ' . $status . ' API error: ' . $message;
                }
            }
        }
    }

    $snippet = preg_replace('/\s+/', ' ', $body);
    $snippet = trim(substr((string) $snippet, 0, 240));
    if ($snippet !== '') {
        return 'HTTP ' . $status . ' API response: ' . $snippet;
    }

    return $fallback;
}

function secureit_http_post_json_with_headers(string $url, array $headers, array $payload): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'body' => '',
            'headers' => [],
            'error' => 'Unable to initialise the request.',
        ];
    }

    $responseHeaders = [];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{}';
    }

    $requestHeaders = array_merge([
        'Accept: application/json',
        'Content-Type: application/json',
        'Content-Length: ' . strlen($body),
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
            return secureit_http_capture_header_line($responseHeaders, $headerLine);
        },
        CURLOPT_HTTPHEADER => $requestHeaders,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'body' => '',
            'headers' => $responseHeaders,
            'error' => $curlError !== '' ? $curlError : 'The request failed before a response body was returned.',
        ];
    }

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => is_string($response) ? (json_decode($response, true) ?: []) : [],
            'body' => (string) $response,
            'headers' => $responseHeaders,
            'error' => secureit_http_describe_api_error(
                $status,
                (string) $response,
                'The request was rejected with HTTP ' . $status . '.'
            ),
        ];
    }

    $data = json_decode((string) $response, true);

    return [
        'ok' => true,
        'status' => $status,
        'data' => is_array($data) ? $data : [],
        'body' => (string) $response,
        'headers' => $responseHeaders,
        'error' => '',
    ];
}

function secureit_github_repository_parts(string $repository): array {
    $repository = trim($repository);
    if ($repository === '') {
        return [];
    }

    $parts = explode('/', $repository, 2);
    if (count($parts) !== 2) {
        return [];
    }

    $owner = trim($parts[0]);
    $name = trim($parts[1]);
    if ($owner === '' || $name === '') {
        return [];
    }

    return [
        'owner' => $owner,
        'name' => $name,
    ];
}

function secureit_github_dispatch_config(): array {
    $config = secureit_config();

    return [
        'repository' => trim((string) ($config['github_repository'] ?? 'matt-edu365/secureit')),
        'workflowFile' => trim((string) ($config['github_workflow_file'] ?? 'secureit-production.yml')),
        'ref' => trim((string) ($config['github_workflow_ref'] ?? 'main')),
        'token' => trim((string) ($config['github_token'] ?? '')),
    ];
}

function secureit_github_dispatch_workflow(array $inputs): array {
    $config = secureit_github_dispatch_config();
    if ($config['token'] === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'error' => 'GitHub workflow dispatch is not configured because SECUREIT_GITHUB_TOKEN is empty.',
            'workflowUrl' => '',
            'repository' => $config['repository'],
            'workflowFile' => $config['workflowFile'],
            'ref' => $config['ref'],
        ];
    }

    $repositoryParts = secureit_github_repository_parts($config['repository']);
    if ($repositoryParts === []) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'error' => 'GitHub repository is not configured in owner/repo form.',
            'workflowUrl' => '',
            'repository' => $config['repository'],
            'workflowFile' => $config['workflowFile'],
            'ref' => $config['ref'],
        ];
    }

    if ($config['workflowFile'] === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'headers' => [],
            'error' => 'GitHub workflow file is not configured.',
            'workflowUrl' => '',
            'repository' => $config['repository'],
            'workflowFile' => $config['workflowFile'],
            'ref' => $config['ref'],
        ];
    }

    $workflowUrl = 'https://github.com/' . rawurlencode($repositoryParts['owner']) . '/' . rawurlencode($repositoryParts['name']) . '/actions/workflows/' . rawurlencode($config['workflowFile']);
    $dispatchUrl = 'https://api.github.com/repos/' . rawurlencode($repositoryParts['owner']) . '/' . rawurlencode($repositoryParts['name']) . '/actions/workflows/' . rawurlencode($config['workflowFile']) . '/dispatches';
    $response = secureit_http_post_json_with_headers($dispatchUrl, [
        'Authorization: Bearer ' . $config['token'],
        'User-Agent: SecureIT',
        'X-GitHub-Api-Version: 2022-11-28',
    ], [
        'ref' => $config['ref'],
        'inputs' => $inputs,
    ]);

    $response['workflowUrl'] = $workflowUrl;
    $response['repository'] = $config['repository'];
    $response['workflowFile'] = $config['workflowFile'];
    $response['ref'] = $config['ref'];
    $response['dispatchUrl'] = $dispatchUrl;

    return $response;
}

function secureit_new_guid(): string {
    $bytes = bin2hex(random_bytes(16));

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($bytes, 0, 8),
        substr($bytes, 8, 4),
        substr($bytes, 12, 4),
        substr($bytes, 16, 4),
        substr($bytes, 20, 12)
    );
}

function secureit_http_capture_header_line(array &$headers, string $headerLine): int {
    $length = strlen($headerLine);
    $headerLine = trim($headerLine);
    if ($headerLine === '' || str_starts_with($headerLine, 'HTTP/')) {
        return $length;
    }

    $parts = explode(':', $headerLine, 2);
    if (count($parts) !== 2) {
        return $length;
    }

    $name = strtolower(trim($parts[0]));
    $value = trim($parts[1]);
    if ($name !== '') {
        $headers[$name] = $value;
    }

    return $length;
}

function secureit_http_post_json_with_bearer(string $url, string $bearerToken, array $payload): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'body' => '',
            'headers' => [],
            'error' => 'Unable to initialise the request.',
            'clientRequestId' => '',
        ];
    }

    $responseHeaders = [];
    $clientRequestId = secureit_new_guid();
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{}';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
            return secureit_http_capture_header_line($responseHeaders, $headerLine);
        },
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
            'client-request-id: ' . $clientRequestId,
            'return-client-request-id: true',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => [],
            'body' => '',
            'headers' => $responseHeaders,
            'error' => $curlError !== '' ? $curlError : 'The request failed before a response body was returned.',
            'clientRequestId' => $clientRequestId,
        ];
    }

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => is_string($response) ? (json_decode($response, true) ?: []) : [],
            'body' => (string) $response,
            'headers' => $responseHeaders,
            'error' => secureit_http_describe_json_error(
                $status,
                (string) $response,
                'Microsoft Graph rejected the request with HTTP ' . $status . '.'
            ),
            'clientRequestId' => $clientRequestId,
        ];
    }

    $data = json_decode((string) $response, true);

    return [
        'ok' => true,
        'status' => $status,
        'data' => is_array($data) ? $data : [],
        'body' => (string) $response,
        'headers' => $responseHeaders,
        'error' => '',
        'clientRequestId' => $clientRequestId,
    ];
}

function secureit_base64url_encode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function secureit_base64url_decode(string $value): string|false {
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function secureit_random_base64url(int $bytes = 32): string {
    return secureit_base64url_encode(random_bytes($bytes));
}

function secureit_jwt_decode_segment(string $segment): array|string|null {
    $decoded = secureit_base64url_decode($segment);
    if ($decoded === false) {
        return null;
    }

    $json = json_decode($decoded, true);
    return is_array($json) ? $json : $decoded;
}

function secureit_jwt_decode(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    $header = secureit_jwt_decode_segment($parts[0]);
    $payload = secureit_jwt_decode_segment($parts[1]);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }

    return [
        'header' => $header,
        'payload' => $payload,
        'signedPart' => $parts[0] . '.' . $parts[1],
        'signature' => $parts[2],
    ];
}

function secureit_jwt_verify_signature(string $jwt, array $jwks): bool {
    $decoded = secureit_jwt_decode($jwt);
    if (!$decoded) {
        return false;
    }

    $kid = (string) ($decoded['header']['kid'] ?? '');
    $alg = (string) ($decoded['header']['alg'] ?? '');
    if ($kid === '' || !in_array($alg, ['RS256'], true)) {
        return false;
    }

    foreach (($jwks['keys'] ?? []) as $key) {
        if (!is_array($key) || (string) ($key['kid'] ?? '') !== $kid) {
            continue;
        }

        $x5c = $key['x5c'][0] ?? null;
        if (!is_string($x5c) || $x5c === '') {
            continue;
        }

        $certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($x5c, 64, "\n") . "-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            continue;
        }

        $signature = secureit_base64url_decode((string) $decoded['signature']);
        if ($signature === false) {
            return false;
        }

        $result = openssl_verify((string) $decoded['signedPart'], $signature, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    return false;
}

function secureit_parse_comma_list(string $value): array {
    $items = [];
    foreach (preg_split('/[,\s;]+/', $value) ?: [] as $item) {
        $item = strtolower(trim((string) $item));
        if ($item !== '') {
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}

function secureit_entra_login_url(?string $loginHint = null): string {
    $config = secureit_entra_config();
    $state = secureit_random_base64url(24);
    $nonce = secureit_random_base64url(24);
    $verifier = secureit_random_base64url(48);
    $challenge = secureit_base64url_encode(hash('sha256', $verifier, true));

    secureit_start_session();
    $_SESSION['secureit_entra_oidc'] = [
        'state' => $state,
        'nonce' => $nonce,
        'codeVerifier' => $verifier,
        'createdAt' => time(),
    ];

    $params = [
        'client_id' => $config['clientId'],
        'response_type' => 'code',
        'redirect_uri' => secureit_entra_redirect_uri(),
        'response_mode' => 'query',
        'scope' => 'openid profile email',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ];

    $loginHint = strtolower(trim((string) $loginHint));
    if ($loginHint !== '') {
        $params['login_hint'] = $loginHint;
        $domain = substr(strrchr($loginHint, '@') ?: '', 1);
        if ($domain !== '') {
            $params['domain_hint'] = $domain;
        }
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return secureit_entra_oauth_base() . '/authorize?' . $query;
}

function secureit_entra_auth_context(): ?array {
    secureit_start_session();
    $auth = $_SESSION['secureit_entra_oidc'] ?? null;
    return is_array($auth) ? $auth : null;
}

function secureit_entra_clear_auth_context(): void {
    secureit_start_session();
    unset($_SESSION['secureit_entra_oidc']);
}

function secureit_entra_authorize_roles(array $claims): array {
    $roles = $claims['roles'] ?? [];
    if (is_string($roles)) {
        $roles = [$roles];
    }
    if (!is_array($roles)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $roles), static fn($value) => trim($value) !== ''));
}

function secureit_entra_is_admin_claim(array $claims): bool {
    $config = secureit_entra_config();
    if (in_array($config['adminAppRole'], secureit_entra_authorize_roles($claims), true)) {
        return true;
    }

    $email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? '')));
    if ($email === '' || !str_contains($email, '@')) {
        return false;
    }

    $domain = substr(strrchr($email, '@') ?: '', 1);
    if ($domain === '') {
        return false;
    }

    return in_array($domain, $config['adminEmailDomains'] ?? [], true);
}

function secureit_entra_map_tenant(string $entraTenantId): ?array {
    $entraTenantId = strtolower(trim($entraTenantId));
    if ($entraTenantId === '') {
        return null;
    }

    foreach ((secureit_load_tenants()['tenants'] ?? []) as $tenant) {
        if (!is_array($tenant)) {
            continue;
        }

        if (strtolower(trim((string) ($tenant['tenantId'] ?? ''))) === $entraTenantId) {
            return $tenant;
        }
    }

    return null;
}

function secureit_entra_response_error(string $message, string $code = 'auth_error'): void {
    secureit_clear_auth_context();
    $query = http_build_query(['auth_error' => $code, 'auth_message' => $message], '', '&', PHP_QUERY_RFC3986);
    $route = in_array($code, ['tenant_unauthorised', 'tenant_unknown'], true)
        ? '/auth/unavailable.php'
        : '/login.php';

    header('Location: ' . $route . '?' . $query, true, 302);
    exit;
}

function secureit_entra_validate_and_decode_id_token(string $idToken): array {
    $token = secureit_jwt_decode($idToken);
    if (!$token) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    $claims = $token['payload'];
    $jwks = secureit_http_get_json(secureit_entra_jwks_url());
    if (!$jwks || !secureit_jwt_verify_signature($idToken, $jwks)) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    $config = secureit_entra_config();
    $now = time();
    $issuerTenantId = strtolower(trim((string) ($claims['tid'] ?? '')));
    $issuer = strtolower(trim((string) ($claims['iss'] ?? '')));
    $expectedIssuer = $issuerTenantId !== ''
        ? 'https://login.microsoftonline.com/' . $issuerTenantId . '/v2.0'
        : '';

    if ($expectedIssuer === '' || $issuer !== strtolower($expectedIssuer)) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    if ((string) ($claims['aud'] ?? '') !== $config['clientId']) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    if (isset($claims['exp']) && (int) $claims['exp'] < ($now - 60)) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    if (isset($claims['nbf']) && (int) $claims['nbf'] > ($now + 60)) {
        return ['ok' => false, 'code' => 'token_invalid'];
    }

    $authContext = secureit_entra_auth_context();
    if (!$authContext) {
        return ['ok' => false, 'code' => 'state_mismatch'];
    }

    if ((string) ($claims['nonce'] ?? '') !== (string) ($authContext['nonce'] ?? '')) {
        return ['ok' => false, 'code' => 'state_mismatch'];
    }

    if (($config['allowedTenantIds'] ?? []) && !in_array($issuerTenantId, $config['allowedTenantIds'], true) && !secureit_entra_is_admin_claim($claims)) {
        return ['ok' => false, 'code' => 'tenant_unauthorised'];
    }

    return ['ok' => true, 'claims' => $claims];
}

function secureit_entra_exchange_code_for_tokens(string $code): array {
    $config = secureit_entra_config();
    $authContext = secureit_entra_auth_context();
    if (!$authContext) {
        return [];
    }

    return secureit_http_post_form(secureit_entra_oauth_base() . '/token', [
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => secureit_entra_redirect_uri(),
        'code_verifier' => (string) ($authContext['codeVerifier'] ?? ''),
        'scope' => 'openid profile email',
    ]);
}

function secureit_entra_graph_access_token_for_tenant(string $tenantId): string {
    $config = secureit_entra_config();
    $tenantId = trim($tenantId);

    if ($tenantId === '') {
        throw new RuntimeException('Tenant ID is required to request a Microsoft Graph token.');
    }
    if ($config['clientId'] === '' || $config['clientSecret'] === '') {
        throw new RuntimeException('Entra client credentials are not configured.');
    }

    $response = secureit_http_post_form(
        'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token',
        [
            'client_id' => $config['clientId'],
            'client_secret' => $config['clientSecret'],
            'grant_type' => 'client_credentials',
            'scope' => 'https://graph.microsoft.com/.default',
        ]
    );

    $accessToken = trim((string) ($response['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Azure token response did not include a Microsoft Graph access token.');
    }

    return $accessToken;
}

function secureit_entra_graph_get_json_for_tenant(string $tenantId, string $path): array {
    $tenantId = trim($tenantId);
    $path = '/' . ltrim($path, '/');
    $token = secureit_entra_graph_access_token_for_tenant($tenantId);
    $response = secureit_http_get_json_with_bearer('https://graph.microsoft.com/v1.0' . $path, $token);

    if (empty($response['ok'])) {
        throw new RuntimeException(trim((string) ($response['error'] ?? 'Unable to query Microsoft Graph.')));
    }

    return is_array($response['data'] ?? null) ? $response['data'] : [];
}

function secureit_entra_graph_send_mail(
    string $tenantId,
    string $senderMailbox,
    string $subject,
    string $bodyContentType,
    string $bodyContent,
    array $toRecipients,
    bool $saveToSentItems = true
): array {
    $tenantId = trim($tenantId);
    $senderMailbox = trim($senderMailbox);
    $subject = trim($subject);
    $bodyContentType = trim($bodyContentType);
    $bodyContent = (string) $bodyContent;

    if ($tenantId === '') {
        throw new RuntimeException('Tenant ID is required to send Microsoft Graph mail.');
    }
    if ($senderMailbox === '') {
        throw new RuntimeException('Sender mailbox is required to send Microsoft Graph mail.');
    }
    if ($subject === '') {
        throw new RuntimeException('Message subject is required to send Microsoft Graph mail.');
    }

    $recipientPayload = [];
    foreach ($toRecipients as $recipient) {
        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            continue;
        }

        $recipientPayload[] = [
            'emailAddress' => [
                'address' => $recipient,
            ],
        ];
    }

    if ($recipientPayload === []) {
        throw new RuntimeException('At least one recipient is required to send Microsoft Graph mail.');
    }

    $normalizedContentType = strtolower($bodyContentType);
    if ($normalizedContentType === 'text') {
        $normalizedContentType = 'Text';
    } elseif ($normalizedContentType === 'html') {
        $normalizedContentType = 'HTML';
    } else {
        throw new RuntimeException('Message body content type must be Text or HTML.');
    }

    $endpoint = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($senderMailbox) . '/sendMail';
    $token = secureit_entra_graph_access_token_for_tenant($tenantId);
    $response = secureit_http_post_json_with_bearer($endpoint, $token, [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => $normalizedContentType,
                'content' => $bodyContent,
            ],
            'toRecipients' => $recipientPayload,
        ],
        'saveToSentItems' => $saveToSentItems,
    ]);

    if (empty($response['ok'])) {
        throw new RuntimeException(trim((string) ($response['error'] ?? 'Unable to send mail through Microsoft Graph.')));
    }

    $response['endpoint'] = $endpoint;
    $response['tenantId'] = $tenantId;
    $response['senderMailbox'] = $senderMailbox;
    $response['subject'] = $subject;
    $response['bodyContentType'] = $normalizedContentType;
    $response['bodyContent'] = $bodyContent;
    $response['toRecipients'] = array_values(array_map('trim', array_filter(array_map('strval', $toRecipients), static fn(string $recipient): bool => trim($recipient) !== '')));
    $response['saveToSentItems'] = $saveToSentItems;
    $response['requestedAt'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);

    return $response;
}

function secureit_entra_resolve_tenant_identity(string $tenantId): array {
    $tenantId = trim($tenantId);
    if ($tenantId === '') {
        return [
            'ok' => false,
            'displayName' => '',
            'domain' => '',
            'message' => 'Tenant ID was not provided.',
        ];
    }

    try {
        $organization = secureit_entra_graph_get_json_for_tenant($tenantId, '/organization?$select=displayName,verifiedDomains');
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'displayName' => '',
            'domain' => '',
            'message' => $exception->getMessage(),
        ];
    }

    $items = $organization['value'] ?? [];
    if (!is_array($items) || $items === []) {
        return [
            'ok' => false,
            'displayName' => '',
            'domain' => '',
            'message' => 'Microsoft Graph did not return an organization record.',
        ];
    }

    $displayName = trim((string) ($items[0]['displayName'] ?? ''));
    $verifiedDomains = $items[0]['verifiedDomains'] ?? [];
    if (!is_array($verifiedDomains) || $verifiedDomains === []) {
        return [
            'ok' => false,
            'displayName' => $displayName,
            'domain' => '',
            'message' => 'Microsoft Graph did not return any verified domains.',
        ];
    }

    $candidates = [];
    foreach ($verifiedDomains as $domainItem) {
        if (!is_array($domainItem)) {
            continue;
        }

        $name = trim((string) ($domainItem['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $candidates[] = [
            'name' => $name,
            'isDefault' => !empty($domainItem['isDefault']),
            'isInitial' => !empty($domainItem['isInitial']),
        ];
    }

    if ($candidates === []) {
        return [
            'ok' => false,
            'displayName' => $displayName,
            'domain' => '',
            'message' => 'Microsoft Graph returned verified domains without usable names.',
        ];
    }

    foreach ($candidates as $candidate) {
        if ($candidate['isDefault']) {
            return [
                'ok' => true,
                'displayName' => $displayName,
                'domain' => $candidate['name'],
                'message' => 'Resolved from the tenant default verified domain.',
            ];
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate['isInitial']) {
            return [
                'ok' => true,
                'displayName' => $displayName,
                'domain' => $candidate['name'],
                'message' => 'Resolved from the tenant initial verified domain.',
            ];
        }
    }

    return [
        'ok' => true,
        'displayName' => $displayName,
        'domain' => $candidates[0]['name'],
        'message' => 'Resolved from the first verified domain returned by Microsoft Graph.',
    ];
}

function secureit_entra_resolve_tenant_domain(string $tenantId): array {
    return secureit_entra_resolve_tenant_identity($tenantId);
}

function secureit_entra_finalize_login(array $claims): array {
    $email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? '')));
    $name = trim((string) ($claims['name'] ?? $claims['given_name'] ?? ''));
    $entraTenantId = strtolower(trim((string) ($claims['tid'] ?? '')));

    if ($entraTenantId === '') {
        return [
            'ok' => false,
            'message' => 'The Entra tenant ID was missing from the sign-in response.',
        ];
    }

    if (secureit_entra_is_admin_claim($claims)) {
        secureit_set_auth_context('admin', $email !== '' ? $email : null, null, [
            'name' => $name,
            'tenantId' => $entraTenantId,
            'objectId' => trim((string) ($claims['oid'] ?? '')),
            'identitySource' => 'entra',
        ]);

        return [
            'ok' => true,
            'route' => '/dashboard.php',
        ];
    }

    $tenant = secureit_entra_map_tenant($entraTenantId);
    if (!$tenant) {
        return [
            'ok' => false,
            'message' => 'No SecureIT tenant is linked to that Entra tenant.',
        ];
    }

    $tenantKey = (string) ($tenant['id'] ?? '');
    secureit_set_auth_context('customer', $email !== '' ? $email : null, $tenantKey, [
        'name' => $name,
        'tenantId' => $entraTenantId,
        'objectId' => trim((string) ($claims['oid'] ?? '')),
        'identitySource' => 'entra',
    ]);

    return [
        'ok' => true,
        'route' => '/tenant.php?tenant=' . rawurlencode($tenantKey),
    ];
}

function secureit_load_tenants(): array {
    $config = secureit_config();
    $path = $config['tenants_file'];
    if (!file_exists($path)) {
        return ['tenants' => []];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : ['tenants' => []];
}

function secureit_save_tenants(array $data): void {
    $config = secureit_config();
    $path = $config['tenants_file'];
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function secureit_reports_root(): string {
    $config = secureit_config();
    $root = $config['reports_root'];
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }
    return $root;
}

function secureit_tenant_report_web_root(string $tenantKey): string {
    return dirname(__DIR__) . '/' . trim(strtolower($tenantKey));
}

function secureit_ensure_tenant_report_web_link(string $tenantKey): void {
    $tenantKey = trim(strtolower($tenantKey));
    if (!secureit_valid_tenant_key($tenantKey)) {
        return;
    }

    $linkPath = secureit_tenant_report_web_root($tenantKey);
    $targetPath = secureit_reports_root() . '/' . $tenantKey;

    if (is_link($linkPath)) {
        $linkedTarget = readlink($linkPath);
        if ($linkedTarget === $targetPath) {
            return;
        }
        @unlink($linkPath);
    } elseif (file_exists($linkPath)) {
        return;
    }

    if (!is_dir($targetPath)) {
        mkdir($targetPath, 0775, true);
    }

    @symlink($targetPath, $linkPath);
}

function secureit_build_report_base_url(string $tenantKey): string {
    $config = secureit_config();
    return $config['base_url'] . '/' . rawurlencode(trim(strtolower($tenantKey)));
}

function secureit_valid_tenant_key(string $tenantKey): bool {
    return (bool) preg_match('/^[a-z0-9-]+$/', $tenantKey);
}

function secureit_tenant_exists(array $tenants, string $tenantKey): bool {
    foreach ($tenants as $tenant) {
        if (($tenant['id'] ?? '') === $tenantKey) {
            return true;
        }
    }
    return false;
}

function secureit_tenant_summary(string $tenantKey): ?array {
    $path = secureit_reports_root() . '/' . $tenantKey . '/latest/summary.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function secureit_find_tenant(string $tenantKey): ?array {
    $config = secureit_load_tenants();
    foreach (($config['tenants'] ?? []) as $tenant) {
        if (($tenant['id'] ?? '') === $tenantKey) {
            return $tenant;
        }
    }
    return null;
}

function secureit_load_test_descriptions(): array {
    $config = secureit_config();
    $descriptions = [];

    $dir = trim((string) ($config['test_descriptions_dir'] ?? ''));
    if ($dir !== '' && is_dir($dir)) {
        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files);
        foreach ($files as $path) {
            $data = json_decode(file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $key => $value) {
                if (is_string($key) && is_string($value) && $key !== '') {
                    $descriptions[$key] = $value;
                }
            }
        }
    }

    return $descriptions;
}

function secureit_control_description_for_title(string $title, array $descriptions): string {
    $baseTitle = trim(preg_replace('/\s*\(scenario [^)]+\)$/i', '', $title) ?? $title);
    if ($baseTitle !== '' && isset($descriptions[$baseTitle])) {
        return (string) $descriptions[$baseTitle];
    }

    if ($baseTitle !== '') {
        return 'Checks that ' . strtolower($baseTitle) . ' is configured appropriately.';
    }

    return '';
}

function secureit_control_description_for_control(array $control, array $descriptions): string {
    $controlId = trim((string) ($control['id'] ?? ''));
    if ($controlId !== '' && isset($descriptions[$controlId])) {
        return (string) $descriptions[$controlId];
    }

    foreach (($control['frameworkMappings'] ?? []) as $mapping) {
        $mapping = trim((string) $mapping);
        if ($mapping !== '' && isset($descriptions[$mapping])) {
            return (string) $descriptions[$mapping];
        }
    }

    $title = trim((string) ($control['title'] ?? ''));
    if ($title !== '') {
        return secureit_control_description_for_title($title, $descriptions);
    }

    return '';
}

function secureit_start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function secureit_current_auth_context(): ?array {
    secureit_start_session();
    $auth = $_SESSION['secureit_auth'] ?? null;
    return is_array($auth) ? $auth : null;
}

function secureit_flash_set(string $key, mixed $value): void {
    secureit_start_session();
    if (!isset($_SESSION['secureit_flash']) || !is_array($_SESSION['secureit_flash'])) {
        $_SESSION['secureit_flash'] = [];
    }

    $_SESSION['secureit_flash'][$key] = $value;
}

function secureit_flash_pull(string $key, mixed $default = null): mixed {
    secureit_start_session();
    if (!isset($_SESSION['secureit_flash']) || !is_array($_SESSION['secureit_flash'])) {
        return $default;
    }

    if (!array_key_exists($key, $_SESSION['secureit_flash'])) {
        return $default;
    }

    $value = $_SESSION['secureit_flash'][$key];
    unset($_SESSION['secureit_flash'][$key]);

    if ($_SESSION['secureit_flash'] === []) {
        unset($_SESSION['secureit_flash']);
    }

    return $value;
}

function secureit_set_auth_context(string $role, ?string $email = null, ?string $tenantKey = null, array $extra = []): void {
    secureit_start_session();
    session_regenerate_id(true);
    $_SESSION['secureit_auth'] = array_merge([
        'role' => $role,
        'email' => $email,
        'tenantKey' => $tenantKey,
    ], $extra);
}

function secureit_clear_auth_context(): void {
    secureit_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function secureit_current_user_role(): ?string {
    $auth = secureit_current_auth_context();
    return isset($auth['role']) ? (string) $auth['role'] : null;
}

function secureit_user_is_admin(): bool {
    return secureit_current_user_role() === 'admin';
}

function secureit_require_admin_access(string $redirect = 'login.php?denied=1'): void {
    if (!secureit_user_is_admin()) {
        header('Location: ' . $redirect, true, 302);
        exit;
    }
}

function secureit_current_user_tenant_key(): ?string {
    $auth = secureit_current_auth_context();
    $tenantKey = trim((string) ($auth['tenantKey'] ?? ''));
    return $tenantKey !== '' ? $tenantKey : null;
}

function secureit_require_tenant_access(string $tenantKey, string $redirect = 'login.php?denied=1'): void {
    $tenantKey = trim($tenantKey);
    if ($tenantKey === '') {
        header('Location: ' . $redirect, true, 302);
        exit;
    }

    if (secureit_user_is_admin()) {
        return;
    }

    $currentTenantKey = secureit_current_user_tenant_key();
    if ($currentTenantKey !== null && strcasecmp($currentTenantKey, $tenantKey) === 0) {
        return;
    }

    header('Location: ' . $redirect, true, 302);
    exit;
}

function secureit_load_identity_seeds(): array {
    $config = secureit_config();
    $path = trim((string) ($config['identity_seeds_file'] ?? ''));
    if ($path === '' || !file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }

    $entries = $data['identities'] ?? $data['users'] ?? $data;
    if (!is_array($entries)) {
        return [];
    }

    $seeds = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $email = strtolower(trim((string) ($entry['email'] ?? $entry['upn'] ?? $entry['username'] ?? '')));
        if ($email === '') {
            continue;
        }

        $role = strtolower(trim((string) ($entry['role'] ?? $entry['access'] ?? 'customer')));
        if (in_array($role, ['admin', 'administrator', 'staff', 'ict365'], true)) {
            $role = 'admin';
        } else {
            $role = 'customer';
        }

        $seeds[$email] = [
            'email' => $email,
            'role' => $role,
            'name' => trim((string) ($entry['name'] ?? $entry['label'] ?? '')),
            'tenantKey' => trim((string) ($entry['tenantKey'] ?? $entry['tenant'] ?? '')),
        ];
    }

    return $seeds;
}

function secureit_resolve_login_route(string $email): array {
    $email = strtolower(trim($email));
    $seed = $email !== '' ? (secureit_load_identity_seeds()[$email] ?? null) : null;
    if (secureit_is_local_identity_environment() && is_array($seed)) {
        $tenantKey = trim((string) ($seed['tenantKey'] ?? ''));
        if (($seed['role'] ?? 'customer') === 'admin') {
            return [
                'route' => 'dashboard.php',
                'identity' => $seed,
                'source' => 'seed',
            ];
        }

        if ($tenantKey !== '') {
            return [
                'route' => 'tenant.php?tenant=' . rawurlencode($tenantKey),
                'identity' => $seed,
                'source' => 'seed',
            ];
        }

        return [
            'route' => 'login.php?unknown=1',
            'identity' => $seed,
            'source' => 'seed',
        ];
    }

    return [
        'route' => 'login.php?unknown=1',
        'identity' => null,
        'source' => 'default',
    ];
}

function secureit_canonical_controls_candidate_paths(): array {
    $config = secureit_config();
    $paths = [
        $config['canonical_controls_file'] ?? '',
        '/usr/local/share/secureit/canonical-controls.json',
        $config['canonical_controls_example_file'] ?? '',
    ];

    $paths = array_values(array_filter(array_map(static fn($path): string => (string) $path, $paths), static fn(string $path): bool => $path !== ''));

    return array_values(array_unique($paths));
}

function secureit_canonical_controls_loaded_path(): string {
    foreach (secureit_canonical_controls_candidate_paths() as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return '';
}

function secureit_load_canonical_controls(): array {
    foreach (secureit_canonical_controls_candidate_paths() as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $data = json_decode(file_get_contents($path), true);
        if (is_array($data)) {
            $descriptions = secureit_load_test_descriptions();
            $controls = is_array($data['controls'] ?? null) ? $data['controls'] : [];
            foreach ($controls as &$control) {
                if (!is_array($control)) {
                    continue;
                }
                $control['description'] = secureit_control_description_for_control($control, $descriptions);
            }
            unset($control);
            $data['controls'] = $controls;
            return $data;
        }
    }

    return [
        'functionalAreas' => [
            'Identity & Access Management',
            'Email & Calendaring',
            'Collaboration & Communication',
            'Files, Intranet & Content Management',
            'Endpoint & Device Management',
            'Security Operations & Threat Protection',
            'Compliance, Governance & Data Protection',
            'Productivity, Automation & AI',
        ],
        'controls' => [],
        'unmappedPolicy' => [
            'defaultDuplicatePolicy' => 'single',
            'defaultScoringWeight' => 1,
        ],
    ];
}

function secureit_total_canonical_control_count(): int {
    foreach (secureit_canonical_controls_candidate_paths() as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            continue;
        }

        $controls = is_array($data['controls'] ?? null) ? $data['controls'] : [];
        $count = count($controls);
        if ($count > 0) {
            return $count;
        }
    }

    return 0;
}

function secureit_tenant_embedded_summary(string $tenantKey): ?array {
    $path = secureit_reports_root() . '/' . $tenantKey . '/latest/embedded-summary.json';
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function secureit_normalise_mapping_id(string $value): string {
    return strtoupper(trim($value));
}

function secureit_pattern_matches_test_id(string $pattern, string $testId): bool {
    $pattern = secureit_normalise_mapping_id($pattern);
    $testId = secureit_normalise_mapping_id($testId);

    if ($pattern === $testId) {
        return true;
    }

    if (str_contains($pattern, '*')) {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\\*', '.*', $quoted) . '$/i';
        return (bool) preg_match($regex, $testId);
    }

    return false;
}

function secureit_extract_tests_from_embedded_summary(?array $embedded): array {
    if (!$embedded) {
        return [];
    }

    $tests = [];
    foreach (($embedded['Tests'] ?? []) as $test) {
        $id = trim((string) ($test['Id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $tests[] = [
            'id' => $id,
            'result' => strtolower(trim((string) ($test['Result'] ?? 'unknown'))),
            'title' => trim((string) ($test['Title'] ?? '')),
            'severity' => trim((string) ($test['Severity'] ?? '')),
            'tags' => is_array($test['Tag'] ?? null) ? $test['Tag'] : [],
        ];
    }

    return $tests;
}

function secureit_extract_test_ids_from_embedded_summary(?array $embedded): array {
    $ids = [];
    foreach (secureit_extract_tests_from_embedded_summary($embedded) as $test) {
        $ids[] = $test['id'];
    }
    return array_values(array_unique($ids));
}

function secureit_normalize_match_tokens(string $value): array {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];
    $stopWords = [
        'a', 'an', 'and', 'are', 'be', 'baseline', 'check', 'checks', 'control', 'controls',
        'ensure', 'for', 'from', 'have', 'in', 'is', 'it', 'its', 'microsoft', 'not', 'of',
        'on', 'or', 'policy', 'policies', 'review', 'security', 'set', 'should', 'that', 'the',
        'to', 'with', 'without',
    ];

    return array_values(array_filter($tokens, static fn (string $token): bool => $token !== '' && !in_array($token, $stopWords, true)));
}

function secureit_score_control_test_match(array $control, array $test): float {
    $controlTokens = secureit_normalize_match_tokens(
        trim((string) ($control['id'] ?? '')) . ' ' .
        trim((string) ($control['title'] ?? '')) . ' ' .
        trim((string) ($control['description'] ?? ''))
    );
    $testTokens = secureit_normalize_match_tokens(
        trim((string) ($test['id'] ?? '')) . ' ' .
        trim((string) ($test['title'] ?? ''))
    );

    if (!$controlTokens || !$testTokens) {
        return 0.0;
    }

    $controlSet = array_fill_keys($controlTokens, true);
    $testSet = array_fill_keys($testTokens, true);
    $sharedTokens = array_intersect_key($controlSet, $testSet);

    $idTokens = secureit_normalize_match_tokens(trim((string) ($control['id'] ?? '')));
    $testIdTokens = secureit_normalize_match_tokens(trim((string) ($test['id'] ?? '')));
    $sharedIdTokens = array_intersect($idTokens, $testIdTokens);

    return count($sharedTokens) + (count($sharedIdTokens) * 0.5);
}

function secureit_fallback_block_for_control(array $control): ?string {
    $controlKey = strtolower(trim((string) ($control['id'] ?? '')) . ' ' . trim((string) ($control['title'] ?? '')));
    if ($controlKey === '') {
        return null;
    }

    if (str_contains($controlKey, 'eidsca') || str_contains($controlKey, 'baseline review')) {
        return 'EIDSCA';
    }

    if (str_contains($controlKey, 'defender') || str_contains($controlKey, 'antivirus')) {
        return 'Maester/Defender';
    }

    if (str_contains($controlKey, 'recommendations')) {
        return 'Maester/Entra';
    }

    return null;
}

function secureit_evaluate_control_status(array $matchedTests, string $passLogic): string {
    if (!$matchedTests) {
        return 'unmapped';
    }

    $passCount = 0;
    $failCount = 0;
    $neutralCount = 0;

    foreach ($matchedTests as $test) {
        $result = $test['result'] ?? 'unknown';
        if (secureit_is_pass_result($result)) {
            $passCount++;
        } elseif (secureit_is_fail_result($result)) {
            $failCount++;
        } else {
            $neutralCount++;
        }
    }

    switch ($passLogic) {
        case 'any-pass-no-fail-review':
            if ($failCount > 0) {
                return $passCount > 0 ? 'partial' : 'fail';
            }
            return $passCount > 0 ? 'pass' : 'partial';

        case 'majority-pass':
            if ($passCount === 0 && $failCount === 0) {
                return 'partial';
            }
            if ($failCount === 0 && $passCount > 0) {
                return 'pass';
            }
            if ($passCount > 0) {
                return 'partial';
            }
            return 'fail';

        case 'direct':
        default:
            if ($passCount > 0 && $failCount === 0) {
                return 'pass';
            }
            if ($passCount > 0) {
                return 'partial';
            }
            if ($failCount > 0) {
                return 'fail';
            }
            return 'partial';
    }
}

function secureit_control_test_label(array $test): string {
    $title = trim((string) ($test['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }

    $id = trim((string) ($test['id'] ?? ''));
    if ($id === '') {
        return 'a mapped check';
    }

    $label = preg_replace('/^Test[-_.]?/i', '', $id) ?? $id;
    $label = preg_replace('/\.Tests\.ps1$/i', '', $label) ?? $label;
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label) ?? $label;
    $label = str_replace(['.', '_', '-'], ' ', $label);
    $label = trim((string) preg_replace('/\s+/', ' ', $label));

    return $label !== '' ? $label : $id;
}

function secureit_compact_detail_list(array $labels, int $limit = 3): string {
    $uniqueLabels = [];
    foreach ($labels as $label) {
        $label = trim((string) $label);
        if ($label !== '') {
            $uniqueLabels[$label] = true;
        }
    }

    $labels = array_keys($uniqueLabels);
    $count = count($labels);
    if ($count === 0) {
        return '';
    }

    if ($count === 1) {
        return $labels[0];
    }

    if ($count === 2) {
        return $labels[0] . ' and ' . $labels[1];
    }

    $visible = array_slice($labels, 0, max(2, $limit));
    $remaining = $count - count($visible);
    if ($remaining > 0) {
        return implode(', ', $visible) . ', and ' . $remaining . ' more related test' . ($remaining === 1 ? '' : 's');
    }

    $last = array_pop($visible);
    return implode(', ', $visible) . ', and ' . $last;
}

function secureit_load_control_details_catalog(): array {
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $path = __DIR__ . '/control-details.php';
    $data = file_exists($path) ? require $path : [];
    $catalog = [];
    if (is_array($data)) {
        foreach ($data as $key => $entry) {
            $normalisedKey = secureit_normalise_mapping_id((string) $key);
            if ($normalisedKey !== '' && is_array($entry)) {
                $catalog[$normalisedKey] = $entry;
            }
        }
    }

    return $catalog;
}

function secureit_format_control_detail_entry(array $entry): string {
    $inspect = trim((string) ($entry['inspect'] ?? 'the relevant Microsoft 365 setting'));
    $pass = trim((string) ($entry['pass'] ?? 'the setting matches the expected secure configuration'));
    $fail = trim((string) ($entry['fail'] ?? 'the setting is missing, too broad, or does not match the expected secure configuration'));
    $why = trim((string) ($entry['why'] ?? 'this setting affects the tenant security posture'));

    return 'Inspects ' . $inspect . '. It passes when ' . $pass . '; it fails when ' . $fail . '. This matters because ' . $why . '.';
}

function secureit_control_detail_catalog_entry(array $control): ?array {
    $catalog = secureit_load_control_details_catalog();
    $candidateKeys = [
        secureit_normalise_mapping_id((string) ($control['id'] ?? '')),
    ];

    foreach (($control['matchedTests'] ?? []) as $test) {
        if (!is_array($test)) {
            continue;
        }
        $testId = secureit_normalise_mapping_id((string) ($test['id'] ?? ''));
        if ($testId !== '') {
            $candidateKeys[] = $testId;
            $candidateKeys[] = secureit_normalise_mapping_id(preg_replace('/^TEST[-_.]?/i', '', preg_replace('/\.TESTS\.PS1$/i', '', $testId)) ?? $testId);
        }
    }

    foreach (($control['frameworkMappings'] ?? []) as $mapping) {
        $mappingId = secureit_normalise_mapping_id((string) $mapping);
        if ($mappingId !== '') {
            $candidateKeys[] = $mappingId;
        }
    }

    foreach ($candidateKeys as $key) {
        if ($key !== '' && isset($catalog[$key])) {
            return $catalog[$key];
        }
    }

    return null;
}

function secureit_control_details_for_resolved_control(array $control): string {
    $title = trim((string) ($control['title'] ?? $control['id'] ?? 'this SecureIT control'));
    if ($title === '') {
        $title = 'this SecureIT control';
    }

    $entry = secureit_control_detail_catalog_entry($control);
    if (is_array($entry)) {
        return secureit_format_control_detail_entry($entry);
    }

    return 'Inspects the Microsoft 365 setting or policy behind "' . $title . '". It passes when the setting matches the expected secure configuration; it fails when the setting is missing, too broad, or not applied as expected. This matters because the setting contributes to the tenant security posture and should be clear enough to review with the customer.';
}

function secureit_resolve_canonical_area_scores_from_artifact(?array $embedded, ?array $summary): array {
    $mapping = secureit_load_canonical_controls();

    $functionalAreas = $mapping['functionalAreas'] ?? [];
    $controls = $mapping['controls'] ?? [];
    $tests = secureit_extract_tests_from_embedded_summary($embedded);
    $availableIds = array_values(array_unique(array_map(static fn(array $test): string => $test['id'], $tests)));

    $testsById = [];
    foreach ($tests as $test) {
        $testsById[secureit_normalise_mapping_id($test['id'])][] = $test;
    }

    $groupResults = [];
    foreach (($embedded['Blocks'] ?? []) as $group) {
        $name = (string) ($group['Name'] ?? '');
        $groupResults[$name] = [
            'result' => (string) ($group['Result'] ?? ''),
            'failed' => (int) ($group['FailedCount'] ?? 0),
            'passed' => (int) ($group['PassedCount'] ?? 0),
            'error' => (int) ($group['ErrorCount'] ?? 0),
            'investigate' => (int) ($group['InvestigateCount'] ?? 0),
            'skipped' => (int) ($group['SkippedCount'] ?? 0),
            'notRun' => (int) ($group['NotRunCount'] ?? 0),
            'total' => (int) ($group['TotalCount'] ?? 0),
            'tag' => $group['Tag'] ?? [],
        ];
    }

    $areas = [];
    foreach ($functionalAreas as $area) {
        $areas[$area] = [
            'name' => $area,
            'status' => 'No data',
            'tone' => 'neutral',
            'score' => null,
            'scoreLabel' => 'Score unavailable',
            'testsTotal' => 0,
            'testsPassed' => 0,
            'testsFailed' => 0,
            'testsSkipped' => 0,
            'controlsTotal' => 0,
            'controlsPassing' => 0,
            'controlsFailing' => 0,
            'controlsPartial' => 0,
            'controlsUnmapped' => 0,
            'controls' => [],
            '_tests' => [],
        ];
    }

    foreach ($controls as $control) {
        $area = $control['functionalArea'] ?? '';
        if (!isset($areas[$area])) {
            continue;
        }

        $matchedTests = [];
        foreach (($control['frameworkMappings'] ?? []) as $pattern) {
            foreach ($availableIds as $availableId) {
                if (!secureit_pattern_matches_test_id((string) $pattern, $availableId)) {
                    continue;
                }
                $lookupId = secureit_normalise_mapping_id($availableId);
                foreach (($testsById[$lookupId] ?? []) as $test) {
                    $matchedTests[] = $test;
                }
            }
        }

        if (!$matchedTests) {
            $bestMatch = null;
            $bestScore = 0.0;
            foreach ($tests as $test) {
                if (preg_match('/^Inspect-/i', (string) ($test['id'] ?? '')) === 1) {
                    continue;
                }

                $score = secureit_score_control_test_match($control, $test);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $test;
                }
            }

            if ($bestMatch !== null && $bestScore >= 1.0) {
                $matchedTests[] = $bestMatch;
            }
        }

        if (!$matchedTests) {
            $fallbackBlock = secureit_fallback_block_for_control($control);
            if ($fallbackBlock !== null && isset($groupResults[$fallbackBlock])) {
                $matchedTests[] = [
                    'id' => 'BLOCK::' . $fallbackBlock,
                    'result' => strtolower((string) ($groupResults[$fallbackBlock]['result'] ?? 'unknown')),
                    'title' => $fallbackBlock,
                    'severity' => '',
                    'tags' => $groupResults[$fallbackBlock]['tag'] ?? [],
                ];
            }
        }

        $matchedIds = [];
        foreach ($matchedTests as $test) {
            $matchedIds[] = $test['id'];
        }
        $matchedIds = array_values(array_unique($matchedIds));

        $passLogic = (string) (($control['scoring']['passLogic'] ?? 'direct'));
        $status = secureit_evaluate_control_status($matchedTests, $passLogic);

        $areas[$area]['controlsTotal']++;
        if ($status === 'pass') {
            $areas[$area]['controlsPassing']++;
        } elseif ($status === 'partial') {
            $areas[$area]['controlsPartial']++;
        } elseif ($status === 'unmapped') {
            $areas[$area]['controlsUnmapped']++;
        } else {
            $areas[$area]['controlsFailing']++;
        }

        $resolvedControl = [
            'id' => $control['id'] ?? '',
            'title' => $control['title'] ?? '',
            'description' => $control['description'] ?? '',
            'status' => $status,
            'frameworkMappings' => $control['frameworkMappings'] ?? [],
            'matchedIds' => $matchedIds,
            'matchedTests' => $matchedTests,
            'weight' => (int) (($control['scoring']['weight'] ?? 1)),
            'passLogic' => $passLogic,
        ];
        $resolvedControl['details'] = secureit_control_details_for_resolved_control($resolvedControl);
        $areas[$area]['controls'][] = $resolvedControl;

        foreach ($matchedTests as $test) {
            $testId = secureit_normalise_mapping_id((string) ($test['id'] ?? ''));
            if ($testId === '') {
                continue;
            }
            $areas[$area]['_tests'][$testId] = $test;
        }
    }

    foreach ($areas as $areaName => &$area) {
        $uniqueTests = array_values($area['_tests'] ?? []);
        unset($area['_tests']);

        $area['testsTotal'] = count($uniqueTests);
        $area['testsPassed'] = 0;
        $area['testsFailed'] = 0;
        $area['testsSkipped'] = 0;
        foreach ($uniqueTests as $test) {
            $result = strtolower((string) ($test['result'] ?? 'unknown'));
            if (secureit_is_pass_result($result)) {
                $area['testsPassed']++;
            } elseif (secureit_is_fail_result($result)) {
                $area['testsFailed']++;
            } elseif (secureit_is_neutral_result($result)) {
                $area['testsSkipped']++;
            }
        }

        if ($area['controlsTotal'] === 0) {
            $area['status'] = 'No data';
            $area['tone'] = 'neutral';
            $area['score'] = null;
            $area['scoreLabel'] = 'Score unavailable';
            continue;
        }

        $weightedEarned = 0.0;
        $weightedTotal = 0.0;
        foreach ($area['controls'] as $control) {
            $weight = max(1, (int) ($control['weight'] ?? 1));
            $weightedTotal += $weight;
            if (($control['status'] ?? '') === 'pass') {
                $weightedEarned += $weight;
            } elseif (($control['status'] ?? '') === 'partial') {
                $weightedEarned += ($weight * 0.5);
            }
        }

        $score = $weightedTotal > 0 ? (int) round(($weightedEarned / $weightedTotal) * 100) : null;
        $area['score'] = $score;
        $areaStatus = secureit_functional_area_status_from_score($score);
        $area['status'] = $areaStatus['status'];
        $area['tone'] = $areaStatus['tone'];
        $area['scoreLabel'] = $areaStatus['scoreLabel'];
        if ($score === null) {
            $area['scoreLabel'] = 'Score unavailable';
        }
    }
    unset($area);

    return [
        'summary' => $summary,
        'embedded' => $embedded,
        'groups' => $groupResults,
        'areas' => array_values($areas),
        'availableTestIds' => $availableIds,
    ];
}

function secureit_resolve_canonical_area_scores(string $tenantKey): array {
    return secureit_resolve_canonical_area_scores_from_artifact(
        secureit_tenant_embedded_summary($tenantKey),
        secureit_tenant_summary($tenantKey)
    );
}

function secureit_secret_name(string $tenantKey, string $suffix): string {
    return 'secureit-' . trim(strtolower($tenantKey)) . '-' . $suffix;
}

function secureit_guid_like(string $value): bool {
    return (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $value);
}

function secureit_summary_counts(?array $summary): array {
    $total = (int) ($summary['total'] ?? 0);
    $passed = (int) ($summary['passed'] ?? 0);
    $failed = (int) ($summary['failed'] ?? 0);
    $skipped = (int) ($summary['skipped'] ?? 0);
    $completed = max(0, $passed + $failed + $skipped);
    $passRate = $total > 0 ? (int) round(($passed / $total) * 100) : 0;
    $riskLevel = 'No data';
    $riskTone = 'neutral';

    if ($total > 0) {
        if ($failed === 0) {
            $riskLevel = 'Healthy';
            $riskTone = 'good';
        } elseif ($failed <= 3) {
            $riskLevel = 'Watch';
            $riskTone = 'warn';
        } else {
            $riskLevel = 'Needs attention';
            $riskTone = 'bad';
        }
    }

    return [
        'total' => $total,
        'passed' => $passed,
        'failed' => $failed,
        'skipped' => $skipped,
        'completed' => $completed,
        'passRate' => $passRate,
        'riskLevel' => $riskLevel,
        'riskTone' => $riskTone,
    ];
}

function secureit_check_summary_counts(array $areaData): array {
    $areas = $areaData['areas'] ?? [];

    $total = 0;
    $passed = 0;
    $partial = 0;
    $failed = 0;
    $unmapped = 0;

    foreach ($areas as $area) {
        $total += (int) ($area['controlsTotal'] ?? 0);
        $passed += (int) ($area['controlsPassing'] ?? 0);
        $partial += (int) ($area['controlsPartial'] ?? 0);
        $failed += (int) ($area['controlsFailing'] ?? 0);
        $unmapped += (int) ($area['controlsUnmapped'] ?? 0);
    }

    $completed = max(0, $passed + $partial + $failed);
    $passRate = $total > 0 ? (int) round(($passed / $total) * 100) : 0;
    $riskLevel = 'No data';
    $riskTone = 'neutral';

    if ($total > 0) {
        if ($failed === 0) {
            $riskLevel = 'Healthy';
            $riskTone = 'good';
        } elseif ($failed <= 3) {
            $riskLevel = 'Watch';
            $riskTone = 'warn';
        } else {
            $riskLevel = 'Needs attention';
            $riskTone = 'bad';
        }
    }

    return [
        'total' => $total,
        'passed' => $passed,
        'partial' => $partial,
        'failed' => $failed,
        'unmapped' => $unmapped,
        'completed' => $completed,
        'passRate' => $passRate,
        'riskLevel' => $riskLevel,
        'riskTone' => $riskTone,
    ];
}

function secureit_tenant_analysis_text(?array $summary, array $areaData): string {
    if (!$summary) {
        return 'No report summary is available yet for this tenant.';
    }

    $counts = secureit_check_summary_counts($areaData);
    $runDate = secureit_format_date_only($summary['generatedAt'] ?? null);
    $areas = $areaData['areas'] ?? [];

    $worstArea = null;
    $bestArea = null;
    foreach ($areas as $area) {
        $score = $area['score'];
        if ($score === null) {
            continue;
        }
        if ($worstArea === null || $score < ($worstArea['score'] ?? 101)) {
            $worstArea = $area;
        }
        if ($bestArea === null || $score > ($bestArea['score'] ?? -1)) {
            $bestArea = $area;
        }
    }

    if ($counts['failed'] === 0) {
        $posture = 'is healthy';
    } elseif ($counts['failed'] <= 3) {
        $posture = 'is on the watch list';
    } else {
        $posture = 'is in need of attention';
    }

    $worstAreaName = 'n/a';
    $worstAreaScore = 'n/a';
    if ($worstArea && isset($worstArea['name'])) {
        $worstScore = $worstArea['score'];
        $worstAreaName = $worstArea['name'];
        $worstAreaScore = $worstScore !== null ? (string) $worstScore : 'n/a';
    }

    $bestAreaName = 'n/a';
    $bestAreaScore = 'n/a';
    if ($bestArea && isset($bestArea['name'])) {
        $bestScore = $bestArea['score'];
        $bestAreaName = $bestArea['name'];
        $bestAreaScore = $bestScore !== null ? (string) $bestScore : 'n/a';
    }

    return sprintf(
        'The latest run on %s covers %d SecureIT checks. The overall posture %s. The lowest-scoring area is currently %s at %s%%. The strongest area is %s at %s%%.',
        $runDate,
        $counts['total'],
        $posture,
        $worstAreaName,
        $worstAreaScore,
        $bestAreaName,
        $bestAreaScore
    );
}

function secureit_format_datetime(?string $value): string {
    if (!$value) {
        return 'Unknown';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('j M Y, H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function secureit_format_date_only(?string $value): string {
    if (!$value) {
        return 'Unknown';
    }

    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('j M Y');
    } catch (Throwable $e) {
        return $value;
    }
}

function secureit_default_hero_background(): string {
    $heroSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 600" fill="none">
  <defs>
    <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0a3d32" stop-opacity="0.08"/>
      <stop offset="1" stop-color="#339997" stop-opacity="0.08"/>
    </linearGradient>
    <linearGradient id="g2" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.28"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0.04"/>
    </linearGradient>
  </defs>
  <rect width="1600" height="600" fill="url(#g1)"/>
  <path d="M150 470C310 360 420 330 580 350C700 365 766 420 850 445C980 483 1090 450 1210 375C1330 300 1430 275 1540 295V600H150V470Z" fill="#0a3d32" fill-opacity="0.14"/>
  <path d="M190 410L350 260L495 325L640 210L800 265L940 155L1110 245L1250 185L1430 285" stroke="url(#g2)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M255 175C255 160 267 148 282 148H388C403 148 415 160 415 175V281C415 296 403 308 388 308H282C267 308 255 296 255 281V175Z" fill="#ffffff" fill-opacity="0.08" stroke="#ffffff" stroke-opacity="0.18"/>
  <path d="M318 188L326 206L345 209L331 222L335 241L318 231L301 241L305 222L291 209L310 206L318 188Z" fill="#ffffff" fill-opacity="0.75"/>
  <path d="M845 135C845 120 857 108 872 108H1022C1037 108 1049 120 1049 135V270C1049 285 1037 297 1022 297H872C857 297 845 285 845 270V135Z" fill="#ffffff" fill-opacity="0.08" stroke="#ffffff" stroke-opacity="0.18"/>
  <path d="M931 152C931 137 944 124 959 124C974 124 987 137 987 152V194C987 209 974 222 959 222C944 222 931 209 931 194V152Z" fill="#ffffff" fill-opacity="0.35"/>
  <path d="M959 171C969 171 977 179 977 189C977 198 969 206 959 206C949 206 941 198 941 189C941 179 949 171 959 171Z" fill="#0a3d32" fill-opacity="0.45"/>
  <path d="M1120 312C1120 297 1132 285 1147 285H1308C1323 285 1335 297 1335 312V448C1335 463 1323 475 1308 475H1147C1132 475 1120 463 1120 448V312Z" fill="#ffffff" fill-opacity="0.08" stroke="#ffffff" stroke-opacity="0.18"/>
  <path d="M1212 349L1227 382H1198L1212 349Z" fill="#ffffff" fill-opacity="0.55"/>
  <circle cx="1228" cy="342" r="10" fill="#ffffff" fill-opacity="0.22"/>
  <circle cx="1281" cy="404" r="9" fill="#ffffff" fill-opacity="0.18"/>
  <circle cx="719" cy="378" r="12" fill="#ffffff" fill-opacity="0.16"/>
  <circle cx="640" cy="262" r="8" fill="#ffffff" fill-opacity="0.2"/>
  <circle cx="1040" cy="228" r="10" fill="#ffffff" fill-opacity="0.16"/>
</svg>
SVG;

    return "linear-gradient(135deg, rgba(10,61,50,0.98) 0%, rgba(0,99,95,0.94) 48%, rgba(51,153,151,0.88) 100%), url('data:image/svg+xml;charset=UTF-8," . rawurlencode($heroSvg) . "') center/cover no-repeat";
}

function secureit_favicon_data_uri(): string {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none">
  <rect width="64" height="64" rx="14" fill="#ffffff"/>
  <path d="M20 30v-6c0-6.627 5.373-12 12-12s12 5.373 12 12v6" fill="none" stroke="#8a6a12" stroke-width="5" stroke-linecap="round"/>
  <rect x="14" y="28" width="36" height="24" rx="6" fill="#d4af37"/>
  <circle cx="32" cy="40" r="4" fill="#ffffff"/>
  <path d="M32 40v6" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
</svg>
SVG;

    return "data:image/svg+xml;charset=UTF-8," . rawurlencode($svg);
}

function secureit_report_branding_script(string $tenantKey): string {
    $tenantUrl = json_encode('/tenant.php?tenant=' . rawurlencode(trim(strtolower($tenantKey))), JSON_UNESCAPED_SLASHES);
    return <<<HTML
<script id="secureit-branding-script-v2">
(function () {
  const tenantUrl = {$tenantUrl};

  function isDividerNode(node) {
    if (!node) {
      return false;
    }

    const text = (node.textContent || '').trim();
    return text === '|' || text === '•' || text === '·' || text === '/';
  }

  function isBrandLink(anchor) {
    if (!anchor) {
      return false;
    }

    const label = (anchor.getAttribute('aria-label') || '').trim().toLowerCase();
    const href = (anchor.getAttribute('href') || '').trim();
    const text = (anchor.textContent || '').trim().toLowerCase();

    if (label.includes('logo') || label.includes('home') || label.includes('go home')) {
      return true;
    }

    if (href === '/' && (anchor.querySelector('svg, img') || text.includes('secureit') || text.includes('ict365'))) {
      return true;
    }

    return false;
  }

  function removeHomeLink(brandLink) {
    const anchors = Array.from(document.querySelectorAll('a'));
    for (const homeLink of anchors.filter((anchor) => anchor !== brandLink && ((anchor.textContent || '').trim() === 'Home' || (anchor.getAttribute('aria-label') || '').trim().toLowerCase() === 'home'))) {
      const nextSibling = homeLink.nextSibling;
      const previousSibling = homeLink.previousSibling;
      homeLink.remove();
      if (isDividerNode(nextSibling)) {
        nextSibling.remove();
      } else if (isDividerNode(previousSibling)) {
        previousSibling.remove();
      }
    }
  }

  function applyBranding() {
    const anchors = Array.from(document.querySelectorAll('a'));
    const brandLink = anchors.find(isBrandLink);

    if (brandLink) {
      brandLink.setAttribute('href', tenantUrl);
      brandLink.setAttribute('aria-label', 'Open tenant overview');
      brandLink.style.display = 'inline-flex';
      brandLink.style.alignItems = 'center';
      brandLink.style.gap = '10px';
      brandLink.style.textDecoration = 'none';
      brandLink.style.color = 'inherit';

      const icon = brandLink.querySelector('svg, img');
      const preservedIcon = icon ? icon.cloneNode(true) : null;
      brandLink.replaceChildren();
      if (preservedIcon) {
        brandLink.appendChild(preservedIcon);
      }

      const wordmark = document.createElement('span');
      wordmark.className = 'secureit-report-wordmark';
      wordmark.innerHTML = '<span>ICT365</span><span>SecureIT</span>';
      wordmark.style.display = 'inline-flex';
      wordmark.style.flexDirection = 'column';
      wordmark.style.lineHeight = '1';
      wordmark.style.fontWeight = '700';
      wordmark.style.letterSpacing = '0.02em';
      brandLink.appendChild(wordmark);
    }

    removeHomeLink(brandLink);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyBranding, { once: true });
  } else {
    applyBranding();
  }

  const observer = new MutationObserver(applyBranding);
  observer.observe(document.documentElement, { childList: true, subtree: true });
  window.setTimeout(function () {
    observer.disconnect();
  }, 15000);
})();
</script>
HTML;
}

function secureit_brand_report_html_contents(string $html, string $tenantKey): string {
    $brandScript = secureit_report_branding_script($tenantKey);
    $html = str_replace(
        [
            'aria-label:`Home`',
            'children:`SecureIT Logo (go home)`',
            'children:`SecureIT`}),!e&&(0,z.jsxs)(`div`,{className:`flex flex-col overflow-hidden`,children:[(0,z.jsx)(`span`,{className:`text-sm font-semibold tracking-tight text-gray-900 dark:text-gray-100`,children:`SecureIT`}),(0,z.jsx)(`span`,{className:`truncate text-xs tracking-tight text-gray-500 dark:text-gray-400`,children:l})]})',
            '(0,z.jsx)(`span`,{className:`truncate text-xs tracking-tight text-gray-500 dark:text-gray-400`,children:l})'
        ],
        [
            'aria-label:`Open tenant overview`',
            'children:`ICT365 SecureIT`',
            'children:`ICT365`}),!e&&(0,z.jsxs)(`div`,{className:`flex flex-col overflow-hidden`,children:[(0,z.jsx)(`span`,{className:`text-sm font-semibold tracking-tight text-gray-900 dark:text-gray-100`,children:`ICT365`}),(0,z.jsx)(`span`,{className:`text-sm font-semibold tracking-tight text-gray-900 dark:text-gray-100`,style:{color:`#2b6e6b`},children:`SecureIT`})]})',
            '(0,z.jsx)(`span`,{className:`text-sm font-semibold tracking-tight text-gray-900 dark:text-gray-100`,style:{color:`#2b6e6b`},children:`SecureIT`})'
        ],
        $html
    );

    if (str_contains($html, 'secureit-branding-script-v2')) {
        return $html;
    }

    if (str_contains($html, 'secureit-branding-script')) {
        $updated = preg_replace('#<script id="secureit-branding-script">.*?</script>#s', $brandScript, $html, 1);
        if (is_string($updated) && $updated !== '') {
            return $updated;
        }
    }

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $brandScript . "\n</body>", $html);
    }

    return $html . "\n" . $brandScript;
}

function secureit_brand_report_html_file(string $path, string $tenantKey): void {
    if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
        return;
    }

    $html = file_get_contents($path);
    if ($html === false || $html === '') {
        return;
    }

    $branded = secureit_brand_report_html_contents($html, $tenantKey);
    if ($branded !== $html) {
        file_put_contents($path, $branded);
    }
}

function secureit_brand_report_html_tree(string $root, string $tenantKey): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
            continue;
        }

        secureit_brand_report_html_file($file->getPathname(), $tenantKey);
    }
}

function secureit_dashboard_stats(array $tenants): array {
    $stats = [
        'tenantCount' => count($tenants),
        'reportingCount' => 0,
        'healthyCount' => 0,
        'attentionCount' => 0,
        'latestGeneratedAt' => null,
    ];

    foreach ($tenants as $tenant) {
        $tenantKey = $tenant['id'] ?? '';
        if ($tenantKey === '') {
            continue;
        }

        $summary = secureit_tenant_summary($tenantKey);
        if (!$summary) {
            continue;
        }

        $stats['reportingCount']++;
        $counts = secureit_summary_counts($summary);
        if ($counts['riskTone'] === 'good') {
            $stats['healthyCount']++;
        }
        if ($counts['riskTone'] === 'bad') {
            $stats['attentionCount']++;
        }

        $generatedAt = $summary['generatedAt'] ?? null;
        if ($generatedAt && ($stats['latestGeneratedAt'] === null || strcmp($generatedAt, $stats['latestGeneratedAt']) > 0)) {
            $stats['latestGeneratedAt'] = $generatedAt;
        }
    }

    return $stats;
}

function secureit_render_shell(string $title, string $content, array $options = []): void {
    $pageTitle = $options['pageTitle'] ?? null;
    $pageIntro = $options['pageIntro'] ?? null;
    $backHref = $options['backHref'] ?? null;
    $backLabel = $options['backLabel'] ?? 'Back';
    $heroBadges = $options['heroBadges'] ?? [];
    $heroActions = $options['heroActions'] ?? [];
    $eyebrow = $options['eyebrow'] ?? 'ICT365 SecureIT';
    $navLinks = $options['navLinks'] ?? [];
    $navCta = $options['navCta'] ?? null;
    $heroIntroMaxWidth = $options['heroIntroMaxWidth'] ?? '760px';
    $hideHeroChrome = (bool) ($options['hideHeroChrome'] ?? false);
    $headerMenu = $options['headerMenu'] ?? [];
    $heroBackground = $options['heroBackground'] ?? null;
    $heroTextAlign = $options['heroTextAlign'] ?? 'left';
    $authContext = secureit_current_auth_context();
    if ($authContext !== null) {
        $headerMenu[] = ['href' => 'logout.php', 'label' => 'Logout'];
    }
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title); ?></title>
  <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(secureit_favicon_data_uri()); ?>">
  <style>
    :root {
      color-scheme: light;
      --bg: #f8fbfb;
      --surface: #ffffff;
      --surface-muted: #f4f8f8;
      --surface-soft: #eef6f6;
      --text: #173530;
      --muted: #5f7874;
      --line: rgba(0, 99, 95, 0.12);
      --shadow: 0 18px 44px rgba(10, 61, 50, 0.09);
      --brand: #00635f;
      --brand-strong: #004f4c;
      --brand-accent: #339997;
      --eden: #0a3d32;
      --good: #0c7b57;
      --good-bg: #e8f8f1;
      --warn: #a46212;
      --warn-bg: #fff6e9;
      --bad: #af4d1a;
      --bad-bg: #fff1eb;
      --neutral: #46655f;
      --neutral-bg: #edf3f2;
      --radius-xl: 28px;
      --radius-lg: 20px;
      --radius-md: 14px;
      --radius-sm: 999px;
      --footer-bg: #111827;
      --footer-line: rgba(255,255,255,0.08);
      --footer-text: #d1d5db;
      --footer-muted: #9ca3af;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
      background: linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
      min-height: 100vh;
    }
    a { color: inherit; }
    .container {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
    }
    .site-header {
      position: sticky;
      top: 0;
      z-index: 50;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(0, 99, 95, 0.08);
      box-shadow: 0 8px 28px rgba(10, 61, 50, 0.05);
    }
    .nav-wrap {
      min-height: 80px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
    }
    .brand-link {
      display: inline-flex;
      align-items: center;
      text-decoration: none;
      flex-shrink: 0;
      font-weight: 800;
      font-size: 1.3rem;
      color: var(--brand-strong);
      letter-spacing: -0.02em;
    }
    .brand-link span:last-child {
      color: #2b6e6b;
      margin-left: 6px;
    }
    .main-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 28px;
      flex: 1;
    }
    .nav-links {
      display: flex;
      align-items: center;
      gap: 28px;
      flex-wrap: wrap;
    }
    .nav-link {
      color: #495f5b;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.97rem;
    }
    .nav-link:hover { color: var(--eden); }
    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-left: auto;
    }
    .menu-dropdown {
      position: relative;
    }
    .menu-trigger {
      min-width: 46px;
      width: 46px;
      height: 46px;
      padding: 0;
      border-radius: 12px;
      background: #ffffff;
      color: var(--brand-strong);
      border: 1px solid rgba(0, 99, 95, 0.12);
      box-shadow: 0 8px 24px rgba(10, 61, 50, 0.08);
    }
    .menu-trigger:hover {
      background: #f4fbfb;
      box-shadow: 0 12px 26px rgba(10, 61, 50, 0.12);
    }
    .menu-panel {
      position: absolute;
      right: 0;
      top: calc(100% + 10px);
      min-width: 250px;
      background: #fff;
      border: 1px solid rgba(0, 99, 95, 0.12);
      border-radius: 18px;
      box-shadow: 0 18px 44px rgba(10, 61, 50, 0.14);
      padding: 10px;
      display: none;
      z-index: 60;
    }
    .menu-dropdown.is-open .menu-panel {
      display: block;
    }
    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      text-decoration: none;
      color: var(--text);
      font-weight: 600;
    }
    .menu-item:hover {
      background: #f4fbfb;
      color: var(--eden);
    }
    .button, .button-secondary, button {
      appearance: none;
      border: 0;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 16px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 600;
      transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .button:hover, .button-secondary:hover, button:hover { transform: translateY(-1px); }
    .button, button {
      background: white;
      color: var(--brand);
      box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }
    .header-actions .button {
      background: var(--brand);
      color: #fff;
      box-shadow: none;
    }
    .button-secondary {
      background: rgba(255,255,255,0.08);
      color: white;
      border: 1px solid rgba(255,255,255,0.16);
    }
    .button-ghost {
      background: transparent;
      color: var(--brand-strong);
      border: 1px solid rgba(0, 99, 95, 0.12);
      box-shadow: none;
    }
    .app-shell {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto;
      padding: 30px 0 52px;
    }
    .hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, rgba(0,99,95,0.92) 0%, rgba(0,99,95,0.90) 45%, rgba(51,153,151,0.85) 100%);
      color: #fff;
      border-radius: var(--radius-xl);
      padding: 34px;
      box-shadow: var(--shadow);
      margin-bottom: 24px;
      border: 1px solid rgba(255,255,255,0.08);
    }
    .hero-row {
      position: relative;
      z-index: 1;
      display: flex;
      justify-content: space-between;
      gap: 20px;
      align-items: flex-start;
      flex-wrap: wrap;
    }
    .eyebrow,
    .hero-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      background: rgba(255,255,255,0.13);
      color: rgba(255,255,255,0.94);
      font-size: 0.86rem;
      border: 1px solid rgba(255,255,255,0.12);
    }
    .eyebrow { margin-bottom: 14px; }
    h1, h2, h3, p { margin-top: 0; }
    .hero h1 {
      margin-bottom: 10px;
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1.02;
    }
    .hero p {
      margin-bottom: 0;
      max-width: 760px;
      color: rgba(255,255,255,0.88);
      font-size: 1rem;
      line-height: 1.6;
      white-space: pre-line;
    }
    .hero-actions,
    .hero-pill-row,
    .inline-links {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }
    .hero-pill-row { margin-top: 16px; }
    .section { margin-top: 22px; }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 16px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }
    .section-title {
      margin-bottom: 4px;
      font-size: 1.2rem;
    }
    .muted {
      color: var(--muted);
      line-height: 1.65;
    }
    .metrics-grid, .tenant-grid, .feature-grid, .portal-grid, .partner-grid {
      display: grid;
      gap: 16px;
    }
    .metrics-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tenant-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .feature-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .portal-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .partner-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
    }
    .metric-card, .tenant-card, .panel, .feature-card, .portal-card, .partner-card, .metric-stat {
      padding: 24px;
      min-width: 0;
    }
    .metric-card {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 10px;
      min-height: 180px;
    }
    .metrics-strip {
      margin-top: 8px;
      margin-bottom: 6px;
    }
    .metrics-strip .metrics-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .metric-stat {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      text-align: left;
    }
    .metric-label, .metric-note {
      color: var(--muted);
      line-height: 1.55;
      overflow-wrap: anywhere;
    }
    .metric-value {
      font-size: clamp(2rem, 3vw, 3rem);
      line-height: 1.05;
      font-weight: 800;
      margin-bottom: 8px;
      color: var(--brand);
      overflow-wrap: anywhere;
    }
    .tenant-card, .portal-card {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    .tenant-head {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: flex-start;
    }
    .tenant-name { margin-bottom: 6px; font-size: 1.2rem; }
    .tenant-meta {
      display: grid;
      gap: 6px;
      font-size: 0.93rem;
      color: var(--muted);
      overflow-wrap: anywhere;
    }
    .portal-icon,
    .feature-icon {
      width: 56px;
      height: 56px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 18px;
      font-size: 1.6rem;
      background: var(--surface-soft);
      border: 1px solid var(--line);
    }
    .section-heading {
      margin-bottom: 22px;
    }
    .section-kicker {
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--brand);
      font-weight: 800;
      margin-bottom: 10px;
    }
    .section-intro {
      color: var(--muted);
      line-height: 1.75;
      max-width: 860px;
      margin-bottom: 0;
    }
    .alt {
      background: rgba(0, 99, 95, 0.04);
      border: 1px solid rgba(0, 99, 95, 0.08);
      border-radius: var(--radius-xl);
      padding: 28px 0;
    }
    .partner-card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.86rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .tone-good { color: var(--good); background: var(--good-bg); }
    .tone-warn { color: var(--warn); background: var(--warn-bg); }
    .tone-bad { color: var(--bad); background: var(--bad-bg); }
    .tone-neutral { color: var(--neutral); background: var(--neutral-bg); }
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }
    .stat-chip {
      background: var(--surface-muted);
      border-radius: 14px;
      padding: 12px;
      min-height: 78px;
      border: 1px solid rgba(0, 99, 95, 0.08);
    }
    .stat-chip strong {
      display: block;
      font-size: 1.1rem;
      margin-bottom: 5px;
      color: var(--eden);
    }
    .progress {
      height: 10px;
      border-radius: 999px;
      background: #d8eceb;
      overflow: hidden;
    }
    .progress-bar {
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(90deg, var(--brand) 0%, var(--brand-accent) 100%);
    }
    .split {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
      gap: 28px;
      align-items: stretch;
    }
    .kv { display: grid; gap: 12px; }
    .kv-row {
      display: grid;
      grid-template-columns: 150px 1fr;
      gap: 10px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--line);
    }
    .kv-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .kv-label { color: var(--muted); font-size: 0.92rem; }
    .kv-value { word-break: break-word; }
    .table-wrap {
      overflow-x: auto;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: var(--surface);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--surface);
    }
    th, td {
      text-align: left;
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
      font-size: 0.95rem;
      vertical-align: middle;
    }
    th {
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      background: #f5fbfb;
      color: var(--muted);
    }
    tr:last-child td { border-bottom: 0; }
    .textlink {
      color: var(--brand);
      text-decoration: none;
      font-weight: 600;
    }
    .textlink:hover { text-decoration: underline; }
    .empty-state, .success, .error {
      padding: 22px;
      border-radius: var(--radius-lg);
    }
    .empty-state {
      background: linear-gradient(180deg, #f3fbfa 0%, #edf7f6 100%);
      border: 1px solid rgba(0, 99, 95, 0.16);
      color: var(--eden);
    }
    .success {
      color: #136045;
      background: #edf9f5;
      border: 1px solid #b9ead5;
    }
    .error {
      color: #9b3f17;
      background: #fff3ef;
      border: 1px solid #f3c8b7;
    }
    input, select {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      padding: 0.85rem 0.95rem;
      margin-top: 0.4rem;
      border: 1px solid #cfe2df;
      border-radius: 12px;
      background: #fff;
      color: var(--text);
      font: inherit;
    }
    input:focus, select:focus {
      outline: none;
      border-color: var(--brand-accent);
      box-shadow: 0 0 0 4px rgba(51, 153, 151, 0.16);
    }
    input[readonly], input[disabled] {
      background: #f4f8f8;
      color: #4d6763;
    }
    label {
      display: block;
      margin-top: 1rem;
      font-weight: 700;
      color: var(--eden);
    }
    .field-note {
      margin-top: 8px;
      color: var(--muted);
      font-size: 0.93rem;
      line-height: 1.6;
    }
    pre {
      background: #153a35;
      color: #eef9f7;
      padding: 1rem;
      border-radius: 14px;
      overflow: auto;
      border: 1px solid rgba(255,255,255,0.08);
    }
    .site-footer {
      margin-top: 64px;
      background: var(--footer-bg);
      color: var(--footer-text);
      border-top: 1px solid var(--footer-line);
    }
    .footer-wrap {
      padding: 24px 0;
    }
    .footer-contact-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 22px;
      align-items: center;
    }
    .footer-contact-row a {
      color: var(--footer-text);
      font-size: 0.95rem;
      font-weight: 700;
      text-decoration: none;
      overflow-wrap: anywhere;
    }
    .footer-contact-row a:nth-child(2) {
      text-align: center;
    }
    .footer-contact-row a:nth-child(3) {
      text-align: right;
    }
    .footer-contact-row a:hover {
      color: #fff;
    }
    @media (max-width: 980px) {
      .main-nav {
        flex-wrap: wrap;
        justify-content: flex-end;
      }
      .split { grid-template-columns: 1fr; }
      .metrics-grid, .feature-grid, .tenant-grid, .stats-row, .portal-grid, .partner-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .kv-row { grid-template-columns: 1fr; gap: 6px; }
    }
    @media (max-width: 640px) {
      .container, .app-shell { width: min(100% - 20px, 1180px); }
      .app-shell { padding-top: 18px; }
      .site-header { position: static; }
      .nav-wrap, .main-nav, .nav-links, .header-actions { align-items: stretch; }
      .nav-wrap, .main-nav { flex-direction: column; }
      .nav-links, .header-actions { width: 100%; }
      .header-actions { justify-content: flex-start; }
      .hero { padding: 22px; border-radius: 20px; }
      .metrics-grid, .feature-grid, .tenant-grid, .stats-row, .portal-grid, .partner-grid, .footer-contact-row { grid-template-columns: 1fr; }
      th, td { padding: 12px; }
      .footer-contact-row a,
      .footer-contact-row a:nth-child(2),
      .footer-contact-row a:nth-child(3) {
        text-align: left;
      }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container nav-wrap">
      <a class="brand-link" href="index.php" aria-label="SecureIT homepage">
        <span>ICT365</span><span>SecureIT</span>
      </a>
      <div class="main-nav">
        <nav class="nav-links" aria-label="Primary">
          <?php foreach ($navLinks as $link): ?>
            <a class="nav-link" href="<?php echo htmlspecialchars($link['href']); ?>"><?php echo htmlspecialchars($link['label']); ?></a>
          <?php endforeach; ?>
        </nav>
        <div class="header-actions">
          <?php if ($navCta): ?>
            <a class="button" href="<?php echo htmlspecialchars($navCta['href']); ?>"><?php echo htmlspecialchars($navCta['label']); ?></a>
          <?php endif; ?>
          <?php if ($headerMenu): ?>
            <div class="menu-dropdown">
              <button class="menu-trigger" type="button" aria-label="Open menu" aria-expanded="false" data-menu-trigger>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M4 12H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M4 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </button>
              <div class="menu-panel" data-menu-panel>
                <?php foreach ($headerMenu as $item): ?>
                  <a class="menu-item" href="<?php echo htmlspecialchars($item['href']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <main class="app-shell">
    <?php if ($pageTitle !== null || $pageIntro !== null): ?>
      <section class="hero card"<?php if ($heroBackground): ?> style="background: <?php echo htmlspecialchars($heroBackground); ?>;"<?php endif; ?>>
        <div class="hero-row">
          <div style="<?php echo $heroTextAlign === 'center' ? 'width:100%; text-align:center;' : ''; ?>">
            <?php if (!$hideHeroChrome && $eyebrow !== ''): ?>
              <div class="eyebrow"><?php echo htmlspecialchars($eyebrow); ?></div>
            <?php endif; ?>
            <?php if ($pageTitle !== null): ?>
              <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php endif; ?>
            <?php if ($pageIntro !== null && $pageIntro !== ''): ?>
              <p style="max-width: <?php echo htmlspecialchars($heroIntroMaxWidth); ?>;<?php echo $heroTextAlign === 'center' ? ' margin-left:auto; margin-right:auto;' : ''; ?>"><?php echo htmlspecialchars($pageIntro); ?></p>
            <?php endif; ?>
            <?php if (!$hideHeroChrome && $heroBadges): ?>
              <div class="hero-pill-row"<?php echo $heroTextAlign === 'center' ? ' style="justify-content:center;"' : ''; ?>>
                <?php foreach ($heroBadges as $badge): ?>
                  <div class="hero-pill"><?php echo htmlspecialchars($badge); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php if (!$hideHeroChrome && $heroActions): ?>
            <div class="hero-actions">
              <?php if (!$hideHeroChrome): ?>
                <?php foreach ($heroActions as $action): ?>
                  <a class="<?php echo htmlspecialchars($action['class'] ?? 'button'); ?>" href="<?php echo htmlspecialchars($action['href']); ?>"><?php echo htmlspecialchars($action['label']); ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php echo $content; ?>
  </main>

  <footer class="site-footer">
    <div class="container footer-wrap">
      <div class="footer-contact-row" aria-label="ICT365 contact details">
        <a href="mailto:helpdesk@ict365.ky">helpdesk@ict365.ky</a>
        <a href="tel:+13457450365">+1(345) 745-0365</a>
        <a href="https://ict365.ky">https://ict365.ky</a>
      </div>
    </div>
  </footer>
  <script>
    (() => {
      const dropdowns = Array.from(document.querySelectorAll('.menu-dropdown'));
      if (!dropdowns.length) return;

      const closeAll = () => {
        dropdowns.forEach((dropdown) => {
          dropdown.classList.remove('is-open');
          const trigger = dropdown.querySelector('[data-menu-trigger]');
          if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
      };

      dropdowns.forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-menu-trigger]');
        const panel = dropdown.querySelector('[data-menu-panel]');
        if (!trigger || !panel) return;

        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          const willOpen = !dropdown.classList.contains('is-open');
          closeAll();
          if (willOpen) {
            dropdown.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
          }
        });

        panel.addEventListener('click', (event) => {
          if (event.target.closest('a')) {
            closeAll();
          }
        });
      });

      document.addEventListener('click', (event) => {
        if (!event.target.closest('.menu-dropdown')) {
          closeAll();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeAll();
        }
      });
    })();
  </script>
</body>
</html>
<?php
}
