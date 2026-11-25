<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLiveQuery } from '../composables/live'
import { db } from '../db'
import { deleteFarm, getFarm, goatsForFarm, persistGoat } from '../services/records'
import { blankGoat } from '../data/validate'
import { createGoatCode, createUuid } from '../data/ids'
import {
    decisionMakers,
    doseMethods,
    farmAnthelmintics,
    farmDewormingFrequencies,
    feedingSystems,
    formatWhen,
    labelFor,
} from '../data/options'
import ConfirmDialog from '../components/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const confirmOpen = ref(false)
const farm = useLiveQuery(() => getFarm(route.params.uuid))
const goats = useLiveQuery(() => goatsForFarm(route.params.uuid))
const rows = computed(() => {
    if (!farm.value) return []
    return [
        ['Ward', farm.value.ward_number],
        ['Total goats', farm.value.total_goats],
        ['Sampled goats', goats.value?.length ?? 0],
        ['Feeding', labelFor(feedingSystems, farm.value.feeding_system)],
        ['Deworming frequency', labelFor(farmDewormingFrequencies, farm.value.deworming_frequency)],
        ['Common medicine', farm.value.common_anthelmintic === 'other' ? farm.value.other_anthelmintic : labelFor(farmAnthelmintics, farm.value.common_anthelmintic)],
        ['Schedule decided by', farm.value.schedule_decision_maker === 'other' ? farm.value.other_decision_maker : labelFor(decisionMakers, farm.value.schedule_decision_maker)],
        ['Dose method', labelFor(doseMethods, farm.value.dose_determination_method)],
        ['Survey date', formatWhen(farm.value.created_at)],
    ]
})

async function addGoat() {
    if (!farm.value) return
    const count = await db.goats.where('farm_uuid').equals(farm.value.uuid).count()
    const uuid = createUuid()
    const goat = blankGoat(uuid, farm.value, createGoatCode(farm.value.farm_code, count + 1))
    await persistGoat(goat, 'draft')
    router.push({ name: 'goat-edit', params: { uuid } })
}

async function removeFarm() {
    await deleteFarm(route.params.uuid)
    confirmOpen.value = false
    router.push('/records')
}
</script>

<template>
    <section v-if="farm && !farm.deleted_at">
        <p class="text-sm text-stone-600">Farm ID</p>
        <h2 class="text-2xl font-semibold">{{ farm.farm_code }}</h2>
        <dl class="mt-4 grid gap-3 rounded-3xl border border-line bg-white p-4 text-sm">
            <div v-for="row in rows" :key="row[0]" class="flex justify-between gap-4 border-b border-line pb-2 last:border-0">
                <dt class="text-stone-600">{{ row[0] }}</dt>
                <dd class="text-right font-medium">{{ row[1] || '—' }}</dd>
            </div>
        </dl>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <RouterLink :to="{ name: 'farm-edit', params: { uuid: farm.uuid } }" class="flex min-h-12 items-center justify-center rounded-2xl border border-line bg-white font-semibold">
                Edit farm
            </RouterLink>
            <button type="button" class="min-h-12 rounded-2xl bg-clay font-semibold text-white" @click="confirmOpen = true">Delete</button>
        </div>
        <div class="mt-8 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold">Sampled goats</h3>
            <button type="button" class="min-h-11 rounded-2xl bg-leaf px-4 font-semibold text-white" @click="addGoat">Add goat</button>
        </div>
        <p v-if="!goats?.length" class="mt-3 rounded-3xl border border-dashed border-line bg-white p-5 text-sm text-stone-600">
            No goats have been added. The farm record is already saved.
        </p>
        <div v-else class="mt-3 grid gap-3">
            <RouterLink
                v-for="goat in goats"
                :key="goat.uuid"
                :to="{ name: 'goat-edit', params: { uuid: goat.uuid } }"
                class="rounded-3xl border border-line bg-white p-4"
            >
                <p class="font-semibold">{{ goat.goat_code }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ goat.sex || 'Sex not set' }} · BCS {{ goat.body_condition_score || '—' }}</p>
            </RouterLink>
        </div>
        <ConfirmDialog
            :open="confirmOpen"
            title="Delete this farm?"
            :message="`Delete ${farm.farm_code} and its ${goats?.length || 0} sampled goats? This cannot be undone.`"
            confirm-label="Delete farm"
            danger
            @cancel="confirmOpen = false"
            @confirm="removeFarm"
        />
    </section>
    <p v-else class="text-sm text-stone-600">This farm could not be found.</p>
</template>
