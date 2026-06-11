<?php
function secureit_config(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
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

function secureit_secret_name(string $tenantKey, string $suffix): string {
    return 'secureit-' . trim(strtolower($tenantKey)) . '-' . $suffix;
}

function secureit_guid_like(string $value): bool {
    return (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $value);
}
