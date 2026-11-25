import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { shortenFarmCodes } from './services/records'

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {})
    })
}

const app = createApp(App)
app.use(createPinia())
app.use(router)
shortenFarmCodes().finally(() => app.mount('#app'))
