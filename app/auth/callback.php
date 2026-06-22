<?php
require __DIR__ . '/../lib.php';

if (!secureit_entra_is_enabled()) {
    secureit_entra_response_error('Microsoft Entra sign-in is not configured on this environment yet.', 'missing_config');
}

$error = trim((string) ($_REQUEST['error'] ?? ''));
if ($error !== '') {
    $description = trim((string) ($_REQUEST['error_description'] ?? ''));
    $message = $description !== '' ? $description : 'Microsoft returned an error during sign-in.';
    secureit_entra_response_error($message, $error === 'access_denied' ? 'auth_error' : 'token_invalid');
}

$state = trim((string) ($_REQUEST['state'] ?? ''));
$code = trim((string) ($_REQUEST['code'] ?? ''));

if ($code === '') {
    secureit_entra_response_error('The sign-in response was missing the authorization code.', 'missing_code');
}

$authContext = secureit_entra_auth_context();
if (!$authContext || $state === '' || !hash_equals((string) ($authContext['state'] ?? ''), $state)) {
    secureit_entra_response_error('The sign-in session could not be verified.', 'state_mismatch');
}

$tokenResponse = secureit_entra_exchange_code_for_tokens($code);
if (!is_array($tokenResponse) || !is_string($tokenResponse['id_token'] ?? null)) {
    secureit_entra_response_error('SecureIT could not complete the sign-in with Microsoft.', 'token_exchange_failed');
}

$validation = secureit_entra_validate_and_decode_id_token($tokenResponse['id_token']);
if (!($validation['ok'] ?? false)) {
    $code = (string) ($validation['code'] ?? 'token_invalid');
    $message = match ($code) {
        'state_mismatch' => 'The sign-in session could not be verified.',
        'tenant_unauthorised' => 'That Microsoft 365 tenant is not allowed to sign in here.',
        default => 'Microsoft returned a sign-in token that could not be validated.',
    };
    secureit_entra_response_error($message, $code);
}

secureit_entra_clear_auth_context();
$result = secureit_entra_finalize_login((array) ($validation['claims'] ?? []));
if (!($result['ok'] ?? false)) {
    $message = (string) ($result['message'] ?? 'The sign-in could not be completed.');
    $errorCode = str_contains(strtolower($message), 'tenant') ? 'tenant_unknown' : 'auth_error';
    secureit_entra_response_error($message, $errorCode);
}

$route = (string) ($result['route'] ?? '/login.php');
if ($route === '') {
    $route = '/login.php';
}

header('Location: ' . $route, true, 302);
exit;
