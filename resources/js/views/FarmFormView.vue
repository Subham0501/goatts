<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import ChoiceCards from '../components/ChoiceCards.vue'
import TextField from '../components/TextField.vue'
import {
    decisionMakers,
    doseMethods,
    farmAnthelmintics,
    farmDewormingFrequencies,
    feedingSystems,
} from '../data/options'
import { farmIsBlank, validateFarm } from '../data/validate'
import { deleteFarm, getFarm, persistFarm } from '../services/records'
import { useSyncStore } from '../stores/sync'
import { useUiStore } from '../stores/ui'

const route = useRoute()
const router = useRouter()
const sync = useSyncStore()
const ui = useUiStore()
const step = ref(0)
const ready = ref(false)
const saving = ref(false)
const errors = ref({})
const farm = reactive({})
let hydrating = false
let timer = null

const steps = [
    { key: 'identity', title: 'Farm identity', hint: 'One Section A record is saved for the whole farm.' },
    { key: 'feeding', title: 'Main feeding system', hint: 'Choose the system used for most of the goats.' },
    { key: 'deworming', title: 'Deworming practice', hint: 'These answers describe the farm’s usual routine.' },
    { key: 'decision', title: 'Schedule and dose', hint: 'Who decides, and how is the dose chosen?' },
    { key: 'review', title: 'Review farm', hint: 'The farm can be saved before any goat is added.' },
]

onMounted(async () => {
    const existing = await getFarm(route.params.uuid)
    if (!existing || existing.deleted_at) {
        router.replace('/records')
        return
    }
    hydrating = true
    Object.assign(farm, existing)
    step.value = Number(existing.draft_step) || 0
    hydrating = false
    ready.value = true
})

watch(farm, () => {
    if (!ready.value || hydrating) return
    clearTimeout(timer)
    timer = setTimeout(() => saveDraft(), 500)
}, { deep: true })

onBeforeRouteLeave(async () => {
    clearTimeout(timer)
    if (ready.value && farmIsBlank(farm) && farm.sync_status === 'local' && !farm.base_updated_at) {
        await deleteFarm(farm.uuid)
    }
})

async function saveDraft(silent = true) {
    if (!farm.uuid || hydrating) return
    hydrating = true
    try {
        const saved = await persistFarm({ ...farm, draft_step: step.value }, 'draft')
        farm.sync_status = saved.sync_status
        farm.updated_at = saved.updated_at
        if (!silent) ui.flash('Draft saved.')
    } finally {
        hydrating = false
    }
}

function next() {
    errors.value = validateFarm(farm, steps[step.value].key)
    if (Object.keys(errors.value).length) return
    step.value += 1
    farm.draft_step = step.value
}

function previous() {
    errors.value = {}
    step.value = Math.max(0, step.value - 1)
    farm.draft_step = step.value
}

async function submit() {
    errors.value = validateFarm(farm, 'review')
    if (Object.keys(errors.value).length) {
        ui.flash('Complete the highlighted answers before saving.', 'error')
        step.value = 0
        return
    }
    saving.value = true
    try {
        await persistFarm({ ...farm, draft_step: steps.length - 1 }, 'queue')
        ui.flash('Farm saved.')
        sync.nudge()
        router.push({ name: 'farm-detail', params: { uuid: farm.uuid } })
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <form v-if="ready" class="grid gap-4" @submit.prevent>
        <div>
            <div class="mb-2 flex items-center justify-between text-sm text-stone-600">
                <span>Step {{ step + 1 }} of {{ steps.length }}</span>
                <span>{{ farm.farm_code }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-stone-200">
                <div class="h-full bg-leaf" :style="{ width: `${((step + 1) / steps.length) * 100}%` }" />
            </div>
        </div>
        <section class="rounded-3xl border border-line bg-white p-4">
            <h2 class="text-xl font-semibold">{{ steps[step].title }}</h2>
            <p class="mt-1 text-sm leading-6 text-stone-600">{{ steps[step].hint }}</p>
            <div v-if="steps[step].key === 'identity'" class="mt-4 grid gap-4">
                <TextField
                    v-model="farm.farm_code"
                    label="Farm ID"
                    hint="Numbered in order: 1, 2, 3. You can edit it if needed."
                    :error="errors.farm_code"
                    @input="farm.code_custom = true"
                />
                <TextField v-model="farm.ward_number" label="Ward number" inputmode="numeric" hint="Sundarbazar Municipality wards are numbered 1 to 11." :error="errors.ward_number" />
                <TextField v-model="farm.total_goats" label="Total number of goats in the farm" inputmode="numeric" :error="errors.total_goats" />
            </div>
            <div v-else-if="steps[step].key === 'feeding'" class="mt-4">
                <ChoiceCards v-model="farm.feeding_system" name="feeding" :options="feedingSystems" :error="errors.feeding_system" />
            </div>
            <div v-else-if="steps[step].key === 'deworming'" class="mt-4 grid gap-4">
                <div>
                    <p class="mb-2 text-sm font-semibold">How frequently do you deworm your goats?</p>
                    <ChoiceCards v-model="farm.deworming_frequency" name="frequency" :options="farmDewormingFrequencies" :error="errors.deworming_frequency" />
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold">Which anthelmintic medicine do you commonly use?</p>
                    <ChoiceCards v-model="farm.common_anthelmintic" name="medicine" :options="farmAnthelmintics" :error="errors.common_anthelmintic" />
                </div>
                <TextField v-if="farm.common_anthelmintic === 'other'" v-model="farm.other_anthelmintic" label="Other medicine" :error="errors.other_anthelmintic" />
            </div>
            <div v-else-if="steps[step].key === 'decision'" class="mt-4 grid gap-4">
                <div>
                    <p class="mb-2 text-sm font-semibold">Who generally decides the deworming schedule?</p>
                    <ChoiceCards v-model="farm.schedule_decision_maker" name="decision" :options="decisionMakers" :error="errors.schedule_decision_maker" />
                </div>
                <TextField v-if="farm.schedule_decision_maker === 'other'" v-model="farm.other_decision_maker" label="Other decision maker" :error="errors.other_decision_maker" />
                <div>
                    <p class="mb-2 text-sm font-semibold">How do you determine the dose of deworming medicine?</p>
                    <ChoiceCards v-model="farm.dose_determination_method" name="dose" :options="doseMethods" :error="errors.dose_determination_method" />
                </div>
            </div>
            <dl v-else class="mt-4 grid gap-3 text-sm">
                <div v-for="row in [
                    ['Farm ID', farm.farm_code],
                    ['Ward', farm.ward_number],
                    ['Total goats', farm.total_goats],
                    ['Feeding', feedingSystems.find((item) => item.value === farm.feeding_system)?.label],
                    ['Deworming frequency', farmDewormingFrequencies.find((item) => item.value === farm.deworming_frequency)?.label],
                    ['Medicine', farm.common_anthelmintic === 'other' ? farm.other_anthelmintic : farmAnthelmintics.find((item) => item.value === farm.common_anthelmintic)?.label],
                    ['Schedule decided by', farm.schedule_decision_maker === 'other' ? farm.other_decision_maker : decisionMakers.find((item) => item.value === farm.schedule_decision_maker)?.label],
                    ['Dose method', doseMethods.find((item) => item.value === farm.dose_determination_method)?.label],
                ]" :key="row[0]" class="flex justify-between gap-4 border-b border-line pb-2">
                    <dt class="text-stone-600">{{ row[0] }}</dt>
                    <dd class="text-right font-medium">{{ row[1] || '—' }}</dd>
                </div>
            </dl>
        </section>
        <div class="sticky bottom-[calc(4.25rem+env(safe-area-inset-bottom))] z-20 grid grid-cols-2 gap-3 bg-paper/95 py-2">
            <button type="button" class="min-h-12 rounded-2xl border border-line bg-white font-semibold" :disabled="step === 0" @click="previous">Previous</button>
            <button v-if="step < steps.length - 1" type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" @click="next">Next</button>
            <button v-else type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" :disabled="saving" @click="submit">Save farm</button>
            <button type="button" class="col-span-2 min-h-12 rounded-2xl border border-line bg-white font-semibold" @click="saveDraft(false)">Save draft</button>
        </div>
    </form>
</template>
