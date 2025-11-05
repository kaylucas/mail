import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import './axios'
import './styles.css'

// Create and mount Vue app
// Note: CSRF cookie initialization is handled in axios.js
createApp(App).use(router).mount('#app')
