import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { shortenFarmCodes } from './services/records'

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).catch(() => {})
    if (navigator.onLine && 'caches' in window) {
        fetch('/', { cache: 'reload' })
            .then((response) => response.ok ? caches.open('html-shell').then((cache) => cache.put(`${location.origin}/`, response)) : null)
            .catch(() => {})
    }
}

const app = createApp(App)
app.use(createPinia())
app.use(router)
shortenFarmCodes().finally(() => app.mount('#app'))
