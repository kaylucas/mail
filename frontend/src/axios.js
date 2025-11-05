import axios from 'axios'

// Axios configuration for standalone SPA
// Backend API: http://mail.loc (proxied through Vite in development)

// Configure Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true

// Set base URL from environment variable or default to root
const API_BASE_URL = import.meta.env.VITE_API_URL || '/'
axios.defaults.baseURL = API_BASE_URL

// Add response interceptor to handle CSRF token mismatch
axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) { // CSRF token mismatch
            // Refresh CSRF token and retry the original request
            try {
                await axios.get('/sanctum/csrf-cookie')
                return axios.request(error.config)
            } catch (retryError) {
                return Promise.reject(retryError)
            }
        }
        return Promise.reject(error)
    }
)

// Initialize Sanctum CSRF cookie on app load
axios.get('/sanctum/csrf-cookie').catch(error => {
    console.error('Failed to initialize CSRF cookie:', error)
})

// Make axios available globally (optional)
window.axios = axios

export default axios
