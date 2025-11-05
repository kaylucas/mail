# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application with a **separated Vue 3 SPA frontend** that implements Microsoft Office 365 OAuth authentication and email integration. The frontend runs independently from the Laravel backend and communicates via API. The application uses Laravel Sanctum for stateful SPA authentication with session-based CSRF protection.

**Architecture:**
- **Backend**: Laravel 12 API at `http://mail.loc` (Docker + Traefik)
- **Frontend**: Vue 3 SPA at `http://localhost:5173` (standalone Vite dev server)
- **Frontend Location**: `/frontend` directory (separate from Laravel)

**Development Domain:** The backend is developed and tested at `http://mail.loc` via Docker + Traefik. The frontend runs on `http://localhost:5173` and proxies API requests to the backend.

## Development Commands

### Initial Setup

**Backend:**
```bash
composer install           # Install Laravel dependencies
php artisan key:generate   # Generate application key
php artisan migrate        # Run database migrations
```

**Frontend:**
```bash
cd frontend
pnpm install              # Install frontend dependencies
```

### Running the Development Servers

**Backend (Laravel API):**
```bash
# Option 1: Use Docker (recommended)
# The backend runs at http://mail.loc via Traefik
docker-compose up

# Option 2: Run services individually
php artisan serve              # Start Laravel server
php artisan queue:listen       # Process queue jobs
php artisan pail               # Tail application logs
```

**Frontend (Vue SPA):**
```bash
cd frontend
pnpm dev                       # Start Vite dev server at localhost:5173
```

Access the application at `http://localhost:5173` (frontend proxies API requests to `http://mail.loc`)

### Building Frontend for Production
```bash
cd frontend
pnpm build                     # Production build → frontend/dist/
pnpm preview                   # Preview production build
```

### Testing
```bash
composer test          # Run full test suite (uses Pest)
php artisan test       # Alternative way to run tests
```

### Code Quality
```bash
vendor/bin/pint        # Format code using Laravel Pint
```

### Database
```bash
php artisan migrate              # Run migrations
php artisan migrate:fresh --seed # Fresh database with seeds
php artisan migrate:rollback     # Rollback last migration
```

## Architecture

### Authentication Flow

**Microsoft SSO Authentication (Primary):**
1. User clicks "Sign in with Microsoft" at `http://localhost:5173` → frontend redirects to backend `/auth/microsoft` (web route)
2. `MicrosoftAuthController::redirect()` generates OAuth state, stores it in **cache**, redirects to Microsoft
3. Microsoft redirects back to ngrok tunnel → `https://aery.eu.ngrok.io/auth/microsoft/callback` (web route)
4. `MicrosoftAuthController::callback()` validates state from cache, exchanges code for tokens, creates/updates User and Office365Connection, logs user in
5. **Session Transfer:** Callback creates temporary token in cache, redirects to `http://mail.loc/auth/session?token=xxx`
6. `MicrosoftAuthController::establishSession()` validates token, logs in user on local domain, regenerates session
7. Final redirect to `http://localhost:5173/#/dashboard` (frontend)
8. Vue SPA uses Sanctum session cookies for authenticated API requests (cross-origin with credentials)

**Critical Security Details:**
- OAuth state is stored in cache (10-minute TTL) with IP validation
- State is single-use and deleted immediately after validation
- Session transfer uses temporary tokens (5-minute TTL) to bridge ngrok → mail.loc
- CSRF cookie must be initialized via `axios.get('/sanctum/csrf-cookie')` before any POST requests
- `SANCTUM_STATEFUL_DOMAINS` must include `localhost:5173` and `mail.loc` for local development
- `CORS_ALLOWED_ORIGINS` must include `http://localhost:5173` for frontend access
- Session is regenerated on the `mail.loc` domain after OAuth callback
- All API requests are cross-origin (frontend on localhost:5173, backend on mail.loc)

### API Authentication

The application uses **stateful Sanctum** (not token-based). Key configuration:
- `bootstrap/app.php`: `$middleware->statefulApi()` enables session-based API auth
- `axios.defaults.withCredentials = true` sends cookies with requests (configured in `frontend/src/axios.js`)
- `frontend/src/axios.js`: CSRF cookie initialized on app load
- Protected routes use `auth:sanctum` middleware in `routes/api.php`
- Cross-origin requests are enabled via CORS configuration (`config/cors.php`)

### Configuration

**Office365 Credentials:**
- All Office365 config is centralized in `config/services.php` under `office365` key
- Controllers use `config('services.office365.*')` - NEVER call `env()` directly
- Values map to: `OFFICE365_CLIENT_ID`, `OFFICE365_CLIENT_SECRET`, `OFFICE365_REDIRECT_URI`, `OFFICE365_TENANT_ID`, `OFFICE365_SCOPES`
- Clients cannot override `client_id`, `client_secret`, or `redirect_uri` via API (prohibited in `StoreOffice365ConnectionRequest`)

### Key Models & Relationships

**User Model:**
- Has one `Office365Connection` relationship
- Fields: `microsoft_id`, `name`, `email`
- Authenticated via Microsoft OAuth (no password field used for SSO users)

**Office365Connection Model:**
- Belongs to `User`
- Stores encrypted OAuth tokens: `access_token`, `refresh_token`, `client_secret`
- Stores OAuth config: `client_id`, `tenant_id`, `redirect_uri`, `scopes` (array)
- Method: `isTokenExpired()` checks if `token_expires_at` is past
- Tokens can be refreshed via `Office365Service::refreshAccessToken()`

### Services

**Office365Service:**
- `generateAuthorizationUrl(connection, state)`: Builds Microsoft OAuth URL
- `exchangeCodeForTokens(connection, code)`: Exchanges auth code for access/refresh tokens
- `refreshAccessToken(connection)`: Refreshes expired access token using refresh token
- `getUserProfile(accessToken, ...)`: Fetches user profile from Microsoft Graph API (validates token is access token, not ID token)
- `getGraphClient(connection)`: Returns configured Microsoft Graph SDK client with proper token context and cache

All methods use config values from `config/services.php`, with optional overrides from connection model.

### Controllers

**MicrosoftAuthController (Web Routes):**
- `redirect()`: Initiates OAuth flow, stores state in cache
- `callback()`: Handles OAuth callback (on ngrok), validates state, exchanges code for tokens, creates/updates user, generates session transfer token
- `establishSession()`: Establishes session on mail.loc domain using transfer token, logs in user, redirects to dashboard
- `logout()`: Logs out user, invalidates session (API endpoint)
- `user()`: Returns authenticated user with `office365Connection` relationship (API endpoint)

**Office365ConnectionController (API Routes, `auth:sanctum` required):**
- `store()`: Save/update Office365 connection config (credentials forced from config, cannot be overridden by client)
- `show()`: Get current connection status and token expiry
- `destroy()`: Delete Office365 connection
- `getAuthUrl()`: **[DEPRECATED]** Generate OAuth URL (use `/auth/microsoft` web route instead)

### Frontend Structure

**Location:** `/frontend` directory (separated from Laravel backend)

**Framework:** Vue 3 with Vue Router (hash mode), Tailwind CSS 4, Headless UI
**Entry:** `frontend/src/main.js` → mounts `App.vue`
**HTML Template:** `frontend/public/index.html`
**Axios Config:** `frontend/src/axios.js` → configures API requests and CSRF cookie initialization
**Router:** `frontend/src/router/index.js`
- Navigation guards check authentication via `/api/user` endpoint
- `meta: { requiresAuth: true }` for protected routes
- `meta: { guest: true }` for public routes (auto-redirects if authenticated)

**Pages:**
- `frontend/src/pages/Login.vue`: Microsoft SSO login button
- `frontend/src/pages/Dashboard.vue`: Main authenticated view

**Key Files:**
- `frontend/vite.config.js`: Vite configuration with proxy for backend API
- `frontend/package.json`: Frontend dependencies (separate from Laravel)
- `frontend/.env`: Frontend environment variables (VITE_API_URL)
- `frontend/README.md`: Frontend-specific documentation

### Routes

**Web Routes (`routes/web.php`):**
- `GET /auth/microsoft` → MicrosoftAuthController::redirect
- `GET /auth/microsoft/callback` → MicrosoftAuthController::callback (receives callback from ngrok)
- `GET /auth/session` → MicrosoftAuthController::establishSession (session transfer endpoint)
- Note: Frontend runs separately, no catch-all route needed

**API Routes (`routes/api.php`, protected by `auth:sanctum`):**
- `POST /api/logout` → MicrosoftAuthController::logout
- `GET /api/user` → MicrosoftAuthController::user
- `POST /api/office365/connections` → Office365ConnectionController::store
- `GET /api/office365/connections` → Office365ConnectionController::show
- `DELETE /api/office365/connections` → Office365ConnectionController::destroy
- `GET /api/office365/auth/url` → **[DEPRECATED]** Office365ConnectionController::getAuthUrl

### Database Schema

**users table:**
- `microsoft_id` (nullable, unique): Microsoft Graph user ID
- `name`, `email`
- No password field used for SSO users

**office365_connections table:**
- `user_id` (foreign key to users)
- `tenant_id`, `client_id`, `client_secret` (encrypted)
- `redirect_uri`
- `access_token` (encrypted), `refresh_token` (encrypted), `token_expires_at`
- `scopes` (JSON array)
- `is_active` (boolean)
- `last_sync_at` (nullable timestamp)

**Other tables:**
- Standard Laravel cache, jobs, sessions, personal_access_tokens (Sanctum)

## Common Issues & Solutions

### 419 CSRF Token Mismatch
- Ensure `axios.get('/sanctum/csrf-cookie')` runs on app load (configured in `frontend/src/axios.js`)
- Check `SANCTUM_STATEFUL_DOMAINS` includes `localhost:5173` and `mail.loc`
- Check `CORS_ALLOWED_ORIGINS` includes `http://localhost:5173`
- Verify `axios.defaults.withCredentials = true` is set (in `frontend/src/axios.js`)

### OAuth State Validation Failed
- State is stored in session, not cache - ensure sessions are working
- State is single-use and expires with session
- Do not share state between users (session-isolated)

### Token Refresh
- Check `Office365Connection::isTokenExpired()` before using tokens
- Call `Office365Service::refreshAccessToken($connection)` to refresh
- Update connection model with new tokens after refresh

## Package Manager

This project uses **pnpm** for both backend and frontend (specified in `package.json` and `frontend/package.json`). Use `pnpm install` and `pnpm run dev` instead of npm where possible.

## Project Structure

```
/
├── app/                    # Laravel application code
├── config/                 # Laravel configuration
├── database/               # Migrations, seeders, factories
├── frontend/               # 🆕 Separated Vue 3 SPA frontend
│   ├── public/            # Static assets
│   ├── src/               # Vue source code
│   │   ├── pages/        # Page components
│   │   ├── router/       # Vue Router config
│   │   ├── App.vue       # Root component
│   │   ├── axios.js      # Axios config
│   │   ├── main.js       # Entry point
│   │   └── styles.css    # Global styles
│   ├── package.json       # Frontend dependencies
│   ├── vite.config.js     # Vite config
│   └── README.md          # Frontend docs
├── resources/              # Laravel resources (views, original js removed)
├── routes/                 # Laravel routes (API and auth endpoints)
├── tests/                  # Laravel tests
└── composer.json           # Backend dependencies
```
