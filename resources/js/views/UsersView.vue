<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../services/api'
import { checkApi } from '../services/connectivity'
import { useUiStore } from '../stores/ui'
import TextField from '../components/TextField.vue'

const ui = useUiStore()
const online = ref(false)
const users = ref([])
const form = reactive({ name: '', email: '', password: '', role: 'researcher' })

async function load() {
    online.value = await checkApi()
    if (!online.value) return
    const { data } = await api.get('/users')
    users.value = data
}

onMounted(load)

async function createUser() {
    try {
        await api.post('/users', { ...form })
        form.name = ''
        form.email = ''
        form.password = ''
        form.role = 'researcher'
        ui.flash('Researcher account created.')
        await load()
    } catch (error) {
        const messages = error?.response?.data?.errors
        ui.flash(messages ? Object.values(messages)[0][0] : 'The account could not be created.', 'error')
    }
}

async function toggle(user) {
    await api.put(`/users/${user.id}`, { is_active: !user.is_active })
    await load()
}
</script>

<template>
    <section class="grid gap-4">
        <p v-if="!online" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-950">
            User management needs a connection to the server.
        </p>
        <form v-else class="grid gap-3 rounded-3xl border border-line bg-white p-4" @submit.prevent="createUser">
            <h2 class="text-lg font-semibold">Add a researcher</h2>
            <TextField v-model="form.name" label="Name" />
            <TextField v-model="form.email" label="Email" type="email" autocomplete="off" />
            <TextField v-model="form.password" label="Temporary password" type="password" autocomplete="new-password" hint="At least 8 characters. Ask them to change it after signing in." />
            <label class="text-sm font-semibold">
                Role
                <select v-model="form.role" class="mt-2 min-h-12 w-full rounded-2xl border border-line px-3 font-normal">
                    <option value="researcher">Researcher</option>
                    <option value="admin">Administrator</option>
                </select>
            </label>
            <button type="submit" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white">Create account</button>
        </form>
        <article v-for="user in users" :key="user.id" class="rounded-3xl border border-line bg-white p-4">
            <p class="font-semibold">{{ user.name }}</p>
            <p class="text-sm text-stone-600">{{ user.email }} · {{ user.role }} · {{ user.is_active ? 'Active' : 'Inactive' }}</p>
            <button type="button" class="mt-3 min-h-10 rounded-xl border border-line px-3 text-sm font-semibold" @click="toggle(user)">
                {{ user.is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </article>
    </section>
</template>
