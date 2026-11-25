<script setup>
import { useLiveQuery } from '../composables/live'
import { recordsNeedingAttention, resolveRecord } from '../services/records'
import { useSyncStore } from '../stores/sync'
import StatusPill from '../components/StatusPill.vue'
import { labelFor, feedingSystems, sexes } from '../data/options'

const sync = useSyncStore()
const data = useLiveQuery(() => recordsNeedingAttention())

const compareFields = [
    ['farm_code', 'Farm ID'],
    ['goat_code', 'Goat ID'],
    ['ward_number', 'Ward'],
    ['total_goats', 'Total goats'],
    ['feeding_system', 'Feeding'],
    ['sex', 'Sex'],
    ['breed', 'Breed'],
    ['body_condition_score', 'Body condition'],
]

function showField(record, field) {
    return record?.[field] !== undefined && record?.[field] !== null && record?.[field] !== ''
}
</script>

<template>
    <section>
        <div class="rounded-3xl border border-line bg-white p-4">
            <p class="text-sm leading-6 text-stone-700">
                Sync sends farms first, then their goats. A record stays on this device until the server confirms it. If the same record changed on another device, you choose which copy to keep.
            </p>
            <button type="button" class="mt-4 min-h-12 w-full rounded-2xl bg-leaf font-semibold text-white" :disabled="sync.running" @click="sync.syncNow({ manual: true })">
                {{ sync.running ? 'Syncing…' : 'Sync now' }}
            </button>
            <p v-if="sync.lastMessage" class="mt-3 text-sm text-stone-600">{{ sync.lastMessage }}</p>
        </div>
        <h2 class="mt-6 text-lg font-semibold">Waiting records</h2>
        <div v-if="!(data?.farms?.length || data?.goats?.length)" class="mt-3 rounded-3xl border border-dashed border-line bg-white p-5 text-sm text-stone-600">
            No drafts, failures, or conflicts are waiting.
        </div>
        <div class="mt-3 grid gap-3">
            <article v-for="farm in data?.farms || []" :key="farm.uuid" class="rounded-3xl border border-line bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-stone-500">Farm</p>
                        <p class="font-semibold">{{ farm.farm_code }}</p>
                    </div>
                    <StatusPill :status="farm.sync_status" />
                </div>
                <p v-if="farm.sync_error" class="mt-2 text-sm text-red-800">{{ farm.sync_error }}</p>
                <div v-if="farm.sync_status === 'conflict' && farm.server_version" class="mt-3 grid gap-2 text-sm">
                    <p v-for="field in compareFields" :key="field[0]">
                        <template v-if="showField(farm, field[0]) || showField(farm.server_version, field[0])">
                            <span class="text-stone-500">{{ field[1] }}: </span>
                            this device {{ farm[field[0]] ?? '—' }}, server {{ farm.server_version[field[0]] ?? '—' }}
                        </template>
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="min-h-11 rounded-xl bg-leaf font-semibold text-white" @click="resolveRecord('farm', farm.uuid, 'client').then(() => sync.syncNow({ manual: true }))">Keep this device</button>
                        <button type="button" class="min-h-11 rounded-xl border border-line font-semibold" @click="resolveRecord('farm', farm.uuid, 'server').then(() => sync.syncNow({ manual: true }))">Use server copy</button>
                    </div>
                </div>
                <RouterLink v-else :to="{ name: 'farm-edit', params: { uuid: farm.uuid } }" class="mt-3 inline-flex min-h-10 items-center font-semibold text-leaf">Open farm</RouterLink>
            </article>
            <article v-for="goat in data?.goats || []" :key="goat.uuid" class="rounded-3xl border border-line bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-stone-500">Goat</p>
                        <p class="font-semibold">{{ goat.goat_code }}</p>
                        <p class="text-sm text-stone-600">{{ labelFor(sexes, goat.sex) }} · {{ labelFor(feedingSystems, goat.feeding_system) }}</p>
                    </div>
                    <StatusPill :status="goat.sync_status" />
                </div>
                <p v-if="goat.sync_error" class="mt-2 text-sm text-red-800">{{ goat.sync_error }}</p>
                <div v-if="goat.sync_status === 'conflict' && goat.server_version" class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" class="min-h-11 rounded-xl bg-leaf font-semibold text-white" @click="resolveRecord('goat', goat.uuid, 'client').then(() => sync.syncNow({ manual: true }))">Keep this device</button>
                    <button type="button" class="min-h-11 rounded-xl border border-line font-semibold" @click="resolveRecord('goat', goat.uuid, 'server').then(() => sync.syncNow({ manual: true }))">Use server copy</button>
                </div>
                <RouterLink v-else :to="{ name: 'goat-edit', params: { uuid: goat.uuid } }" class="mt-3 inline-flex min-h-10 items-center font-semibold text-leaf">Open goat</RouterLink>
            </article>
        </div>
        <p class="mt-6 text-sm text-stone-600">Queue entries: {{ data?.queue?.length || 0 }}</p>
        <RouterLink to="/offline" class="mt-2 inline-flex font-semibold text-leaf">Offline data details</RouterLink>
    </section>
</template>
