import { createRouter, createWebHashHistory } from 'vue-router'
import axios from '../axios'
import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import AuthCallback from '../pages/AuthCallback.vue'

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
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

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
      next({ name: 'Login' })
      return
    }

    try {
      // Verify token is valid by fetching user
      await axios.get('/api/user')
      next()
    } catch (error) {
      if (error.response?.status === 401) {
        // Token invalid, clear it and redirect to login
        localStorage.removeItem('auth_token')
        delete axios.defaults.headers.common['Authorization']
        next({ name: 'Login' })
      } else {
        // Network error or other issue
        console.error('Auth check failed:', error)
        next({ name: 'Login' })
      }
    }
  } else if (to.meta.guest) {
    if (token) {
      try {
        // Check if token is still valid
        await axios.get('/api/user')
        // Token valid, redirect to dashboard
        next({ name: 'Dashboard' })
      } catch (error) {
        // Token invalid, clear it and proceed to guest route
        localStorage.removeItem('auth_token')
        delete axios.defaults.headers.common['Authorization']
        next()
      }
    } else {
      // No token, proceed to guest route
      next()
    }
  } else {
    next()
  }
})

export default router
