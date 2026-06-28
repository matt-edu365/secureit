#!/bin/sh
set -eu

seed_file="/usr/local/share/secureit/canonical-controls.json"
target_file="${SECUREIT_CANONICAL_CONTROLS_FILE:-/var/www/data/canonical-controls.json}"
target_dir="$(dirname "$target_file")"

should_seed=0

if [ -f "$seed_file" ]; then
    if [ ! -s "$target_file" ]; then
        should_seed=1
    else
        current_count="$(php -r '
$path = $argv[1];
$data = @json_decode(@file_get_contents($path), true);
if (!is_array($data) || !is_array($data["controls"] ?? null)) {
    echo 0;
    exit(0);
}
echo count($data["controls"]);
' "$target_file" 2>/dev/null || echo 0)"

        if [ "${current_count:-0}" -le 0 ]; then
            should_seed=1
        fi
    fi
fi

if [ "$should_seed" -eq 1 ]; then
    mkdir -p "$target_dir"
    cp "$seed_file" "$target_file"
    chown www-data:www-data "$target_file" || true
    chmod 0644 "$target_file" || true
fi

php -r '
$appRoot = "/var/www/html";
$lib = $appRoot . "/lib.php";
if (file_exists($lib)) {
    require_once $lib;
}
$tenantsPath = getenv("SECUREIT_TENANTS_FILE") ?: "/var/www/data/tenants.json";
$reportsRoot = getenv("SECUREIT_REPORTS_ROOT") ?: "/var/www/data/reports";
$webRoot = "/var/www/html";
$data = @json_decode(@file_get_contents($tenantsPath), true);
if (!is_array($data)) {
    exit(0);
}
foreach (($data["tenants"] ?? []) as $tenant) {
    if (!is_array($tenant)) {
        continue;
    }
    $tenantKey = strtolower(trim((string) ($tenant["id"] ?? "")));
    if (!preg_match("/^[a-z0-9-]+$/", $tenantKey)) {
        continue;
    }
    $linkPath = $webRoot . "/" . $tenantKey;
    $targetPath = $reportsRoot . "/" . $tenantKey;
    $linkOk = false;
    if (is_link($linkPath)) {
        $linkedTarget = readlink($linkPath);
        if ($linkedTarget !== $targetPath) {
            @unlink($linkPath);
        } else {
            $linkOk = true;
        }
    } elseif (file_exists($linkPath)) {
        continue;
    }
    if (!$linkOk && !is_dir($targetPath)) {
        @mkdir($targetPath, 0775, true);
    }
    if (!$linkOk) {
        @symlink($targetPath, $linkPath);
    }
    if (function_exists("secureit_brand_report_html_tree")) {
        secureit_brand_report_html_tree($targetPath, $tenantKey);
    }
}
'

exec docker-php-entrypoint "$@"
