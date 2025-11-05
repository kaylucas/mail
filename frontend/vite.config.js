import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// Standalone Vue SPA Configuration
// Frontend: localhost:5173 (default Vite dev server)
// Backend API: http://mail.loc (Docker + Traefik)
//
// The frontend runs independently and communicates with the backend API via axios.
// CORS must be configured on the Laravel backend to allow requests from localhost:5173

export default defineConfig({
    plugins: [
        vue(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
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
    build: {
        outDir: 'dist',
        assetsDir: 'assets',
        sourcemap: true,
    },
    resolve: {
        alias: {
            '@': '/src',
        },
    },
});
