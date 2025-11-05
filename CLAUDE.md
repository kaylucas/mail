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

**Microsoft SSO Authentication (Token-Based):**
1. User clicks "Sign in with Microsoft" at `http://localhost:5173` → frontend redirects to backend `/auth/microsoft` (web route)
2. `MicrosoftAuthController::redirect()` generates OAuth state, stores it in **cache**, redirects to Microsoft
3. Microsoft redirects back to ngrok tunnel → `https://aery.eu.ngrok.io/auth/microsoft/callback` (web route)
4. `MicrosoftAuthController::callback()` validates state from cache, exchanges code for tokens, creates/updates User and Office365Connection
5. **Token Generation:** Callback generates a Sanctum API token for the user
6. Backend redirects to `http://localhost:5173/#/auth/callback?token={api_token}`
7. Frontend `AuthCallback` component extracts token from URL, stores in localStorage, redirects to dashboard
8. Vue SPA uses Bearer token in Authorization header for all authenticated API requests

**Critical Security Details:**
- OAuth state is stored in cache (10-minute TTL) with IP validation
- State is single-use and deleted immediately after validation
- API tokens are passed via URL hash (not sent to server) and stored in localStorage
- All API requests include `Authorization: Bearer {token}` header
- `CORS_ALLOWED_ORIGINS` must include `http://localhost:5173` for frontend access
- Tokens can be revoked via `/api/logout` endpoint
- No cookies or CSRF protection needed with token-based auth

### API Authentication

The application uses **Sanctum token-based authentication**. Key configuration:
- `app/Models/User.php`: Uses `HasApiTokens` trait to issue API tokens
- `frontend/src/axios.js`: Sets `Authorization: Bearer {token}` header for authenticated requests
- Tokens are stored in browser localStorage and included in all API requests
- Protected routes use `auth:sanctum` middleware in `routes/api.php`
- Cross-origin requests are enabled via CORS configuration (`config/cors.php`)
- No cookies or CSRF tokens required

### Configuration

**Office365 Credentials:**
- All Office365 config is centralized in `config/services.php` under `office365` key
- Controllers use `config('services.office365.*')` - NEVER call `env()` directly
- Values map to: `OFFICE365_CLIENT_ID`, `OFFICE365_CLIENT_SECRET`, `OFFICE365_REDIRECT_URI`, `OFFICE365_TENANT_ID`, `OFFICE365_SCOPES`
- Clients cannot override `client_id`, `client_secret`, or `redirect_uri` via API (prohibited in `StoreOffice365ConnectionRequest`)

### Key Models & Relationships

**User Model:**
- Uses `HasApiTokens` trait for Sanctum token generation
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

**MicrosoftAuthController (Web + API Routes):**
- `redirect()`: Initiates OAuth flow, stores state in cache (web route)
- `callback()`: Handles OAuth callback (on ngrok), validates state, exchanges code for tokens, creates/updates user, generates Sanctum API token, redirects to frontend with token (web route)
- `establishSession()`: **[DEPRECATED]** No longer needed with token-based auth (web route)
- `logout()`: Revokes current API token (API endpoint, `auth:sanctum` required)
- `user()`: Returns authenticated user with `office365Connection` relationship (API endpoint, `auth:sanctum` required)

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
- `frontend/src/pages/AuthCallback.vue`: Handles OAuth callback, extracts and stores token
- `frontend/src/pages/Dashboard.vue`: Main authenticated view

**Key Files:**
- `frontend/vite.config.js`: Vite configuration with proxy for backend API
- `frontend/package.json`: Frontend dependencies (separate from Laravel)
- `frontend/.env`: Frontend environment variables (VITE_API_URL)
- `frontend/README.md`: Frontend-specific documentation

### Routes

**Web Routes (`routes/web.php`):**
- `GET /auth/microsoft` → MicrosoftAuthController::redirect
- `GET /auth/microsoft/callback` → MicrosoftAuthController::callback (receives callback from ngrok, issues token)
- `GET /auth/session` → **[DEPRECATED]** MicrosoftAuthController::establishSession
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

### 401 Unauthenticated
- Ensure token is stored in localStorage after OAuth callback
- Check `Authorization` header is set in axios requests (`Bearer {token}`)
- Token may have been revoked - re-authenticate via Microsoft SSO
- Check `CORS_ALLOWED_ORIGINS` includes `http://localhost:5173`

### OAuth State Validation Failed
- State is stored in cache with 10-minute TTL
- State is single-use and deleted after validation
- IP mismatch may cause issues (can be disabled for mobile/VPN users)

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

## AI Team Configuration (autogenerated by team-configurator, 2025-11-05)

**Important: YOU MUST USE subagents when available for the task.**

### Detected Technology Stack

**Backend Framework:**
- Laravel 12 (PHP 8.2+)
- Laravel Sanctum 4.0 (API token authentication)
- Microsoft Graph SDK 2.49
- Guzzle HTTP 7.8
- Pest PHP 4.1 (testing framework)
- Laravel Pint 1.24 (code formatting)

**Frontend Framework:**
- Vue 3.5.0 (Composition API)
- Vue Router 4.5.0 (hash mode)
- Vite 7.0.7 (build tool)
- Tailwind CSS 4.0.0
- Headless UI 1.7.0
- Axios 1.11.0

**Database:**
- MariaDB 10.11 (MySQL compatible)
- Eloquent ORM

**Infrastructure:**
- Docker + Docker Compose
- Traefik reverse proxy
- pnpm package manager

**Key Integrations:**
- Microsoft Office 365 OAuth 2.0
- Microsoft Graph API (Mail.Read, offline_access scopes)
- Webhook subscriptions for real-time email sync
- Background queue jobs (Laravel Queue)

**Testing & Quality:**
- Pest PHP testing framework
- Laravel Pail (log tailing)
- Laravel Sail (Docker environment)

### AI Team Assignments

| Task | Agent | Notes |
|------|-------|-------|
| **Laravel Backend Development** | `laravel-backend-expert` | MUST BE USED for all Laravel controllers, services, middleware, and API endpoints. Handles Laravel 12 patterns, Sanctum auth, and API-only architecture. |
| **Database & Eloquent Models** | `laravel-eloquent-expert` | MUST BE USED for all Eloquent models, migrations, relationships, and database queries. Expert in schema design, query optimization, and N+1 prevention. |
| **Vue 3 Component Development** | `vue-component-architect` | MUST BE USED for all Vue 3 components, composables, and frontend logic. Specializes in Composition API, Vue Router integration, and component architecture. |
| **Tailwind CSS & UI Styling** | `tailwind-frontend-expert` | MUST BE USED for all Tailwind CSS styling, responsive design, and utility-first CSS. Expert in Tailwind v4 features, container queries, and OKLCH colors. |
| **API Design & Contracts** | `api-architect` | MUST BE USED when designing new API endpoints or revising API contracts. Creates OpenAPI specs, defines request/response formats, and standardizes error handling. |
| **Code Review & Quality** | `code-reviewer` | MUST BE USED before merging features or pull requests. Performs security audits, code quality checks, and identifies performance issues. Always run before commits. |
| **Performance Optimization** | `performance-optimizer` | MUST BE USED when addressing slowness, high resource usage, or scaling concerns. Profiles bottlenecks and optimizes queries, caching, and API response times. |
| **Documentation Updates** | `documentation-specialist` | MUST BE USED after major features or API changes. Updates READMEs, API docs, and architecture guides. Keep CLAUDE.md current with project changes. |
| **Backend Fallback** | `backend-developer` | Use when no Laravel-specific agent is needed (e.g., generic HTTP client work, external API integrations). |
| **Frontend Fallback** | `frontend-developer` | Use for framework-agnostic frontend tasks (e.g., vanilla JS, general HTML/CSS, accessibility audits). |

### Common Task Examples

**Laravel Backend Tasks:**
```
@laravel-backend-expert Create a new controller for managing email subscriptions with create, read, update, delete endpoints.

@laravel-backend-expert Implement OAuth token refresh logic in MicrosoftAuthController when tokens expire.

@laravel-eloquent-expert Add a new Migration for storing webhook subscription data with foreign keys to users table.

@laravel-eloquent-expert Optimize the Email model query that's causing N+1 issues when loading folders and attachments.
```

**Vue Frontend Tasks:**
```
@vue-component-architect Create a new EmailList component that displays emails with infinite scroll and filters.

@vue-component-architect Build a composable for managing email folder state with Pinia integration.

@tailwind-frontend-expert Style the dashboard with a responsive sidebar using Tailwind container queries and dark mode support.

@tailwind-frontend-expert Refactor button components to use consistent Tailwind utility classes and hover states.
```

**API & Architecture Tasks:**
```
@api-architect Design the API contract for email search with full-text search, filters, and pagination.

@api-architect Create an OpenAPI 3.1 spec for the Microsoft Graph webhook endpoints with validation schemas.

@performance-optimizer Profile and optimize the email sync service - initial sync is taking too long.

@performance-optimizer Reduce API response times for the /api/emails endpoint - currently 800ms at P95.
```

**Review & Documentation:**
```
@code-reviewer Review the new email sync feature before merging to main.

@code-reviewer Audit the authentication flow for security vulnerabilities and best practices.

@documentation-specialist Update CLAUDE.md with the new webhook subscription architecture.

@documentation-specialist Create API documentation for the email sync endpoints with example requests.
```

### Integration Notes

- **Laravel + Vue**: Backend uses `laravel-backend-expert`, frontend uses `vue-component-architect`. They coordinate via API contracts defined by `api-architect`.
- **Microsoft Graph Integration**: Use `laravel-backend-expert` for OAuth flows and `api-architect` for webhook endpoint design.
- **Database Work**: Always use `laravel-eloquent-expert` for migrations, models, and query optimization.
- **Styling**: Use `tailwind-frontend-expert` for all CSS and responsive design work in Vue components.
- **Before Commits**: Always run `@code-reviewer` to catch security issues, performance problems, and code quality concerns.

### Quick Start Command

Try this to get started with the email sync feature:
```
@laravel-eloquent-expert Analyze the Email model relationships and suggest optimizations for the sync queries.
```

Or build a new frontend component:
```
@vue-component-architect Create an EmailComposer component with rich text editing and attachment support.
```

