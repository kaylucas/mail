import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Development setup: Frontend runs locally on localhost:5173
// Backend runs in Docker via Traefik at mail.loc
// Vite proxies API/auth requests to the backend

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
