# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application with a Vue 3 SPA frontend that implements Microsoft Office 365 OAuth authentication and email integration. The application uses Laravel Sanctum for stateful SPA authentication with session-based CSRF protection.

## Development Commands

### Initial Setup
```bash
composer setup  # Installs dependencies, generates app key, runs migrations, builds frontend
```

### Running the Development Server
```bash
composer dev  # Runs server, queue, logs (pail), and Vite concurrently on localhost:8000
```

Or run services individually:
```bash
php artisan serve              # Start Laravel server on localhost:8000
php artisan queue:listen       # Process queue jobs
php artisan pail               # Tail application logs
npm run dev                    # Start Vite dev server (use pnpm if available)
```

### Building Frontend
```bash
npm run build   # Production build
npm run dev     # Development mode with hot reload
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
1. User clicks "Sign in with Microsoft" → `/auth/microsoft` (web route)
2. `MicrosoftAuthController::redirect()` generates OAuth state, stores it in **session** (not cache), redirects to Microsoft
3. Microsoft redirects back to `/auth/microsoft/callback` (web route)
4. `MicrosoftAuthController::callback()` validates state from session, exchanges code for tokens, creates/updates User and Office365Connection, logs user in, regenerates session
5. Vue SPA uses Sanctum session cookies for authenticated API requests

**Critical Security Details:**
- OAuth state is stored per-session in `$request->session()`, NOT in global cache
- State is validated and immediately forgotten after use
- CSRF cookie must be initialized via `axios.get('/sanctum/csrf-cookie')` before any POST requests
- `SANCTUM_STATEFUL_DOMAINS` must include `localhost:8000` for local development
- Session is regenerated only AFTER successful login

### API Authentication

The application uses **stateful Sanctum** (not token-based). Key configuration:
- `bootstrap/app.php`: `$middleware->statefulApi()` enables session-based API auth
- `axios.defaults.withCredentials = true` sends cookies with requests
- `resources/js/app.js`: CSRF cookie initialized before Vue app mounts
- Protected routes use `auth:sanctum` middleware in `routes/api.php`

### Configuration

**Office365 Credentials:**
- All Office365 config is centralized in `config/services.php` under `office365` key
- Controllers use `config('services.office365.*')` - NEVER call `env()` directly
- Values map to: `OFFICE365_CLIENT_ID`, `OFFICE365_CLIENT_SECRET`, `OFFICE365_REDIRECT_URI`, `OFFICE365_TENANT_ID`, `OFFICE365_SCOPES`
- Clients cannot override `client_id`, `client_secret`, or `redirect_uri` via API (prohibited in `StoreOffice365ConnectionRequest`)

### Key Models & Relationships

**User Model:**
- Has one `Office365Connection` relationship
- Fields: `microsoft_id`, `name`, `email`, `avatar`
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
- `getUserProfile(accessToken)`: Fetches user profile from Microsoft Graph API
- `getUserPhoto(accessToken)`: Fetches user photo (returns base64 data URL or null)
- `getGraphClient(connection)`: Returns configured Microsoft Graph SDK client

All methods use config values from `config/services.php`, with optional overrides from connection model.

### Controllers

**MicrosoftAuthController (Web Routes):**
- `redirect()`: Initiates OAuth flow, stores state in session
- `callback()`: Handles OAuth callback, creates/updates user, logs in
- `logout()`: Logs out user, invalidates session
- `user()`: Returns authenticated user with `office365Connection` relationship (API endpoint)

**Office365ConnectionController (API Routes, `auth:sanctum` required):**
- `store()`: Save/update Office365 connection config (credentials forced from config, cannot be overridden by client)
- `show()`: Get current connection status and token expiry
- `destroy()`: Delete Office365 connection
- `getAuthUrl()`: **[DEPRECATED]** Generate OAuth URL (use `/auth/microsoft` web route instead)

### Frontend Structure

**Framework:** Vue 3 with Vue Router (hash mode), Tailwind CSS 4, Headless UI
**Entry:** `resources/js/app.js` → mounts `App.vue` after CSRF cookie initialization
**Router:** `resources/js/router/index.js`
- Navigation guards check authentication via `/api/user` endpoint
- `meta: { requiresAuth: true }` for protected routes
- `meta: { guest: true }` for public routes (auto-redirects if authenticated)

**Pages:**
- `Login.vue`: Microsoft SSO login button
- `Dashboard.vue`: Main authenticated view

### Routes

**Web Routes (`routes/web.php`):**
- `GET /auth/microsoft` → MicrosoftAuthController::redirect
- `GET /auth/microsoft/callback` → MicrosoftAuthController::callback
- `GET /{any}` → Catch-all for Vue SPA

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
- `name`, `email`, `avatar`
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
- Ensure `axios.get('/sanctum/csrf-cookie')` runs before mounting Vue app
- Check `SANCTUM_STATEFUL_DOMAINS` includes your domain/port
- Verify `axios.defaults.withCredentials = true` is set

### OAuth State Validation Failed
- State is stored in session, not cache - ensure sessions are working
- State is single-use and expires with session
- Do not share state between users (session-isolated)

### Token Refresh
- Check `Office365Connection::isTokenExpired()` before using tokens
- Call `Office365Service::refreshAccessToken($connection)` to refresh
- Update connection model with new tokens after refresh

## Package Manager

This project uses **pnpm** (specified in `package.json` packageManager field). Use `pnpm install` and `pnpm run dev` instead of npm where possible.
