import axios from 'axios';
window.axios = axios;

// Axios configuration for cross-origin requests
// Frontend: localhost:5173 (Vite dev server)
// Backend: mail.loc (Docker + Traefik)

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Set base URL for API requests
// In development: Vite proxy handles routing to backend (see vite.config.js)
// In production: Set VITE_API_URL to backend URL if needed
window.axios.defaults.baseURL = import.meta.env.VITE_API_URL || '/';

// Enable credentials for cross-origin requests (required for cookies)
window.axios.defaults.withCredentials = true;

// Set CSRF token header if available
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Add response interceptor to handle CSRF token mismatch
window.axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) { // CSRF token mismatch
            // Refresh CSRF token and retry the original request
            try {
                await window.axios.get('/sanctum/csrf-cookie');
                return window.axios.request(error.config);
            } catch (retryError) {
                return Promise.reject(retryError);
            }
        }
        return Promise.reject(error);
    }
);
