<script setup>
import { onMounted, ref } from 'vue'
import { storageEstimate } from '../db'
import { askPersistentStorage } from '../db'
import { useLiveQuery } from '../composables/live'
import { surveyCounts } from '../services/records'

const storage = ref(null)
const counts = useLiveQuery(() => surveyCounts())

async function refresh() {
    storage.value = await storageEstimate()
}

onMounted(refresh)

async function persist() {
    await askPersistentStorage()
    await refresh()
}

function megabytes(bytes) {
    if (!bytes) return '0'
    return (bytes / (1024 * 1024)).toFixed(1)
}
</script>

<template>
    <section class="grid gap-4">
        <article class="rounded-3xl border border-line bg-white p-4 text-sm leading-6 text-stone-700">
            Survey records are stored in IndexedDB on this device, not in temporary browser storage. They remain after the browser is closed, refreshed, or the phone restarts. They are sent to MySQL only after the server confirms each record.
        </article>
        <article class="rounded-3xl border border-line bg-white p-4">
            <p class="font-semibold">Storage on this device</p>
            <p class="mt-2 text-sm text-stone-600">
                {{ storage?.persisted ? 'Persistent storage is allowed.' : 'Persistent storage has not been granted yet.' }}
                Using about {{ megabytes(storage?.usage) }} MB.
            </p>
            <button type="button" class="mt-4 min-h-12 w-full rounded-2xl bg-leaf font-semibold text-white" @click="persist">
                Ask the browser to keep this data
            </button>
        </article>
        <article class="grid grid-cols-2 gap-3">
            <div class="rounded-3xl border border-line bg-white p-4">
                <p class="text-sm text-stone-600">Farms on device</p>
                <p class="text-3xl font-semibold">{{ counts?.farms ?? '—' }}</p>
            </div>
            <div class="rounded-3xl border border-line bg-white p-4">
                <p class="text-sm text-stone-600">Goats on device</p>
                <p class="text-3xl font-semibold">{{ counts?.goats ?? '—' }}</p>
            </div>
        </article>
        <p class="text-sm leading-6 text-stone-600">
            Install the survey from the browser menu with Add to Home Screen. Open it once while online so the app shell is cached, then it can be opened without a connection.
        </p>
    </section>
</template>
