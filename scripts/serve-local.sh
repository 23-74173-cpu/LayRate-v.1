#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_EXT_DIR="$PROJECT_ROOT/.dev/php-ext"

export PHP_INI_SCAN_DIR="/etc/php/conf.d:$PROJECT_ROOT/.dev/php-conf.d"
export LD_LIBRARY_PATH="$PHP_EXT_DIR${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
export PHP_CLI_SERVER_WORKERS=4

exec php artisan serve --no-reload "$@"
