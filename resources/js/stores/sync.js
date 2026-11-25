import { defineStore } from 'pinia'
import { prepareSync, syncNow as runSyncNow } from '../services/sync'
import { checkApi } from '../services/connectivity'
import { useSessionStore } from './session'
export const useSyncStore = defineStore('sync', {
    state: () => ({
        online: false,
        running: false,
        lastMessage: '',
        booted: false,
        timer: null,
        nudgeTimer: null,
    }),
    actions: {
        async boot() {
            if (this.booted) {
                await this.refreshConnectivity()
                return
            }
            this.booted = true
            await prepareSync()
            await this.refreshConnectivity()
            this.timer = window.setInterval(() => this.auto(), 30000)
            window.addEventListener('online', () => this.auto())
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') this.auto()
            })
            this.auto()
        },
        async refreshConnectivity() {
            this.online = await checkApi()
            return this.online
        },
        nudge() {
            clearTimeout(this.nudgeTimer)
            this.nudgeTimer = setTimeout(() => this.auto(), 1200)
        },
        async auto() {
            if (!useSessionStore().token) return
            await this.syncNow({ manual: false })
        },
        async syncNow() {
            if (!useSessionStore().token) return
            this.running = true
            try {
                const result = await runSyncNow()
                this.online = result.online
                this.lastMessage = result.message
                return result
            } finally {
                this.running = false
            }
        },
    },
})
