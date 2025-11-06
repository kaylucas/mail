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

**Security Improvements (2025-11-06):**
- SQL injection protection: Job IDs validated before database queries
- Error message sanitization: All exceptions sanitized to prevent data exposure
- Rate limiting: Sync endpoints limited to prevent abuse (5 syncs/hour, 20 status checks/min)
- Production logs: Stack traces hidden in production environment
- See [docs/SECURITY_FIXES.md](docs/SECURITY_FIXES.md) for complete details

### Automatic Email Sync

**Trigger Conditions:**
The system automatically syncs emails for new users immediately after successful OAuth authentication if:
1. User has no stored delta token (`email_delta_token` is null)
2. User has no existing emails in the database
3. User has an active Office365 connection

**Sync Process:**
1. **OAuth Callback** - User authenticates via Microsoft → `MicrosoftAuthController::callback()`
2. **Trigger Check** - System checks if user needs initial sync
3. **Job Dispatch** - `InitialEmailSyncJob` dispatched with 7-day filter
   - Filter: `receivedDateTime ge {7 days ago in ISO 8601}`
   - Example: `receivedDateTime ge 2025-10-30T00:00:00Z`
4. **Folder Sync** - Job syncs email folders first
5. **Email Sync** - Job fetches last 7 days of emails via Microsoft Graph delta query
6. **Delta Token** - Job stores delta token for future incremental syncs
7. **Webhook Setup** - `CreateUserSubscriptionJob` creates webhook subscription
8. **Ready** - User can now receive real-time email notifications

**Job Chain:**
```
MicrosoftAuthController::callback()
  └─> InitialEmailSyncJob (with 7-day filter)
      ├─> EmailSyncService::syncFolders()
      ├─> EmailSyncService::initialSync($user, $filter)
      │   └─> Stores emails + delta token
      └─> CreateUserSubscriptionJob
          └─> GraphSubscriptionService::createSubscription()
              └─> Webhook ready for real-time updates
```

**Configuration:**
- **Sync Window:** Defaults to 7 days (configurable in `MicrosoftAuthController.php` line 214)
- **Queue:** Jobs run on default queue
- **Retry Logic:** 3 attempts with exponential backoff
- **Timeout:** 600 seconds (10 minutes)
- **Deduplication:** 1 hour uniqueness window (prevents duplicate syncs)

**Idempotency:**
- `InitialEmailSyncJob` implements `ShouldBeUnique` interface
- Unique ID: `"initial-email-sync-{user_id}"`
- Multiple login attempts within 1 hour won't trigger duplicate syncs
- Sync only runs if user has no delta token and no emails

**Security:**
- Filter parameter validates OData syntax (regex validation)
- Only allows: `receivedDateTime/sentDateTime/createdDateTime` fields
- Only allows operators: `ge`, `le`, `eq`, `ne`, `gt`, `lt`
- Date format must be ISO 8601 with timezone
- Filter is URL-encoded using `rawurlencode()` (RFC 3986)
- Invalid filter throws `\InvalidArgumentException`

**Observability:**
All operations are logged with context:
- Job dispatch: `'Dispatching initial email sync for new user'`
- Job start: `'InitialEmailSyncJob started'` (includes filter)
- Filter applied: `'Applying filter to initial sync'` (includes encoded filter)
- Job complete: `'Initial sync completed in job'` (includes counts)
- Job failed: `'InitialEmailSyncJob failed'` (includes error details)

**Monitoring:**
Check logs for sync status:
```bash
# Tail logs
php artisan pail

# Search for sync jobs
grep "InitialEmailSyncJob" storage/logs/laravel.log

# Check for failures
grep "InitialEmailSyncJob failed" storage/logs/laravel.log
```

**Manual Triggering:**
You can manually trigger sync for a user:
```php
use App\Jobs\InitialEmailSyncJob;
use App\Models\User;

$user = User::find($userId);

// Full sync (all emails)
InitialEmailSyncJob::dispatch($user);

// Filtered sync (last 30 days)
$filter = "receivedDateTime ge " . now()->subDays(30)->toIso8601String();
InitialEmailSyncJob::dispatch($user, $filter);
```

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
- Fields: `microsoft_id`, `name`, `email`, `email_delta_token`, `last_email_sync_at`
- Methods: `hasDeltaToken()`, `updateDeltaToken($token)`, `clearDeltaToken()`
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

**EmailSyncService:**
- `syncFolders(user)`: Syncs all email folders from Microsoft Graph
- `initialSync(user, filter)`: Performs initial delta query to sync messages with optional date filter
- `processDeltaSync(user)`: Performs incremental sync using stored delta token
- `syncSingleMessage(user, messageId)`: Fetches and stores a single message by ID

### Controllers

**MicrosoftAuthController (Web + API Routes):**
- `redirect()`: Initiates OAuth flow, stores state in cache (web route)
- `callback()`: Handles OAuth callback (on ngrok), validates state, exchanges code for tokens, creates/updates user, generates Sanctum API token, **triggers automatic email sync**, redirects to frontend with token (web route)
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
- `email_delta_token` (nullable): Microsoft Graph delta token for incremental email sync
- `last_email_sync_at` (nullable timestamp): Last time emails were synced
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
- `emails`, `email_folders`, `email_attachments`, `graph_subscriptions`

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

### Email Sync Not Triggered
If emails aren't syncing after login:
1. Check if user already has emails: `User::find($id)->emails()->count()`
2. Check if user has delta token: `User::find($id)->hasDeltaToken()`
3. Check if Office365Connection exists and is active
4. Check queue worker is running: `php artisan queue:listen`
5. Check logs for job dispatch: `grep "Dispatching initial email sync" storage/logs/laravel.log`

### Duplicate Sync Jobs
If multiple syncs are triggered:
- System prevents duplicates with `ShouldBeUnique` (1 hour window)
- Check if queue is configured correctly
- Verify cache driver is working (uniqueness uses cache)

### Slow Initial Sync
If sync takes too long:
- Check network latency to Microsoft Graph API
- Verify 7-day filter is applied (reduces email count)
- Check if queue worker has sufficient memory
- Consider reducing sync window or using dedicated queue

## Package Manager

This project uses **pnpm** for both backend and frontend (specified in `package.json` and `frontend/package.json`). Use `pnpm install` and `pnpm run dev` instead of npm where possible.

## Project Structure

```
/
├── app/                    # Laravel application code
├── config/                 # Laravel configuration
├── database/               # Migrations, seeders, factories
├── docs/                   # Project documentation
│   ├── AUTOMATIC_EMAIL_SYNC.md      # Email sync feature guide
│   └── TESTING_EMAIL_ENDPOINTS.md   # Email endpoint testing
├── frontend/               # Separated Vue 3 SPA frontend
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
