<script setup>
import { computed, ref } from 'vue'
import { useLiveQuery } from '../composables/live'
import { listFarms, listGoats } from '../services/records'
import {
    breeds,
    farmDewormingFrequencies,
    feedingSystems,
    sexes,
    WARD_MAX,
    WARD_MIN,
} from '../data/options'
import {
    downloadText,
    downloadWorkbook,
    farmHeaders,
    farmRows,
    goatHeaders,
    goatRows,
    toCsv,
} from '../services/export'

const search = ref('')
const ward = ref('')
const sex = ref('')
const breed = ref('')
const feeding = ref('')
const frequency = ref('')
const farms = useLiveQuery(() => listFarms())
const goats = useLiveQuery(() => listGoats())
const wards = Array.from({ length: WARD_MAX - WARD_MIN + 1 }, (_, index) => WARD_MIN + index)

const filteredFarms = computed(() => (farms.value || []).filter((farm) => {
    const query = search.value.trim().toLowerCase()
    const matchesSearch = !query || String(farm.farm_code).toLowerCase().includes(query)
    const matchesWard = !ward.value || Number(farm.ward_number) === Number(ward.value)
    const matchesFeeding = !feeding.value || farm.feeding_system === feeding.value
    const matchesFrequency = !frequency.value || farm.deworming_frequency === frequency.value
    return matchesSearch && matchesWard && matchesFeeding && matchesFrequency
}))

const filteredGoats = computed(() => (goats.value || []).filter((goat) => {
    const query = search.value.trim().toLowerCase()
    const matchesSearch = !query || `${goat.goat_code} ${goat.farm_code}`.toLowerCase().includes(query)
    const matchesWard = !ward.value || Number(goat.ward_number) === Number(ward.value)
    const matchesSex = !sex.value || goat.sex === sex.value
    const matchesBreed = !breed.value || goat.breed === breed.value
    const matchesFeeding = !feeding.value || goat.feeding_system === feeding.value
    const matchesFrequency = !frequency.value || goat.deworming_frequency === frequency.value
    return matchesSearch && matchesWard && matchesSex && matchesBreed && matchesFeeding && matchesFrequency
}))

const exportFarms = computed(() => filteredFarms.value)
const exportGoats = computed(() => filteredGoats.value.map((goat) => {
    const farm = (farms.value || []).find((item) => item.uuid === goat.farm_uuid)
    return {
        ...goat,
        farm_total_goats: farm?.total_goats ?? '',
        farm_feeding_system: farm?.feeding_system ?? '',
        farm_deworming_frequency: farm?.deworming_frequency ?? '',
        farm_common_anthelmintic: farm?.common_anthelmintic ?? '',
    }
}))

function stamp() {
    return new Date().toISOString().slice(0, 10)
}

function exportCsv(kind) {
    if (kind === 'farms') {
        downloadText(`sundarbazar-farms-${stamp()}.csv`, toCsv(farmHeaders, farmRows(exportFarms.value, goats.value || [])), 'text/csv')
    } else {
        downloadText(`sundarbazar-goats-${stamp()}.csv`, toCsv(goatHeaders, goatRows(exportGoats.value)), 'text/csv')
    }
}

async function exportExcel(kind) {
    if (kind === 'farms') {
        await downloadWorkbook(`sundarbazar-farms-${stamp()}.xlsx`, [
            { name: 'Farms', headers: farmHeaders, rows: farmRows(exportFarms.value, goats.value || []) },
        ])
    } else {
        await downloadWorkbook(`sundarbazar-goats-${stamp()}.xlsx`, [
            { name: 'Goats', headers: goatHeaders, rows: goatRows(exportGoats.value) },
        ])
    }
}

</script>

<template>
    <section class="grid gap-4">
        <div class="grid grid-cols-2 gap-3">
            <article class="rounded-3xl border border-line bg-white p-4">
                <p class="text-sm text-stone-600">Farms in view</p>
                <p class="text-3xl font-semibold">{{ filteredFarms.length }}</p>
            </article>
            <article class="rounded-3xl border border-line bg-white p-4">
                <p class="text-sm text-stone-600">Goats in view</p>
                <p class="text-3xl font-semibold">{{ filteredGoats.length }}</p>
            </article>
        </div>
        <div class="grid gap-3 rounded-3xl border border-line bg-white p-4">
            <input v-model="search" class="min-h-12 rounded-2xl border border-line px-4" placeholder="Search Farm ID or Goat ID">
            <div class="grid grid-cols-2 gap-3">
                <select v-model="ward" class="min-h-12 rounded-2xl border border-line px-3">
                    <option value="">All wards</option>
                    <option v-for="number in wards" :key="number" :value="String(number)">Ward {{ number }}</option>
                </select>
                <select v-model="sex" class="min-h-12 rounded-2xl border border-line px-3">
                    <option value="">All sexes</option>
                    <option v-for="item in sexes" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="breed" class="min-h-12 rounded-2xl border border-line px-3">
                    <option value="">All breeds</option>
                    <option v-for="item in breeds" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="feeding" class="min-h-12 rounded-2xl border border-line px-3">
                    <option value="">All feeding systems</option>
                    <option v-for="item in feedingSystems" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="frequency" class="min-h-12 rounded-2xl border border-line px-3">
                    <option value="">All deworming schedules</option>
                    <option v-for="item in farmDewormingFrequencies" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <button type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" @click="exportExcel('farms')">Farm Excel</button>
            <button type="button" class="min-h-12 rounded-2xl border border-line bg-white font-semibold" @click="exportCsv('farms')">Farm CSV</button>
            <button type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" @click="exportExcel('goats')">Goat Excel</button>
            <button type="button" class="min-h-12 rounded-2xl border border-line bg-white font-semibold" @click="exportCsv('goats')">Goat CSV</button>
        </div>
    </section>
</template>
