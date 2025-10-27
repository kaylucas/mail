# Docker Quick Start Guide

## Prerequisites

- Docker and Docker Compose installed
- Ports available: 3306 (MySQL), 80 or 8080 (HTTP)

## First Time Setup

1. **Ensure .env is configured:**
   ```bash
   # Check that DB_PASSWORD is set (not empty)
   grep DB_PASSWORD .env

   # If empty, set a password:
   sed -i 's/^DB_PASSWORD=$/DB_PASSWORD=password/' .env
   ```

2. **Build and start containers:**
   ```bash
   # If port 80 is available
   docker-compose up -d

   # If port 80 is in use, use a different port
   APP_PORT=8080 docker-compose up -d
   ```

3. **Check status:**
   ```bash
   docker-compose ps

   # Both containers should show "Up" or "Up (healthy)"
   ```

4. **View logs (if needed):**
   ```bash
   docker-compose logs -f
   ```

5. **Access the application:**
   - If using default port: http://localhost
   - If using custom port: http://localhost:8080 (or your APP_PORT)

## Common Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart containers
docker-compose restart

# View logs
docker-compose logs -f app
docker-compose logs -f mariadb

# Execute commands in container
docker-compose exec app php artisan migrate
docker-compose exec app php artisan tinker

# Rebuild images
docker-compose build --no-cache

# Clean up everything
docker-compose down -v  # WARNING: Deletes database!
```

## Development Modes

### Standard Development (No Hot-Reload)
```bash
docker-compose up -d
# Code changes require manual asset rebuild
```

### With Hot-Reload (Vite Dev Server)
```bash
docker-compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.dev.yml up -d
# Access app: http://localhost
# Vite server: http://localhost:5173
```

### With Traefik
```bash
docker network create proxy  # First time only
docker-compose -f docker-compose.yml -f docker-compose.traefik.yml up -d
# Access via: http://mail.loc (configure hosts file)
```

## Troubleshooting

### Port Already in Use
```bash
# Error: Bind for 0.0.0.0:80 failed: port is already allocated
# Solution: Use different port
APP_PORT=8080 docker-compose up -d
```

### Database Connection Errors
```bash
# Check MariaDB is healthy
docker-compose ps mariadb
# Should show "Up (healthy)"

# Check .env has correct DB_HOST
grep DB_HOST .env
# Should be: DB_HOST=mariadb (not 127.0.0.1)

# Check password is set
grep DB_PASSWORD .env
# Should not be empty
```

### Node Not Found (Expected!)
```bash
# This is correct - Node is NOT in production image
docker-compose exec app which node
# Returns error (this is good!)

# For development with Node, use:
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

### Reset Database
```bash
docker-compose down -v  # Deletes volumes
docker-compose up -d    # Fresh database
```

## Running Migrations

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Fresh migrations with seed
docker-compose exec app php artisan migrate:fresh --seed
```

## Running Tests

```bash
docker-compose exec app php artisan test
```

## Accessing Container Shell

```bash
# PHP/Apache container
docker-compose exec app bash

# MariaDB container
docker-compose exec mariadb bash

# Connect to MySQL
docker-compose exec mariadb mysql -u root -p
# Password: value from DB_PASSWORD in .env
```

## Production Deployment

1. Update `.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   DB_PASSWORD=<strong-unique-password>
   ```

2. Build production image:
   ```bash
   docker-compose build
   ```

3. Start containers:
   ```bash
   docker-compose up -d
   ```

4. Run migrations:
   ```bash
   docker-compose exec app php artisan migrate --force
   ```

## Verification

After starting, verify everything works:

```bash
# 1. Check containers are running
docker-compose ps

# 2. Check app logs for errors
docker-compose logs app | grep -i error

# 3. Verify Node is NOT in production image
docker-compose exec app which node
# Should fail (this is correct!)

# 4. Verify assets are built
docker-compose exec app ls -l public/build/

# 5. Test HTTP endpoint
curl http://localhost  # or http://localhost:8080
```

## Need Help?

- See `DOCKER.md` for detailed documentation
- See `RUNTIME_FIXES.md` for common issues and fixes
- See `IMPLEMENTATION_SUMMARY.md` for all changes made
