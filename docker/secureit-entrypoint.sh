#!/bin/sh
set -eu

seed_file="/usr/local/share/secureit/canonical-controls.json"
target_file="${SECUREIT_CANONICAL_CONTROLS_FILE:-/var/www/data/canonical-controls.json}"
target_dir="$(dirname "$target_file")"

if [ -f "$seed_file" ] && [ ! -s "$target_file" ]; then
    mkdir -p "$target_dir"
    cp "$seed_file" "$target_file"
    chown www-data:www-data "$target_file" || true
    chmod 0644 "$target_file" || true
fi

exec docker-php-entrypoint "$@"
