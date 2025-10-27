import './bootstrap'
import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import axios from 'axios'

// Configure Axios for SPA
axios.defaults.withCredentials = true
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrfToken) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

// Initialize Sanctum CSRF cookie
axios.get('/sanctum/csrf-cookie').then(() => {
  // Create and mount Vue app after CSRF cookie is initialized
  createApp(App).use(router).mount('#app')
})
