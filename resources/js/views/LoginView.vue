<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSessionStore } from '../stores/session'
import TextField from '../components/TextField.vue'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

async function submit() {
    error.value = ''
    submitting.value = true
    try {
        await session.login(email.value.trim(), password.value)
        const next = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//')
            ? route.query.redirect
            : '/dashboard'
        router.push(next)
    } catch (exception) {
        error.value = exception?.response?.data?.errors?.email?.[0]
            || exception?.response?.data?.message
            || exception.message
            || 'Sign-in failed.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-10">
        <p class="text-sm font-semibold uppercase tracking-wide text-leaf">Sundarbazar Municipality · Lamjung</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Goat Health Survey</h1>
        <p class="mt-3 text-sm leading-6 text-stone-700">
            Sign in once while you have internet. After that, farm and goat surveys can be recorded without a connection.
        </p>
        <form class="mt-8 grid gap-4 rounded-3xl border border-line bg-white p-5" @submit.prevent="submit">
            <TextField v-model="email" label="Email" type="email" autocomplete="username" inputmode="email" />
            <TextField v-model="password" label="Password" type="password" autocomplete="current-password" />
            <p v-if="error" class="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-800">{{ error }}</p>
            <button type="submit" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white disabled:opacity-60" :disabled="submitting">
                {{ submitting ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
        <RouterLink to="/" class="mt-6 text-sm font-semibold text-leaf">Back to start</RouterLink>
    </main>
</template>
