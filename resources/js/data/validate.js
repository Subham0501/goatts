import { WARD_MAX, WARD_MIN } from './options'

const codePattern = /^[\p{L}\p{N}][\p{L}\p{N}._ -]{0,62}$/u

export function validateFarm(farm, step = null) {
    const errors = {}
    const checks = {
        identity: () => {
            if (!String(farm.farm_code || '').trim()) {
                errors.farm_code = 'Enter a Farm ID.'
            } else if (!codePattern.test(String(farm.farm_code).trim())) {
                errors.farm_code = 'Use letters, numbers, spaces, or hyphens.'
            }
            const ward = Number(farm.ward_number)
            if (!Number.isInteger(ward) || ward < WARD_MIN || ward > WARD_MAX) {
                errors.ward_number = `Enter a Sundarbazar ward from ${WARD_MIN} to ${WARD_MAX}.`
            }
            const total = Number(farm.total_goats)
            if (!Number.isInteger(total) || total < 1) {
                errors.total_goats = 'Enter the total number of goats as a whole number greater than zero.'
            }
        },
        feeding: () => {
            if (!farm.feeding_system) errors.feeding_system = 'Choose the main feeding system.'
        },
        deworming: () => {
            if (!farm.deworming_frequency) errors.deworming_frequency = 'Choose how often the goats are dewormed.'
            if (!farm.common_anthelmintic) errors.common_anthelmintic = 'Choose the medicine that is commonly used.'
            if (farm.common_anthelmintic === 'other' && !String(farm.other_anthelmintic || '').trim()) {
                errors.other_anthelmintic = 'Enter the name of the other medicine.'
            }
        },
        decision: () => {
            if (!farm.schedule_decision_maker) errors.schedule_decision_maker = 'Choose who decides the deworming schedule.'
            if (farm.schedule_decision_maker === 'other' && !String(farm.other_decision_maker || '').trim()) {
                errors.other_decision_maker = 'Describe who makes the decision.'
            }
            if (!farm.dose_determination_method) errors.dose_determination_method = 'Choose how the dose is determined.'
        },
    }

    const order = step && step !== 'review' ? [step] : ['identity', 'feeding', 'deworming', 'decision']
    order.forEach((key) => checks[key]?.())
    return errors
}

export function validateGoat(goat, step = null) {
    const errors = {}
    const checks = {
        identity: () => {
            if (!goat.farm_uuid) errors.farm_uuid = 'This goat is not linked to a farm.'
            if (!String(goat.goat_code || '').trim()) {
                errors.goat_code = 'Enter a Goat ID.'
            } else if (!codePattern.test(String(goat.goat_code).trim())) {
                errors.goat_code = 'Use letters, numbers, spaces, or hyphens.'
            }
            if (goat.age_value === '' || goat.age_value === null || goat.age_value === undefined) {
                errors.age_value = 'Enter the age.'
            }
            const age = Number(goat.age_value)
            if (!errors.age_value && (!Number.isInteger(age) || age < 0 || age > 360)) {
                errors.age_value = 'Enter age as a whole number from 0 to 360.'
            }
            if (!['months', 'years'].includes(goat.age_unit)) errors.age_unit = 'Choose months or years.'
            if (goat.age_unit === 'years' && age > 30) errors.age_value = 'Age in years cannot be more than 30. Use months for young goats.'
            if (!goat.sex) errors.sex = 'Choose the sex of this goat.'
        },
        condition: () => {
            if (!goat.breed) errors.breed = 'Choose the breed.'
            if (goat.breed === 'other' && !String(goat.other_breed || '').trim()) {
                errors.other_breed = 'Enter the breed details.'
            }
            const score = Number(goat.body_condition_score)
            if (!Number.isInteger(score) || score < 1 || score > 5) {
                errors.body_condition_score = 'Choose a body condition score from 1 to 5.'
            }
        },
        history: () => {
            if (!goat.last_deworming_duration) errors.last_deworming_duration = 'Choose when this goat was last dewormed.'
            if (!goat.last_deworming_medicine) errors.last_deworming_medicine = 'Choose the medicine used at the last deworming.'
            if (goat.last_deworming_medicine === 'other' && !String(goat.other_last_deworming_medicine || '').trim()) {
                errors.other_last_deworming_medicine = 'Enter the name of the other medicine.'
            }
            if (!goat.deworming_frequency_last_12_months) {
                errors.deworming_frequency_last_12_months = 'Choose how many times this goat was dewormed in the last 12 months.'
            }
        },
    }

    const order = step && step !== 'review' ? [step] : ['identity', 'condition', 'history']
    order.forEach((key) => checks[key]?.())
    return errors
}

export function blankFarm(uuid) {
    const now = new Date().toISOString()
    return {
        uuid,
        farm_code: '',
        ward_number: '',
        total_goats: '',
        feeding_system: '',
        deworming_frequency: '',
        common_anthelmintic: '',
        other_anthelmintic: '',
        schedule_decision_maker: '',
        other_decision_maker: '',
        dose_determination_method: '',
        code_custom: false,
        draft_step: 0,
        sync_status: 'local',
        sync_error: null,
        base_updated_at: null,
        server_updated_at: null,
        server_id: null,
        server_version: null,
        pending_resolution: null,
        last_sync_at: null,
        deleted_at: null,
        created_at: now,
        updated_at: now,
    }
}

export function blankGoat(uuid, farm, goatCode) {
    const now = new Date().toISOString()
    return {
        uuid,
        farm_uuid: farm.uuid,
        farm_code: farm.farm_code,
        goat_code: goatCode,
        age_value: '',
        age_unit: 'months',
        sex: '',
        breed: '',
        other_breed: '',
        body_condition_score: null,
        last_deworming_duration: '',
        last_deworming_medicine: '',
        other_last_deworming_medicine: '',
        deworming_frequency_last_12_months: '',
        code_custom: false,
        draft_step: 0,
        sync_status: 'local',
        sync_error: null,
        base_updated_at: null,
        server_updated_at: null,
        server_id: null,
        server_version: null,
        pending_resolution: null,
        last_sync_at: null,
        deleted_at: null,
        created_at: now,
        updated_at: now,
    }
}

export function farmIsBlank(farm) {
    return !farm.ward_number && !farm.total_goats && !farm.feeding_system && !farm.deworming_frequency
        && !farm.common_anthelmintic && !farm.schedule_decision_maker && !farm.dose_determination_method
        && !farm.code_custom
}

export function goatIsBlank(goat) {
    return !goat.age_value && !goat.sex && !goat.breed && !goat.body_condition_score
        && !goat.last_deworming_duration && !goat.last_deworming_medicine
        && !goat.deworming_frequency_last_12_months && !goat.code_custom
}
