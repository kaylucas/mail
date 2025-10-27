#!/bin/bash
set -e

# Install dependencies if vendor is missing (due to bind-mount)
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing PHP dependencies..."
    composer install --no-interaction
fi

# If pnpm is available (dev image), install node dependencies and build assets
if command -v pnpm &> /dev/null; then
    if [ ! -d "node_modules" ]; then
        echo "Installing Node dependencies..."
        pnpm install --frozen-lockfile
    fi

    if [ ! -d "public/build" ]; then
        echo "Building frontend assets..."
        pnpm run build
    fi
fi

# Execute the main command (apache2-foreground)
exec "$@"
