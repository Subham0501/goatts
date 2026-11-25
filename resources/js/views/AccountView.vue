<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useSessionStore } from '../stores/session'
import { useUiStore } from '../stores/ui'
import TextField from '../components/TextField.vue'

const session = useSessionStore()
const ui = useUiStore()
const router = useRouter()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false)

async function changePassword() {
    saving.value = true
    try {
        await api.put('/user/password', { ...form })
        form.current_password = ''
        form.password = ''
        form.password_confirmation = ''
        ui.flash('Password updated.')
    } catch (error) {
        const messages = error?.response?.data?.errors
        ui.flash(messages ? Object.values(messages)[0][0] : 'The password was not changed.', 'error')
    } finally {
        saving.value = false
    }
}

async function logout() {
    await session.logout()
    router.push('/')
}
</script>

<template>
    <section class="grid gap-4">
        <article class="rounded-3xl border border-line bg-white p-4">
            <p class="text-sm text-stone-600">Signed in</p>
            <p class="text-xl font-semibold">{{ session.user?.name }}</p>
            <p class="text-sm text-stone-600">{{ session.user?.email }} · {{ session.user?.role }}</p>
        </article>
        <form class="grid gap-3 rounded-3xl border border-line bg-white p-4" @submit.prevent="changePassword">
            <h2 class="text-lg font-semibold">Change password</h2>
            <TextField v-model="form.current_password" label="Current password" type="password" autocomplete="current-password" />
            <TextField v-model="form.password" label="New password" type="password" autocomplete="new-password" />
            <TextField v-model="form.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" />
            <button type="submit" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" :disabled="saving">Update password</button>
        </form>
        <button type="button" class="min-h-12 rounded-2xl border border-clay font-semibold text-clay" @click="logout">Log out</button>
    </section>
</template>
