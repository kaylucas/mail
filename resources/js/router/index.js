import { createRouter, createWebHashHistory } from 'vue-router'
import axios from 'axios'
import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'

const routes = [
  {
    path: '/',
    name: 'Login',
    component: Login,
    meta: { guest: true }
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

// Navigation guard
// Note: Cross-origin authentication between localhost:5173 and mail.loc
// may require browser to allow credentials. Check browser console for CORS errors.
router.beforeEach(async (to, from, next) => {
  if (to.meta.requiresAuth) {
    try {
      // Verify authentication by fetching user
      await axios.get('/api/user')
      next()
    } catch (error) {
      if (error.response?.status === 401) {
        // Not authenticated
        next({ name: 'Login' })
      } else if (error.response?.status === 419) {
        // CSRF token mismatch - redirect to login
        next({ name: 'Login' })
      } else {
        // Network error or other issue
        console.error('Auth check failed:', error)
        next({ name: 'Login' })
      }
    }
  } else if (to.meta.guest) {
    try {
      // Check if already authenticated
      await axios.get('/api/user')
      // Already authenticated, redirect to dashboard
      next({ name: 'Dashboard' })
    } catch (error) {
      if (error.response?.status === 401) {
        // Not authenticated, proceed to guest route
        next()
      } else if (error.response?.status === 419) {
        // CSRF token mismatch - proceed to guest route
        next()
      } else {
        // Network error or other issue
        console.error('Auth check failed:', error)
        next()
      }
    }
  } else {
    next()
  }
})

export default router
