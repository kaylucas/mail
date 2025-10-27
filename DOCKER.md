# Docker Setup Guide

This document explains the Docker setup for the Laravel application, including production and development configurations.

## Quick Start

### Production Setup
```bash
# Build and start the application
docker-compose up -d

# The application will be available at http://localhost:80
```

### Development Setup with Hot-Reload
```bash
# Use the dev Dockerfile which includes Node/pnpm for hot-reload
docker-compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.dev.yml up -d

# The application will be available at:
# - http://localhost:80 (Laravel)
# - http://localhost:5173 (Vite dev server)
```

### With Traefik Reverse Proxy
```bash
# First, create the proxy network
docker network create proxy

# Then start with Traefik configuration
docker-compose -f docker-compose.yml -f docker-compose.traefik.yml up -d

# Access at http://mail.loc (configure DNS/hosts file accordingly)
```

## Architecture

### Multi-Stage Build (Production)

The production Dockerfile (`docker/app/Dockerfile`) uses a multi-stage build:

1. **Stage 1: Node Builder** (`node-builder`)
   - Uses Node 20 to install pnpm and dependencies
   - Builds frontend assets (Vite, Tailwind CSS)
   - Outputs to `public/build`

2. **Stage 2: PHP Apache** (final image)
   - Uses PHP 8.4 with Apache
   - Installs PHP extensions with proper JPEG/Freetype support
   - Copies built assets from stage 1
   - **Does NOT include Node/pnpm** (reduced image size and attack surface)
   - Runs composer install for production

### Development Setup

For local development with hot-reload, use `docker/app/Dockerfile.dev`:
- Includes Node 20 and pnpm
- Supports Vite hot-reload on port 5173
- Bind-mounts project directory for live code updates
- Auto-installs dependencies on container start

## Docker Compose Files

### `docker-compose.yml` (Base)
- MariaDB 10.11 service with health checks
- App service (production build by default)
- Basic port mappings
- No bind-mounts (production-ready)

### `docker-compose.override.yml` (Auto-loaded for Dev)
- Bind-mounts project directory for live editing
- Uses development entrypoint script
- Automatically loaded by docker-compose

### `docker-compose.dev.yml` (Optional - Hot Reload)
- Switches to `Dockerfile.dev` (includes Node)
- Exposes Vite dev server port (5173)
- Required for frontend hot-reload

### `docker-compose.traefik.yml` (Optional - Reverse Proxy)
- Adds Traefik labels for routing
- Connects to external `proxy` network
- Use when running behind Traefik

## PHP Extensions

All Dockerfiles install the following PHP extensions:

- **pdo_mysql** - Database connectivity
- **zip** - Zip archive support
- **gd** - Image manipulation (with JPEG and Freetype support)
- **bcmath** - Arbitrary precision math
- **mbstring** - Multibyte string support (requires oniguruma)
- **exif** - EXIF metadata reading
- **pcntl** - Process control
- **redis** - Redis support (via PECL)

### Extension Configuration

**GD** is configured with full JPEG and Freetype support:
```dockerfile
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
```

Required system libraries:
- `libjpeg62-turbo-dev`
- `libfreetype6-dev`

**mbstring** requires the oniguruma library:
- `libonig-dev`

All PECL extensions require build tools:
- `$PHPIZE_DEPS` (meta-package providing autoconf, dpkg-dev, file, g++, gcc, libc-dev, make, pkg-config, re2c)

## Build Optimization

### Production Image
- Multi-stage build removes Node/pnpm from final image
- Build dependencies (`$PHPIZE_DEPS`) are purged after extension compilation
- Node modules removed after asset build
- Only production Composer dependencies installed

### CI Image (`docker/ci/Dockerfile`)
- PHP 8.4 CLI (lighter than Apache)
- Includes Xdebug for code coverage
- Keeps build tools for CI pipeline
- Includes Node and pnpm for asset building

## Environment Variables

Required environment variables (set in `.env`):

```env
# Database
DB_CONNECTION=mysql
DB_HOST=mariadb  # Use service name from docker-compose
DB_PORT=3306
DB_DATABASE=mail
DB_USERNAME=root
DB_PASSWORD=password  # Change for production!

# Laravel
APP_KEY=base64:...
APP_ENV=local
APP_DEBUG=true
APP_PORT=80  # Host port mapping (change if 80 is in use)
```

**Important Notes:**
- `DB_HOST` should be `mariadb` (the service name) when running in Docker
- `DB_PASSWORD` must not be empty - MariaDB requires it
- MariaDB will use `root` user with the password from `DB_PASSWORD`
- Change `APP_PORT` if port 80 is already in use (e.g., 8080, 8888)

## Entrypoint Script

The development entrypoint (`docker-entrypoint-dev.sh`) automatically:

1. Installs Composer dependencies if `vendor/` is missing (bind-mount scenario)
2. If pnpm is available (dev image):
   - Installs Node dependencies if `node_modules/` is missing
   - Builds assets if `public/build/` is missing
3. Executes the main command (apache2-foreground)

## Apache Configuration

Apache is configured with:
- DocumentRoot: `/var/www/html/public`
- `AllowOverride All` for `.htaccess` support
- `mod_rewrite` enabled for Laravel routing

## Temporary Storage

The following directories use tmpfs for performance:
- `storage/framework/cache`
- `storage/framework/sessions`
- `storage/framework/views`
- `bootstrap/cache`

## GitHub Actions CI

The `.github/workflows/ci.yml` workflow:

1. **Test Job**
   - Builds CI image via `docker buildx`
   - Provisions MariaDB service
   - Runs composer and pnpm installs
   - Builds frontend assets
   - Runs database migrations
   - Executes test suite in parallel

2. **Lint Job**
   - Runs Laravel Pint for code style checking

## Troubleshooting

### Build fails with "pnpm-lock.yaml not found"
- Ensure `pnpm-lock.yaml` is committed to the repository
- It was previously excluded in `.dockerignore` but is now required

### Vendor directory is empty in container
- Use `docker-compose.override.yml` which bind-mounts the project
- The entrypoint script will auto-install dependencies

### Frontend assets not building
- For production: Assets are built during image build (multi-stage)
- For development: Use `docker-compose.dev.yml` for hot-reload
- Or run `pnpm run build` manually in the dev container

### Traefik routing not working
- Create the external network: `docker network create proxy`
- Use: `docker-compose -f docker-compose.yml -f docker-compose.traefik.yml up`

## PHP Version Compatibility

The project uses **PHP 8.4** which is compatible with:
- Laravel 12 (requires PHP ^8.2)
- All project dependencies (verified in `composer.json`)

To change PHP version, update:
- `docker/app/Dockerfile` (line 1 and stage 2)
- `docker/app/Dockerfile.dev` (line 1)
- `docker/ci/Dockerfile` (line 1)
