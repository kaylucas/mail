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
   - Frontend: http://localhost:5173
   - Backend API: http://mail.loc (accessed via Traefik, proxied by Vite for API calls)

### How It Works

- **Backend**: Laravel runs in Docker, accessible at `http://mail.loc` via Traefik reverse proxy
- **Frontend**: Vue/Vite runs locally on host at `http://localhost:5173`
- **Proxying**: Vite proxies `/api`, `/auth`, and `/sanctum` requests to `http://mail.loc`
- **Authentication**: Sanctum handles cross-origin cookie-based authentication
- **Hot Reload**: Frontend changes reload instantly without Docker rebuild
- **Access Point**: Use Traefik exclusively (http://mail.loc) for consistent backend access

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

**"CORS errors in browser console":**
- Verify `CORS_ALLOWED_ORIGINS=http://localhost:5173` in .env
- Check config/cors.php configuration
- Ensure Docker containers are restarted after .env changes: `docker-compose restart app`

**"CSRF token mismatch":**
- Check `SANCTUM_STATEFUL_DOMAINS` includes `localhost:5173`
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
- The application continues to run locally at `http://mail.loc` with all existing configuration
- ngrok acts as a reverse proxy, forwarding external HTTPS requests to local HTTP
- No changes to `.env` configuration are needed (except `OFFICE365_REDIRECT_URI`)
- Only Azure App Registration needs the tunnel URL added as a redirect URI
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
   OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
   ```

6. **Restart application:**
   ```bash
   docker-compose restart app
   ```

**Important Notes:**
- Keep the ngrok tunnel running during development/testing
- The tunnel URL (aery.eu.ngrok.io) is only used by Microsoft for OAuth callbacks
- Users still access the application at http://localhost:5173 (frontend) or http://mail.loc (backend)
- All other `.env` settings remain unchanged (APP_URL, SANCTUM_STATEFUL_DOMAINS, CORS, SESSION)

**For detailed setup instructions, troubleshooting, and Azure configuration, see [NGROK_SETUP.md](NGROK_SETUP.md).**

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
