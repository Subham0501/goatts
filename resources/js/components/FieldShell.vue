<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useUiStore } from '../stores/ui'
import { useSessionStore } from '../stores/session'
import ConfirmDialog from './ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const session = useSessionStore()
const confirmLogout = ref(false)
const signingOut = ref(false)

async function logout() {
    if (signingOut.value) return
    signingOut.value = true
    confirmLogout.value = false
    await session.logout()
    signingOut.value = false
    router.push('/')
}

const nav = [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/records', label: 'Farms' },
    { to: '/goats', label: 'Goats' },
    { to: '/reports', label: 'Reports' },
]

const title = computed(() => {
    if (route.name === 'dashboard') return 'Dashboard'
    if (route.name === 'records') return 'Farm records'
    if (route.name === 'goats') return 'Goat records'
    if (route.name === 'reports') return 'Reports'
    if (route.name === 'users') return 'Researchers'
    if (route.name === 'account') return 'Account'
    if (route.name === 'farm-edit') return 'Farm survey'
    if (route.name === 'farm-detail') return 'Farm'
    if (route.name === 'goat-edit') return 'Goat survey'
    return 'Goat Health Survey'
})
</script>

<template>
    <div class="min-h-dvh bg-paper text-ink">
        <header class="sticky top-0 z-30 border-b border-line bg-paper/95 backdrop-blur">
            <div class="mx-auto max-w-5xl px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <RouterLink to="/dashboard" class="min-h-10 text-base font-semibold text-leaf">Dashboard</RouterLink>
                    <button
                        type="button"
                        class="min-h-10 shrink-0 rounded-xl border border-line bg-white px-3 text-sm font-semibold text-clay"
                        @click="confirmLogout = true"
                    >
                        Log out
                    </button>
                </div>
                <h1 v-if="route.name !== 'dashboard'" class="mt-1 truncate text-lg font-semibold leading-tight">{{ title }}</h1>
            </div>
            <p
                v-if="ui.notice"
                class="px-4 py-2 text-sm"
                :class="ui.kind === 'error' ? 'bg-red-50 text-red-900' : 'bg-emerald-50 text-emerald-950'"
            >
                {{ ui.notice }}
            </p>
        </header>
        <main class="mx-auto max-w-5xl px-4 pb-[calc(9rem+env(safe-area-inset-bottom))] pt-4">
            <slot />
        </main>
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white pb-[env(safe-area-inset-bottom)]">
            <div class="mx-auto grid max-w-5xl grid-cols-4">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    v-slot="{ href, navigate, isExactActive }"
                    :to="item.to"
                    custom
                >
                    <a
                        :href="href"
                        class="flex min-h-14 flex-col items-center justify-center text-xs font-semibold"
                        :class="isExactActive ? 'text-leaf' : 'text-stone-500'"
                        @click="navigate"
                    >
                        {{ item.label }}
                    </a>
                </RouterLink>
            </div>
        </nav>
        <ConfirmDialog
            :open="confirmLogout"
            title="Log out?"
            message="You will need to sign in again to continue."
            confirm-label="Log out"
            danger
            @cancel="confirmLogout = false"
            @confirm="logout"
        />
    </div>
</template>
