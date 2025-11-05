# Mail Application - Frontend

This is the Vue 3 frontend for the Mail application. It runs as a standalone SPA and communicates with the Laravel backend API.

## Prerequisites

- Node.js 18+ or pnpm 8+
- The Laravel backend must be running at `http://mail.loc`

## Installation

```bash
# Install dependencies
pnpm install
```

## Development

```bash
# Start development server on localhost:5173
pnpm dev
```

The frontend will be available at `http://localhost:5173` and will proxy API requests to `http://mail.loc`.

## Building for Production

```bash
# Build for production
pnpm build

# Preview production build
pnpm preview
```

The production build will be output to the `dist` folder.

## Configuration

Environment variables can be set in `.env` file. Copy `.env.example` to `.env` and adjust as needed.

- `VITE_API_URL`: Backend API URL (optional, defaults to proxy in development)

## Architecture

- **Framework**: Vue 3 with Composition API
- **Router**: Vue Router 4 (hash mode)
- **HTTP Client**: Axios
- **Styling**: Tailwind CSS 4
- **UI Components**: Headless UI
- **Build Tool**: Vite 7

## Authentication

The application uses Laravel Sanctum for session-based authentication:

1. CSRF cookie is initialized on app load (`axios.js`)
2. User clicks "Sign in with Microsoft" which redirects to Laravel backend
3. After OAuth flow completes, user is redirected back with session cookie
4. Frontend makes authenticated API requests using session cookie

## Project Structure

```
frontend/
├── index.html        # HTML template (root level for Vite)
├── public/           # Static assets (copied as-is)
├── src/
│   ├── pages/        # Page components
│   ├── router/       # Vue Router configuration
│   ├── App.vue       # Root component
│   ├── axios.js      # Axios configuration
│   ├── main.js       # Application entry point
│   └── styles.css    # Global styles (Tailwind)
├── .env              # Environment variables
├── package.json      # Dependencies
└── vite.config.js    # Vite configuration
```

## API Communication

All API requests are made to the Laravel backend:

- `GET /api/user` - Get authenticated user
- `POST /api/logout` - Logout
- OAuth flow endpoints are on the web routes (not API)

In development, Vite proxies these requests to `http://mail.loc`. See `vite.config.js` for proxy configuration.
