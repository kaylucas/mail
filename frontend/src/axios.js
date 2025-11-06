import axios from 'axios'

// Axios configuration for standalone SPA with token-based auth
// Backend API: http://mail.loc (proxied through Vite in development)

// Configure Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Set base URL from environment variable or default to root
const API_BASE_URL = import.meta.env.VITE_API_URL || '/'
axios.defaults.baseURL = API_BASE_URL

// Set auth token from localStorage if available on initial load
const token = localStorage.getItem('auth_token')
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

// Add response interceptor to handle authentication errors
axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 401) {
            // Unauthorized - remove invalid token and clear auth cache
            localStorage.removeItem('auth_token')
            delete axios.defaults.headers.common['Authorization']

            // Clear authentication cache in router if available
            if (typeof window !== 'undefined') {
                // Dynamically import to avoid circular dependency
                const routerModule = await import('./router/index.js')
                if (routerModule.clearAuthState) {
                    routerModule.clearAuthState()
                }

                // Only redirect if we're not already on login page
                const currentHash = window.location.hash
                if (!currentHash.includes('/auth/callback') && !currentHash.includes('/?')) {
                    window.location.href = '/#/?error=unauthorized'
                }
            }
        }
        return Promise.reject(error)
    }
)

// Make axios available globally ONLY in development mode (security)
if (import.meta.env.DEV) {
    window.axios = axios
}

export default axios
