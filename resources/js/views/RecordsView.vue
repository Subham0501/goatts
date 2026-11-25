<script setup>
import { computed, ref } from 'vue'
import { useLiveQuery } from '../composables/live'
import { listFarms, goatsForFarm } from '../services/records'
import { WARD_MAX, WARD_MIN, formatWhen } from '../data/options'
const search = ref('')
const ward = ref('')
const farms = useLiveQuery(async () => {
    const rows = await listFarms()
    return Promise.all(rows.map(async (farm) => ({
        ...farm,
        sampled: (await goatsForFarm(farm.uuid)).length,
    })))
})

const wards = computed(() => Array.from({ length: WARD_MAX - WARD_MIN + 1 }, (_, index) => WARD_MIN + index))
const visible = computed(() => (farms.value || []).filter((farm) => {
    const matchesSearch = !search.value || String(farm.farm_code).toLowerCase().includes(search.value.trim().toLowerCase())
    const matchesWard = !ward.value || Number(farm.ward_number) === Number(ward.value)
    return matchesSearch && matchesWard
}))
</script>

<template>
    <section>
        <div class="grid gap-3 sm:grid-cols-[1fr_10rem]">
            <input v-model="search" class="min-h-12 rounded-2xl border border-line bg-white px-4" placeholder="Search Farm ID" inputmode="search">
            <select v-model="ward" class="min-h-12 rounded-2xl border border-line bg-white px-4">
                <option value="">All wards</option>
                <option v-for="number in wards" :key="number" :value="String(number)">Ward {{ number }}</option>
            </select>
        </div>
        <p class="mt-3 text-sm text-stone-600">{{ visible.length }} farm{{ visible.length === 1 ? '' : 's' }}</p>
        <div v-if="!visible.length" class="mt-3 rounded-3xl border border-dashed border-line bg-white p-5 text-sm text-stone-600">
            No farms match this search.
        </div>
        <div v-else class="mt-3 grid gap-3">
            <RouterLink
                v-for="farm in visible"
                :key="farm.uuid"
                :to="{ name: 'farm-detail', params: { uuid: farm.uuid } }"
                class="rounded-3xl border border-line bg-white p-4"
            >
                <p class="font-semibold">{{ farm.farm_code }}</p>
                <p class="mt-1 text-sm text-stone-600">Ward {{ farm.ward_number }} · {{ farm.total_goats }} goats in herd · {{ farm.sampled }} sampled</p>
                <p class="mt-1 text-sm text-stone-500">{{ formatWhen(farm.created_at) }}</p>
            </RouterLink>
        </div>
    </section>
</template>
