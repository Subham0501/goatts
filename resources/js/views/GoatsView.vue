<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useLiveQuery } from '../composables/live'
import { deleteGoat, listGoats } from '../services/records'
import { breeds, formatWhen, goatFrequencies, goatMedicines, labelFor, lastDewormingDurations, sexes } from '../data/options'
import ConfirmDialog from '../components/ConfirmDialog.vue'

const route = useRoute()
const search = ref(route.query.farm || '')
const sex = ref('')
const breed = ref('')
const pendingDelete = ref(null)
const goats = useLiveQuery(() => listGoats())
const visible = computed(() => (goats.value || []).filter((goat) => {
    const query = search.value.trim().toLowerCase()
    const matchesSearch = !query || `${goat.goat_code} ${goat.farm_code}`.toLowerCase().includes(query)
    const matchesSex = !sex.value || goat.sex === sex.value
    const matchesBreed = !breed.value || goat.breed === breed.value
    return matchesSearch && matchesSex && matchesBreed
}))

async function confirmDelete() {
    if (!pendingDelete.value) return
    await deleteGoat(pendingDelete.value.uuid)
    pendingDelete.value = null
}
</script>

<template>
    <section>
        <div class="grid gap-3">
            <input v-model="search" class="min-h-12 rounded-2xl border border-line bg-white px-4" placeholder="Search Goat ID or Farm ID">
            <div class="grid grid-cols-2 gap-3">
                <select v-model="sex" class="min-h-12 rounded-2xl border border-line bg-white px-4">
                    <option value="">All sexes</option>
                    <option v-for="item in sexes" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <select v-model="breed" class="min-h-12 rounded-2xl border border-line bg-white px-4">
                    <option value="">All breeds</option>
                    <option v-for="item in breeds" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
        </div>
        <p class="mt-3 text-sm text-stone-600">{{ visible.length }} goat{{ visible.length === 1 ? '' : 's' }}</p>
        <div v-if="!visible.length" class="mt-3 rounded-3xl border border-dashed border-line bg-white p-5 text-sm text-stone-600">No goats match this search.</div>
        <div v-else class="mt-3 grid gap-3">
            <article v-for="goat in visible" :key="goat.uuid" class="rounded-3xl border border-line bg-white p-4">
                <p class="font-semibold">{{ goat.goat_code }}</p>
                <p class="mt-1 text-sm text-stone-600">Farm {{ goat.farm_code }} · {{ goat.age_value || '—' }} {{ goat.age_unit }} · {{ labelFor(sexes, goat.sex) }} · {{ labelFor(breeds, goat.breed) }}</p>
                <p class="mt-1 text-sm text-stone-600">BCS {{ goat.body_condition_score || '—' }} · {{ labelFor(lastDewormingDurations, goat.last_deworming_duration) }} · {{ labelFor(goatMedicines, goat.last_deworming_medicine) }} · {{ labelFor(goatFrequencies, goat.deworming_frequency_last_12_months) }}</p>
                <p class="mt-1 text-sm text-stone-500">{{ formatWhen(goat.created_at) }}</p>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <RouterLink :to="{ name: 'goat-edit', params: { uuid: goat.uuid } }" class="flex min-h-11 items-center justify-center rounded-xl border border-line text-sm font-semibold">Edit</RouterLink>
                    <RouterLink :to="{ name: 'farm-detail', params: { uuid: goat.farm_uuid } }" class="flex min-h-11 items-center justify-center rounded-xl border border-line text-sm font-semibold">Farm</RouterLink>
                    <button type="button" class="min-h-11 rounded-xl bg-clay text-sm font-semibold text-white" @click="pendingDelete = goat">Delete</button>
                </div>
            </article>
        </div>
        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            title="Delete this goat?"
            :message="pendingDelete ? `Delete ${pendingDelete.goat_code}? This cannot be undone.` : ''"
            confirm-label="Delete goat"
            danger
            @cancel="pendingDelete = null"
            @confirm="confirmDelete"
        />
    </section>
</template>
