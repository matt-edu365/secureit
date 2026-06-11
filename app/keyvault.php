<?php
require_once __DIR__ . '/lib.php';

function secureit_keyvault_enabled(): bool {
    $config = secureit_config();
    return (bool) ($config['azure_tenant_id'] && $config['azure_client_id'] && $config['azure_client_secret'] && ($config['key_vault_name'] || $config['key_vault_uri']));
}

function secureit_keyvault_base_uri(): string {
    $config = secureit_config();
    if (!empty($config['key_vault_uri'])) {
        return rtrim($config['key_vault_uri'], '/');
    }
    if (!empty($config['key_vault_name'])) {
        return 'https://' . $config['key_vault_name'] . '.vault.azure.net';
    }
    throw new RuntimeException('Key Vault base URI is not configured.');
}

function secureit_keyvault_access_token(): string {
    $config = secureit_config();
    if (!secureit_keyvault_enabled()) {
        throw new RuntimeException('Azure Key Vault settings are incomplete.');
    }

    $url = 'https://login.microsoftonline.com/' . rawurlencode($config['azure_tenant_id']) . '/oauth2/v2.0/token';
    $postFields = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $config['azure_client_id'],
        'client_secret' => $config['azure_client_secret'],
        'scope' => 'https://vault.azure.net/.default',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        throw new RuntimeException('Failed to obtain Azure access token. ' . ($error ?: $response));
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('Azure token response did not include an access token.');
    }

    return $data['access_token'];
}

function secureit_keyvault_set_secret(string $secretName, string $secretValue): array {
    $token = secureit_keyvault_access_token();
    $url = secureit_keyvault_base_uri() . '/secrets/' . rawurlencode($secretName) . '?api-version=7.4';
    $payload = json_encode(['value' => $secretValue], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        throw new RuntimeException('Failed to store secret in Azure Key Vault. ' . ($error ?: $response));
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : ['raw' => $response];
}
