#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/app"
DATA_DIR="$ROOT_DIR/data"
HOST="${SECUREIT_DEV_HOST:-127.0.0.1}"
PORT="${SECUREIT_DEV_PORT:-8088}"

export SECUREIT_APP_NAME="${SECUREIT_APP_NAME:-SecureIT}"
export SECUREIT_BASE_URL="${SECUREIT_BASE_URL:-http://${HOST}:${PORT}}"
export SECUREIT_TENANTS_FILE="${SECUREIT_TENANTS_FILE:-${DATA_DIR}/tenants.json}"
export SECUREIT_REPORTS_ROOT="${SECUREIT_REPORTS_ROOT:-${DATA_DIR}/reports}"
export SECUREIT_CANONICAL_CONTROLS_FILE="${SECUREIT_CANONICAL_CONTROLS_FILE:-${DATA_DIR}/canonical-controls.json}"

mkdir -p "${SECUREIT_REPORTS_ROOT}"

echo "Starting SecureIT dev server on http://${HOST}:${PORT}"
echo "App dir: ${APP_DIR}"
echo "Data dir: ${DATA_DIR}"

exec php -S "${HOST}:${PORT}" -t "${APP_DIR}"
