#!/bin/bash

# Script to rebuild Docker containers after code changes

echo "Rebuilding Docker containers..."

# Stop all containers
docker-compose down

# Rebuild the app image with no cache to ensure fresh code
docker-compose build --no-cache app queue

# Start containers
docker-compose up -d

echo "Docker containers rebuilt and started!"
echo ""
echo "To view logs, run: docker-compose logs -f"
echo "To access the application: http://mail.loc"
echo "To run artisan commands: docker-compose exec app php artisan <command>"
