import { defineStore } from 'pinia'
import { api, setToken } from '../services/api'
import { checkApi } from '../services/connectivity'
import { askPersistentStorage, db } from '../db'
import { setUnauthorizedHandler } from '../services/sync'

export const useSessionStore = defineStore('session', {
    state: () => ({
        user: null,
        token: null,
        ready: false,
        restoring: null,
    }),
    actions: {
        async restore() {
            if (this.ready) return
            if (this.restoring) return this.restoring
            this.restoring = this.load()
            await this.restoring
        },
        async load() {
            setUnauthorizedHandler(async () => {
                await this.clearAuth()
                if (window.location.pathname !== '/') window.location.assign('/')
            })
            const row = await db.appSettings.get('auth')
            if (row?.token) {
                this.token = row.token
                this.user = row.user
                setToken(row.token)
                if (await checkApi()) {
                    try {
                        const { data } = await api.get('/user')
                        this.user = data
                        await db.appSettings.put({ key: 'auth', token: this.token, user: data })
                    } catch (error) {
                        if (error?.response?.status === 401) await this.clearAuth()
                    }
                }
            }
            this.ready = true
            if (this.token) {
                const { useSyncStore } = await import('./sync')
                useSyncStore().boot()
            }
            askPersistentStorage()
        },
        async login(email, password) {
            if (!(await checkApi())) {
                throw new Error('Sign-in could not reach the server. Try again.')
            }
            const { data } = await api.post('/login', { email, password })
            this.token = data.token
            this.user = data.user
            setToken(data.token)
            await db.appSettings.put({ key: 'auth', token: data.token, user: data.user })
            const { useSyncStore } = await import('./sync')
            await useSyncStore().boot()
            askPersistentStorage()
        },
        async logout() {
            try {
                if (await checkApi()) await api.post('/logout')
            } catch {
                // The local session still ends. Survey records stay on the device.
            }
            await this.clearAuth()
        },
        async clearAuth() {
            this.user = null
            this.token = null
            setToken(null)
            await db.appSettings.delete('auth')
        },
    },
})
