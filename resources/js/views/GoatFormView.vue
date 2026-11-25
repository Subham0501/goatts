<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import ChoiceCards from '../components/ChoiceCards.vue'
import TextField from '../components/TextField.vue'
import {
    ageUnits,
    bodyConditions,
    breeds,
    goatFrequencies,
    goatMedicines,
    labelFor,
    lastDewormingDurations,
    sexes,
} from '../data/options'
import { blankGoat, goatIsBlank, validateGoat } from '../data/validate'
import { createGoatCode, createUuid } from '../data/ids'
import { db } from '../db'
import { deleteGoat, getFarm, getGoat, persistGoat } from '../services/records'
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
const goat = reactive({})
const selectedScore = computed(() => bodyConditions.find((item) => String(item.value) === String(goat.body_condition_score)))
let hydrating = false
let timer = null

const steps = [
    { key: 'identity', title: 'Goat identity', hint: 'This record belongs only to this sampled goat.' },
    { key: 'condition', title: 'Breed and condition', hint: 'Score the body condition you can see and feel.' },
    { key: 'history', title: 'Deworming history', hint: 'These answers are stored on the goat, not the farm.' },
    { key: 'review', title: 'Review goat', hint: 'Check this goat before saving.' },
]

onMounted(async () => {
    const existing = await getGoat(route.params.uuid)
    if (!existing || existing.deleted_at) {
        router.replace('/goats')
        return
    }
    const farm = await getFarm(existing.farm_uuid)
    hydrating = true
    Object.assign(goat, existing, { farm_code: farm?.farm_code || existing.farm_code })
    step.value = Number(existing.draft_step) || 0
    hydrating = false
    ready.value = true
})

watch(goat, () => {
    if (!ready.value || hydrating) return
    clearTimeout(timer)
    timer = setTimeout(() => saveDraft(), 500)
}, { deep: true })

onBeforeRouteLeave(async () => {
    clearTimeout(timer)
    if (ready.value && goatIsBlank(goat) && goat.sync_status === 'local' && !goat.base_updated_at) {
        await deleteGoat(goat.uuid)
    }
})

async function saveDraft(silent = true) {
    if (!goat.uuid || hydrating) return
    hydrating = true
    try {
        const saved = await persistGoat({ ...goat, draft_step: step.value }, 'draft')
        goat.sync_status = saved.sync_status
        goat.updated_at = saved.updated_at
        if (!silent) ui.flash(sync.online === false ? 'Draft saved on this phone.' : 'Draft saved.')
    } finally {
        hydrating = false
    }
}

function next() {
    errors.value = validateGoat(goat, steps[step.value].key)
    if (Object.keys(errors.value).length) return
    step.value += 1
    goat.draft_step = step.value
}

function previous() {
    errors.value = {}
    step.value = Math.max(0, step.value - 1)
    goat.draft_step = step.value
}

async function submit(addAnother = false) {
    errors.value = validateGoat(goat, 'review')
    if (Object.keys(errors.value).length) {
        ui.flash('Complete the highlighted answers before saving.', 'error')
        step.value = 0
        return
    }
    saving.value = true
    try {
        await persistGoat({ ...goat, draft_step: steps.length - 1 }, 'queue')
        ui.flash(sync.online === false ? 'Goat saved on this phone. It will send when internet is back.' : 'Goat saved.')
        sync.nudge()
        if (addAnother) {
            const farm = await getFarm(goat.farm_uuid)
            if (!farm) {
                ui.flash('Save the farm before adding this goat.', 'error')
                return
            }
            const count = await db.goats.where('farm_uuid').equals(goat.farm_uuid).count()
            const uuid = createUuid()
            await persistGoat(blankGoat(uuid, farm, createGoatCode(farm.farm_code, count + 1)), 'draft')
            router.push({ name: 'goat-edit', params: { uuid } })
        } else {
            router.push({ name: 'farm-detail', params: { uuid: goat.farm_uuid } })
        }
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
                <RouterLink :to="{ name: 'farm-detail', params: { uuid: goat.farm_uuid } }" class="font-semibold text-leaf">{{ goat.farm_code }}</RouterLink>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-stone-200">
                <div class="h-full bg-leaf" :style="{ width: `${((step + 1) / steps.length) * 100}%` }" />
            </div>
        </div>
        <section class="rounded-3xl border border-line bg-white p-4">
            <h2 class="text-xl font-semibold">{{ steps[step].title }}</h2>
            <p class="mt-1 text-sm leading-6 text-stone-600">{{ steps[step].hint }}</p>
            <div v-if="steps[step].key === 'identity'" class="mt-4 grid gap-4">
                <TextField v-model="goat.goat_code" label="Goat ID" hint="Linked to the farm number, such as 1-G01." :error="errors.goat_code" @input="goat.code_custom = true" />
                <TextField v-model="goat.age_value" label="Age" inputmode="numeric" :error="errors.age_value" />
                <div>
                    <p class="mb-2 text-sm font-semibold">Age unit</p>
                    <ChoiceCards v-model="goat.age_unit" name="age-unit" :options="ageUnits" :error="errors.age_unit" />
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold">Sex</p>
                    <ChoiceCards v-model="goat.sex" name="sex" :options="sexes" :error="errors.sex" />
                </div>
            </div>
            <div v-else-if="steps[step].key === 'condition'" class="mt-4 grid gap-4">
                <div>
                    <p class="mb-2 text-sm font-semibold">Breed</p>
                    <ChoiceCards v-model="goat.breed" name="breed" :options="breeds" :error="errors.breed" />
                </div>
                <TextField v-if="goat.breed === 'other'" v-model="goat.other_breed" label="Breed details" :error="errors.other_breed" />
                <div>
                    <p class="mb-2 text-sm font-semibold">Body condition score (1–5)</p>
                    <ChoiceCards v-model="goat.body_condition_score" name="bcs" :options="bodyConditions" :error="errors.body_condition_score" />
                    <p v-if="selectedScore" class="mt-3 rounded-2xl bg-emerald-50 px-3 py-2 text-sm text-emerald-950">{{ selectedScore.hint }}</p>
                </div>
                <details class="rounded-2xl bg-stone-50 px-3 py-2 text-sm text-stone-700">
                    <summary class="cursor-pointer font-semibold">Body condition guide</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li v-for="item in bodyConditions" :key="item.value">{{ item.hint }}</li>
                    </ol>
                </details>
            </div>
            <div v-else-if="steps[step].key === 'history'" class="mt-4 grid gap-4">
                <div>
                    <p class="mb-2 text-sm font-semibold">How long ago was this goat last dewormed?</p>
                    <ChoiceCards v-model="goat.last_deworming_duration" name="duration" :options="lastDewormingDurations" :error="errors.last_deworming_duration" />
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold">Medicine used during the last deworming</p>
                    <ChoiceCards v-model="goat.last_deworming_medicine" name="last-medicine" :options="goatMedicines" :error="errors.last_deworming_medicine" />
                </div>
                <TextField v-if="goat.last_deworming_medicine === 'other'" v-model="goat.other_last_deworming_medicine" label="Other medicine" :error="errors.other_last_deworming_medicine" />
                <div>
                    <p class="mb-2 text-sm font-semibold">Times dewormed during the last 12 months</p>
                    <ChoiceCards v-model="goat.deworming_frequency_last_12_months" name="year-frequency" :options="goatFrequencies" :error="errors.deworming_frequency_last_12_months" />
                </div>
            </div>
            <dl v-else class="mt-4 grid gap-3 text-sm">
                <div v-for="row in [
                    ['Goat ID', goat.goat_code],
                    ['Farm ID', goat.farm_code],
                    ['Age', `${goat.age_value || '—'} ${labelFor(ageUnits, goat.age_unit)}`],
                    ['Sex', labelFor(sexes, goat.sex)],
                    ['Breed', goat.breed === 'other' ? goat.other_breed : labelFor(breeds, goat.breed)],
                    ['Body condition', goat.body_condition_score],
                    ['Last dewormed', labelFor(lastDewormingDurations, goat.last_deworming_duration)],
                    ['Medicine', goat.last_deworming_medicine === 'other' ? goat.other_last_deworming_medicine : labelFor(goatMedicines, goat.last_deworming_medicine)],
                    ['Treatments in 12 months', labelFor(goatFrequencies, goat.deworming_frequency_last_12_months)],
                ]" :key="row[0]" class="flex justify-between gap-4 border-b border-line pb-2">
                    <dt class="text-stone-600">{{ row[0] }}</dt>
                    <dd class="text-right font-medium">{{ row[1] || '—' }}</dd>
                </div>
            </dl>
        </section>
        <div class="sticky bottom-[calc(4.25rem+env(safe-area-inset-bottom))] z-20 grid grid-cols-2 gap-3 bg-paper/95 py-2">
            <button type="button" class="min-h-12 rounded-2xl border border-line bg-white font-semibold" :disabled="step === 0" @click="previous">Previous</button>
            <button v-if="step < steps.length - 1" type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" @click="next">Next</button>
            <button v-else type="button" class="min-h-12 rounded-2xl bg-leaf font-semibold text-white" :disabled="saving" @click="submit(false)">Save goat</button>
            <button v-if="step === steps.length - 1" type="button" class="col-span-2 min-h-12 rounded-2xl border border-line bg-white font-semibold" @click="submit(true)">Save and add another</button>
            <button type="button" class="col-span-2 min-h-12 rounded-2xl border border-line bg-white font-semibold" @click="saveDraft(false)">Save draft</button>
        </div>
    </form>
</template>
