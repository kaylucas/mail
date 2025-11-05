import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Development setup:
// - Application access: http://mail.loc (ALWAYS use this URL)
// - Vite dev server: localhost:5173 (for hot-reload only, do NOT access directly)
// - Backend: Docker via Traefik at mail.loc
// - Vite provides hot-reload via HMR, but users should access http://mail.loc
// - Proxies are configured for the Vite dev server, but not used when accessing via mail.loc

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        cors: {
            origin: ['http://mail.loc', 'http://localhost:5173'],
            credentials: true,
        },
        proxy: {
            '/api': {
                target: 'http://mail.loc',
                changeOrigin: true,
                secure: false,
                ws: true,
            },
            '/sanctum': {
                target: 'http://mail.loc',
                changeOrigin: true,
                secure: false,
                ws: true,
            },
            '/auth': {
                target: 'http://mail.loc',
                changeOrigin: true,
                secure: false,
                ws: true,
            },
        },
    },
});
