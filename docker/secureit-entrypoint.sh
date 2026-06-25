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

exec docker-php-entrypoint "$@"
