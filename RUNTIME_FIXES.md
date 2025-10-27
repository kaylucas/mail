# Runtime Fixes Applied

This document describes additional fixes applied during testing that weren't part of the original 9 verification comments.

## Issue 1: Missing Config Files in Dockerfile

**Error:**
```
failed to compute cache key: "/postcss.config.js": not found
```

**Root Cause:**
The project uses Tailwind CSS 4 via the `@tailwindcss/vite` plugin, which doesn't require separate `tailwind.config.js` or `postcss.config.js` files. Configuration is embedded in `vite.config.js`.

**Fix:**
Updated `docker/app/Dockerfile` stage 1 (node-builder):
```dockerfile
# Before
COPY vite.config.js tailwind.config.js postcss.config.js ./

# After
COPY vite.config.js ./
```

**Files Modified:**
- `docker/app/Dockerfile`

---

## Issue 2: Missing oniguruma Library for mbstring

**Error:**
```
configure: error: Package requirements (oniguruma) were not met:
Package 'oniguruma', required by 'virtual:world', not found
```

**Root Cause:**
The `mbstring` PHP extension requires the oniguruma library for multibyte regex support. The library wasn't installed in the system dependencies.

**Fix:**
Added `libonig-dev` to all three Dockerfiles:
```dockerfile
RUN apt-get update && apt-get install -y \
    ...
    libonig-dev \
    ...
```

**Files Modified:**
- `docker/app/Dockerfile`
- `docker/app/Dockerfile.dev`
- `docker/ci/Dockerfile`

**Impact:**
All Dockerfiles now include the complete set of dependencies for all PHP extensions.

---

## Issue 3: MariaDB Environment Variable Configuration

**Error:**
```
ERROR: Database is uninitialized and password option is not specified
You need to specify one of MARIADB_ROOT_PASSWORD, ...
```

**Root Cause:**
The MariaDB Docker image expects specific environment variable names:
- `MYSQL_ROOT_PASSWORD` - for the root user password
- `MYSQL_DATABASE` - for the database name
- `MYSQL_USER` / `MYSQL_PASSWORD` - for creating a custom (non-root) user

The original configuration tried to create a user named "root" using `MYSQL_USER`, which conflicts with the built-in root user.

**Fix:**
Updated `docker-compose.yml` to use only root user:
```yaml
# Before
environment:
  MYSQL_DATABASE: ${DB_DATABASE}
  MYSQL_USER: ${DB_USERNAME}
  MYSQL_PASSWORD: ${DB_PASSWORD}
  MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}

# After
environment:
  MYSQL_DATABASE: ${DB_DATABASE:-mail}
  MYSQL_ROOT_PASSWORD: ${DB_PASSWORD:-password}
```

**Files Modified:**
- `docker-compose.yml`
- `.env` - Set `DB_PASSWORD=password` (was empty)

**Configuration:**
The application now connects as `root` user to MariaDB. For production, consider creating a dedicated user with limited privileges.

---

## Issue 4: Empty Database Password in .env

**Error:**
MariaDB failed to initialize due to missing password.

**Root Cause:**
The `.env` file had `DB_PASSWORD=` (empty value).

**Fix:**
Set a default password in `.env`:
```env
DB_PASSWORD=password
```

**Files Modified:**
- `.env`

**Security Note:**
This is suitable for local development only. Production deployments should use strong, unique passwords.

---

## Issue 5: Port 80 Already Allocated

**Error:**
```
Bind for 0.0.0.0:80 failed: port is already allocated
```

**Root Cause:**
Port 80 (HTTP) was already in use by another service on the host machine.

**Fix:**
Used the `APP_PORT` environment variable to run on a different port:
```bash
APP_PORT=8888 docker-compose up -d
```

**Documentation:**
Updated `DOCKER.md` to mention:
- Default port is 80
- Can be changed via `APP_PORT` environment variable
- Common alternatives: 8080, 8888

**No Files Modified:**
The functionality was already supported via `${APP_PORT:-80}` in `docker-compose.yml`.

---

## Verification Results

After all fixes, the Docker setup is fully functional:

### ✅ Production Build
```bash
docker-compose build
# ✓ Build completes successfully
# ✓ Multi-stage build works (Node in stage 1 only)
# ✓ All PHP extensions compile correctly
# ✓ Frontend assets built and copied to final image
```

### ✅ Container Startup
```bash
docker-compose up -d
# ✓ MariaDB starts and passes health check
# ✓ App container starts successfully
# ✓ Apache runs and serves requests
```

### ✅ Multi-Stage Build Verification
```bash
docker-compose exec app which node
# ✓ Node not found (as expected - not in production image)

docker-compose exec app ls public/build/
# ✓ Built assets present (assets/ and manifest.json)
```

### ✅ Container Status
```
NAME             STATUS
mail-mariadb-1   Up (healthy)
mail-app-1       Up
```

---

## Summary of All Changes

### Original 9 Verification Comments: ✅ All Implemented
1. Fixed volume bind-mounts
2. Fixed pnpm-lock.yaml exclusion
3. Added $PHPIZE_DEPS
4. Added GD extension dependencies
5. Populated CI workflow
6. Removed Traefik from defaults
7. Fixed Apache config sed
8. Multi-stage build implementation
9. Verified PHP 8.4 compatibility

### Additional Runtime Fixes: ✅ All Applied
10. Removed non-existent config files from Dockerfile
11. Added libonig-dev for mbstring
12. Fixed MariaDB environment variables
13. Set database password in .env
14. Documented port configuration

---

## Testing Checklist

- [x] Docker build completes without errors
- [x] Node/pnpm not present in production image
- [x] Built assets present in public/build
- [x] MariaDB starts and is healthy
- [x] App container starts successfully
- [x] Apache serves requests
- [x] All PHP extensions loaded
- [x] Database connection works

---

## Files Modified in Runtime Fixes

1. `docker/app/Dockerfile` - Removed postcss.config.js/tailwind.config.js, added libonig-dev
2. `docker/app/Dockerfile.dev` - Added libonig-dev
3. `docker/ci/Dockerfile` - Added libonig-dev
4. `docker-compose.yml` - Fixed MariaDB environment variables
5. `.env` - Set DB_PASSWORD
6. `DOCKER.md` - Added environment variable documentation and notes

---

## Recommendations

1. **For Production:**
   - Change `DB_PASSWORD` to a strong, unique password
   - Consider creating a dedicated database user (not root)
   - Use `APP_ENV=production` and `APP_DEBUG=false`
   - Enable HTTPS via reverse proxy

2. **For Development:**
   - Use `docker-compose.dev.yml` for hot-reload
   - Monitor port conflicts (80, 8080, 8888)
   - Keep `.env` out of version control

3. **For CI/CD:**
   - GitHub Actions workflow is ready to use
   - Ensure secrets are configured for production deployments
