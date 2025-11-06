import { createRouter, createWebHashHistory } from 'vue-router'
import axios from '../axios'
import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import AuthCallback from '../pages/AuthCallback.vue'
import EmailsPage from '../pages/EmailsPage.vue'
import EmailViewer from '../pages/EmailViewer.vue'
import Settings from '../pages/Settings.vue'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: Login,
    meta: { guest: true }
  },
  {
    path: '/auth/callback',
    name: 'AuthCallback',
    component: AuthCallback,
    meta: { public: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true }
  },
  {
    path: '/emails',
    name: 'Emails',
    component: EmailsPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/emails/:id',
    name: 'EmailViewer',
    component: EmailViewer,
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: Settings,
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

// Authentication state cache to avoid redundant API calls
let isAuthenticatedCache = null
let authCacheExpiry = null
let authCheckPromise = null

// Auth cache TTL: 5 minutes
const AUTH_CACHE_TTL = 5 * 60 * 1000

// Helper to clear auth state
export const clearAuthState = () => {
  isAuthenticatedCache = false
  authCacheExpiry = null
  authCheckPromise = null
  localStorage.removeItem('auth_token')
  delete axios.defaults.headers.common['Authorization']
}

// Helper to validate token with backend (cached)
const validateToken = async () => {
  // If we already have a validation in progress, wait for it
  if (authCheckPromise) {
    return authCheckPromise
  }

  // Check if cache has expired
  if (isAuthenticatedCache === true && authCacheExpiry && Date.now() > authCacheExpiry) {
    isAuthenticatedCache = null
    authCacheExpiry = null
  }

  // If we already validated and it's cached and not expired, return cached result
  if (isAuthenticatedCache === true) {
    return true
  }

  // Make validation request and cache the promise
  authCheckPromise = axios.get('/api/user')
    .then(() => {
      isAuthenticatedCache = true
      authCacheExpiry = Date.now() + AUTH_CACHE_TTL
      authCheckPromise = null
      return true
    })
    .catch((error) => {
      if (error.response?.status === 401) {
        // Token is invalid
        clearAuthState()
      }
      authCheckPromise = null
      return false
    })

  return authCheckPromise
}

// Navigation guard for token-based authentication
router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('auth_token')

  // Public routes (like auth callback)
  if (to.meta.public) {
    next()
    return
  }

  if (to.meta.requiresAuth) {
    if (!token) {
      // No token, redirect to login
      isAuthenticatedCache = false
      next({ name: 'Login' })
      return
    }

    // Token exists, validate it (uses cache)
    const isValid = await validateToken()

    if (isValid) {
      next()
    } else {
      // Token validation failed, redirect to login
      next({ name: 'Login' })
    }
  } else if (to.meta.guest) {
    if (token) {
      // Token exists, check if valid (uses cache)
      const isValid = await validateToken()

      if (isValid) {
        // Token valid, redirect to dashboard
        next({ name: 'Dashboard' })
      } else {
        // Token invalid, proceed to guest route
        next()
      }
    } else {
      // No token, proceed to guest route
      isAuthenticatedCache = false
      next()
    }
  } else {
    next()
  }
})

export default router
