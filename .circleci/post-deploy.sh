#!/bin/bash

cd "$(dirname "${BASH_SOURCE[0]}")/../" || exit 1

set -euo pipefail

BRANCH="${1:-}"

php='php'

migrate_db() {
    echo "--- Running migrations"
    $php artisan migrate --force
}

main() {
    echo "--- Clearing cache"
    $php artisan optimize:clear

    echo "--- Restarting queue workers"
    $php artisan queue:restart || true
}

case "${2:-}" in
    migrate)
        migrate_db
        ;;
    *)
        main
        ;;
esac
