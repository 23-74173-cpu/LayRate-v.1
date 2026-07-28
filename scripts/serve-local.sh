#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_EXT_DIR="$PROJECT_ROOT/.dev/php-ext"

export PHP_INI_SCAN_DIR="/etc/php/conf.d:$PHP_EXT_DIR"
export LD_LIBRARY_PATH="$PHP_EXT_DIR${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"

exec php artisan serve "$@"
