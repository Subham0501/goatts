<script setup>
import { useRouter } from 'vue-router'
import { useLiveQuery } from '../composables/live'
import { surveyCounts, persistFarm } from '../services/records'
import { blankFarm } from '../data/validate'
import { db } from '../db'
import { createUuid, nextFarmCode } from '../data/ids'
import { formatWhen } from '../data/options'
import { useSessionStore } from '../stores/session'

const router = useRouter()
const session = useSessionStore()
const counts = useLiveQuery(() => surveyCounts())

async function startFarm() {
    const uuid = createUuid()
    const farm = blankFarm(uuid)
    const existing = await db.farms.toArray()
    farm.farm_code = nextFarmCode(existing.map((row) => row.farm_code))
    await persistFarm(farm, 'draft')
    router.push({ name: 'farm-edit', params: { uuid } })
}
</script>

<template>
    <section class="grid gap-4">
        <div class="rounded-3xl bg-leaf px-5 py-5 text-white">
            <p class="text-sm text-emerald-100">Hello, {{ session.user?.name || 'Researcher' }}</p>
            <h2 class="mt-1 text-2xl font-semibold leading-tight">Field survey</h2>
            <p class="mt-1 text-sm text-emerald-100">Sundarbazar, Lamjung</p>
            <button
                type="button"
                class="mt-4 min-h-12 w-full rounded-2xl bg-white text-base font-semibold text-leaf active:bg-emerald-50"
                @click="startFarm"
            >
                New farm survey
            </button>
        </div>

        <div class="grid grid-cols-2 overflow-hidden rounded-3xl border border-line bg-white">
            <div class="border-r border-line px-4 py-4">
                <p class="text-sm text-stone-500">Farms</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums leading-none">{{ counts?.farms ?? '—' }}</p>
            </div>
            <div class="px-4 py-4">
                <p class="text-sm text-stone-500">Goats</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums leading-none">{{ counts?.goats ?? '—' }}</p>
            </div>
        </div>

        <div>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold">Recent farms</h2>
                <RouterLink to="/records" class="text-sm font-semibold text-leaf">View all</RouterLink>
            </div>
            <div v-if="!counts?.recent?.length" class="mt-3 rounded-3xl border border-dashed border-line bg-white px-4 py-5 text-sm leading-6 text-stone-600">
                No farms yet. Start a farm survey, then add each sampled goat.
            </div>
            <div v-else class="mt-3 overflow-hidden rounded-3xl border border-line bg-white">
                <RouterLink
                    v-for="(farm, index) in counts.recent"
                    :key="farm.uuid"
                    :to="{ name: 'farm-detail', params: { uuid: farm.uuid } }"
                    class="block px-4 py-3 active:bg-stone-50"
                    :class="index > 0 ? 'border-t border-line' : ''"
                >
                    <p class="break-all font-semibold leading-6">{{ farm.farm_code }}</p>
                    <p class="mt-1 text-sm leading-5 text-stone-600">
                        Ward {{ farm.ward_number || '—' }} · {{ farm.sampled }} sampled · {{ formatWhen(farm.updated_at) }}
                    </p>
                </RouterLink>
            </div>
        </div>
    </section>
</template>
