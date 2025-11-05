<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Development Setup

This application uses a hybrid development approach: Laravel backend runs in Docker (via Traefik), while the Vue/Vite frontend runs locally on the host machine for faster iteration.

### Prerequisites

1. **Docker Desktop** with Traefik reverse proxy configured
2. **Node.js 20+** and **pnpm** installed on host machine
3. **Hosts file entry**: Add `127.0.0.1 mail.loc` to `/etc/hosts`
4. **Traefik network**: Create with `docker network create proxy`

### Quick Start

1. **Copy environment file:**
```bash
cp .env.example .env
```

2. **Update .env:**
   - Set `APP_URL=http://mail.loc`
   - Configure Office365 credentials (OFFICE365_CLIENT_ID, OFFICE365_CLIENT_SECRET)
   - Verify `SANCTUM_STATEFUL_DOMAINS=localhost:5173,mail.loc,localhost,127.0.0.1`
   - Verify `CORS_ALLOWED_ORIGINS=http://localhost:5173`

3. **Start Docker backend:**
```bash
docker-compose up -d
```

4. **Run migrations:**
```bash
docker-compose exec app php artisan migrate
```

5. **Install frontend dependencies:**
```bash
pnpm install
```

6. **Start Vite dev server:**
```bash
pnpm run dev
```

7. **Access the application:**
   - **Application**: http://mail.loc (ALWAYS use this URL)
   - **Vite dev server**: http://localhost:5173 (for hot-reload only, do NOT access directly)
   - **Backend API**: http://mail.loc/api (same domain as frontend)

### How It Works

- **Backend**: Laravel runs in Docker, accessible at `http://mail.loc` via Traefik reverse proxy
- **Frontend**: Vue/Vite assets served by Laravel at `http://mail.loc`
- **Development**: Vite dev server at `localhost:5173` provides hot-reload (do not access directly)
- **Authentication**: Sanctum handles same-domain cookie-based authentication
- **Hot Reload**: Frontend changes reload instantly via Vite HMR
- **Access**: Always use `http://mail.loc`, never `localhost:5173`

**Important**: The Vite dev server at `localhost:5173` is for development hot-reload only. Always access the application at `http://mail.loc` to ensure proper session handling and authentication. Accessing via `localhost:5173` creates a different session domain and causes authentication issues.

### Building Images

**Using docker-compose:**
```bash
docker-compose build
```

**Using docker bake:**
```bash
docker buildx bake              # Build all images
docker buildx bake app          # Build app image only
docker buildx bake ci           # Build CI image only
docker buildx bake --push       # Build and push to registry
```

### Database

- **MariaDB 10.11** is used instead of MySQL
- Data persists in `mariadb_data` volume
- Access from host: `localhost:3306` (or custom `FORWARD_DB_PORT`)

### Traefik Configuration (Optional)

1. Create external proxy network:
```bash
docker network create proxy
```

2. Ensure Traefik is running and connected to proxy network

3. Add `mail.loc` to `/etc/hosts`:
```bash
echo "127.0.0.1 mail.loc" | sudo tee -a /etc/hosts
```

4. Access app at: http://mail.loc

**To disable Traefik:** Comment out `proxy` network and labels in `docker-compose.yml`

### Development Workflow

- **Frontend changes**: Edit files in `resources/js/` and `resources/css/` - Vite hot-reloads automatically
- **Backend changes**: Edit PHP files - changes reflect immediately (no rebuild needed)
- **Database changes**: Run migrations with `docker-compose exec app php artisan migrate`
- **Clear cache**: `docker-compose exec app php artisan cache:clear`
- **View logs**: `docker-compose logs -f app`

### Production Build

1. **Build frontend assets:**
```bash
pnpm run build
```

2. **Rebuild Docker image:**
```bash
docker-compose build app
```

3. **Deploy with production environment variables:**
```bash
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
CORS_ALLOWED_ORIGINS=https://your-production-domain.com
```

**Note:** This setup is optimized for local development with fast frontend iteration.

### Troubleshooting

**"Cannot access mail.loc":**
- Check Traefik is running and `/etc/hosts` has the entry `127.0.0.1 mail.loc`
- Verify Traefik network exists: `docker network ls | grep proxy`

#### Error: "Unauthenticated" after successful Microsoft OAuth login

**Symptoms**:
- OAuth authentication completes successfully
- User is redirected to dashboard
- API calls return `{"message":"Unauthenticated."}`
- Browser console shows 401 errors on `/api/user` endpoint

**Root Cause 1: Accessing via wrong URL**
You're accessing the application at `http://localhost:5173` instead of `http://mail.loc`. This creates different sessions with different cookie domains.

**Solution**:
1. Always access the application at `http://mail.loc`
2. Close all browser tabs at `localhost:5173`
3. Clear browser cookies
4. Access `http://mail.loc` and try again

**Root Cause 2: Secure cookie over HTTP**
The session cookie is marked with the `Secure` flag (`SESSION_SECURE_COOKIE=true`), which prevents browsers from sending it over HTTP connections.

**Solution**:
1. Update your `.env` file:
   ```env
   SESSION_SECURE_COOKIE=false
   ```

2. Restart the application:
   ```bash
   docker-compose restart app
   ```

3. Clear browser cookies and cache (or use incognito mode)

4. Access `http://mail.loc` and try the OAuth flow again

**Verification**:
- Open browser DevTools → Application → Cookies
- After login, verify the session cookie exists for domain `mail.loc`
- Verify it does NOT have the `Secure` flag
- Make an API request and verify the cookie is sent in the request headers

**Why this happens**:
Browsers enforce strict security policies: cookies marked `Secure` are only transmitted over HTTPS. The application runs at `http://mail.loc` (HTTP) during local development. Even though OAuth happens over HTTPS (ngrok), the actual application access is HTTP, so secure cookies are blocked.

**Production note**: In production with full HTTPS, set `SESSION_SECURE_COOKIE=true` for security.

**"CORS errors in browser console":**
- Verify `CORS_ALLOWED_ORIGINS=http://localhost:5173,http://mail.loc` in .env
- Note: CORS is primarily for the Vite dev server; the app should be accessed at `http://mail.loc`
- Check config/cors.php configuration
- Ensure Docker containers are restarted after .env changes: `docker-compose restart app`

**"CSRF token mismatch":**
- Check `SANCTUM_STATEFUL_DOMAINS` includes `mail.loc` (primary) and `localhost:5173` (for Vite dev server)
- Always access the app at `http://mail.loc` for proper session handling
- Verify session configuration in config/session.php

**"Authentication not working":**
- Cross-origin cookies may be blocked. Check browser console
- Ensure `SESSION_SAME_SITE=none` and `supports_credentials=true` in CORS config
- Verify `axios.defaults.withCredentials = true` in resources/js/bootstrap.js

**"Vite proxy errors":**
- Ensure backend is running at `http://mail.loc` and accessible from host
- Test with: `curl http://mail.loc`

**"Assets not loading":**
- Run `pnpm run build` to generate production assets
- Or ensure `pnpm run dev` is running for development

**Permission issues:**
```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**Database connection:**
- Ensure mariadb service is healthy: `docker-compose ps`

### Using ngrok Tunnel for Microsoft OAuth

Microsoft OAuth requires a publicly accessible redirect URI. Since local URLs like `http://mail.loc` and `http://localhost` are not accessible from the internet, we use ngrok to create a secure tunnel from a public URL to your local application.

**Architecture:**
```
Microsoft OAuth Servers (Internet)
         ↓
    ngrok tunnel (https://aery.eu.ngrok.io)
         ↓
    Local Application (http://mail.loc)
         ↑
    Frontend Dev Server (http://localhost:5173)
```

**Key Points:**
- ngrok acts as a reverse proxy, forwarding external HTTPS requests to local HTTP
- When using ngrok for OAuth, you must update `.env` with the ngrok URL and enable secure cookies
- Azure App Registration needs the tunnel URL added as a redirect URI
- Trusted proxy configuration is already in place to maintain sessions through ngrok

**Quick Setup:**

1. **Install and authenticate ngrok:**
   ```bash
   brew install ngrok  # macOS
   ngrok authtoken YOUR_AUTH_TOKEN
   ```

2. **Start the tunnel:**
   ```bash
   ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
   ```

3. **Verify tunnel is active:**
   - Open https://aery.eu.ngrok.io in a browser
   - You should see the same application as http://mail.loc

4. **Configure Azure App Registration:**
   - Navigate to Azure Portal → App Registrations
   - Add redirect URI: `https://aery.eu.ngrok.io/auth/microsoft/callback`
   - Keep existing local URIs for reference

5. **Update .env:**
   ```env
   APP_URL=https://aery.eu.ngrok.io
   SESSION_SECURE_COOKIE=true
   SANCTUM_STATEFUL_DOMAINS=localhost:5173,mail.loc,localhost,127.0.0.1,aery.eu.ngrok.io
   CORS_ALLOWED_ORIGINS=http://localhost:5173,https://aery.eu.ngrok.io
   OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
   OFFICE365_SCOPES="openid,profile,email,offline_access,User.Read,Mail.Read"
   ```

6. **Restart application:**
   ```bash
   docker-compose restart app
   ```

**Important Notes:**
- Keep the ngrok tunnel running during development/testing
- Setting `APP_URL` to the ngrok HTTPS URL is required for session cookies to work during OAuth
- The `User.Read` scope is required for accessing user profile via Microsoft Graph API
- Users can access the application at http://localhost:5173 (frontend) or https://aery.eu.ngrok.io (via tunnel)

**For detailed setup instructions, troubleshooting, and Azure configuration, see [NGROK_SETUP.md](NGROK_SETUP.md).**

## Troubleshooting

### Microsoft OAuth Errors

#### Error: Class "Microsoft\Graph\Graph" not found

**Cause:** The Microsoft Graph SDK dependencies are not installed.

**Solution:**
```bash
docker-compose exec app composer install
```

This installs the `microsoft/microsoft-graph` package and its dependencies.

#### Error: Invalid state parameter

**Cause:** Session cookies are not being maintained through the OAuth callback flow when using ngrok.

**Solution:**
1. Ensure your `.env` file has:
   ```env
   APP_URL=https://aery.eu.ngrok.io
   SESSION_SECURE_COOKIE=true
   SANCTUM_STATEFUL_DOMAINS=localhost:5173,mail.loc,localhost,127.0.0.1,aery.eu.ngrok.io
   CORS_ALLOWED_ORIGINS=http://localhost:5173,https://aery.eu.ngrok.io
   ```

2. Restart the application:
   ```bash
   docker-compose restart app
   ```

3. Clear caches:
   ```bash
   docker-compose exec app php artisan config:clear
   docker-compose exec app php artisan cache:clear
   ```

4. Ensure ngrok is running with host header rewriting:
   ```bash
   ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
   ```

**Why this happens:**
- When `APP_URL` is set to HTTP but requests come through HTTPS (ngrok), browsers won't send session cookies
- This creates a new session on the OAuth callback, losing the state parameter
- Setting `APP_URL` to the ngrok HTTPS URL and enabling secure cookies fixes this

For detailed ngrok setup instructions, see `NGROK_SETUP.md`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
