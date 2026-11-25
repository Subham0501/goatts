export const WARD_MIN = 1
export const WARD_MAX = 11

export const feedingSystems = [
    { value: 'stall_feeding', label: 'Stall feeding' },
    { value: 'grazing', label: 'Grazing' },
    { value: 'semi_intensive', label: 'Semi-intensive' },
]

export const farmDewormingFrequencies = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'every_2_3_months', label: 'Every 2–3 months' },
    { value: 'every_4_6_months', label: 'Every 4–6 months' },
    { value: 'once_a_year', label: 'Once a year' },
    { value: 'only_when_necessary', label: 'Only when necessary' },
    { value: 'no_fixed_schedule', label: 'No fixed schedule' },
]

export const farmAnthelmintics = [
    { value: 'albendazole', label: 'Albendazole' },
    { value: 'fenbendazole', label: 'Fenbendazole' },
    { value: 'ivermectin', label: 'Ivermectin' },
    { value: 'levamisole', label: 'Levamisole' },
    { value: 'other', label: 'Other' },
    { value: 'dont_know', label: "Don't know" },
]

export const decisionMakers = [
    { value: 'farmer', label: 'Farmer' },
    { value: 'veterinarian', label: 'Veterinarian/livestock technician' },
    { value: 'agro_vet', label: 'Agro-vet' },
    { value: 'other', label: 'Other' },
]

export const doseMethods = [
    { value: 'actual_body_weight', label: 'According to actual body weight' },
    { value: 'estimated_body_weight', label: 'According to estimated body weight' },
    { value: 'according_to_age', label: 'According to age' },
    { value: 'previous_experience', label: 'Based on previous experience' },
    { value: 'professional_advice', label: 'Professional advice' },
]

export const ageUnits = [
    { value: 'months', label: 'Months' },
    { value: 'years', label: 'Years' },
]

export const sexes = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
]

export const breeds = [
    { value: 'local', label: 'Local' },
    { value: 'crossbred', label: 'Crossbred' },
    { value: 'other', label: 'Other' },
]

export const bodyConditions = [
    { value: 1, label: '1', hint: 'Emaciated. Spine, ribs and hip bones are sharp and easy to see.' },
    { value: 2, label: '2', hint: 'Thin. The spine is prominent and there is little muscle or fat cover.' },
    { value: 3, label: '3', hint: 'Moderate. The spine is felt with light pressure and the body looks smooth.' },
    { value: 4, label: '4', hint: 'Fat. The spine is hard to feel and the body looks rounded.' },
    { value: 5, label: '5', hint: 'Obese. The spine is buried in fat and the body looks very rounded.' },
]

export const lastDewormingDurations = [
    { value: 'within_1_month', label: 'Within 1 month' },
    { value: '1_3_months', label: '1–3 months' },
    { value: '3_6_months', label: '3–6 months' },
    { value: 'more_than_6_months', label: 'More than 6 months' },
    { value: 'never', label: 'Never dewormed' },
    { value: 'unknown', label: 'Unknown' },
]

export const goatMedicines = [
    { value: 'albendazole', label: 'Albendazole' },
    { value: 'fenbendazole', label: 'Fenbendazole' },
    { value: 'ivermectin', label: 'Ivermectin' },
    { value: 'levamisole', label: 'Levamisole' },
    { value: 'other', label: 'Other' },
    { value: 'unknown', label: 'Unknown' },
]

export const goatFrequencies = [
    { value: 'once', label: 'Once' },
    { value: 'twice', label: 'Twice' },
    { value: 'three_times', label: 'Three times' },
    { value: 'more_than_three', label: 'More than three times' },
    { value: 'never', label: 'Never' },
    { value: 'unknown', label: 'Unknown' },
]

export const syncLabels = {
    local: 'Saved on device',
    pending: 'Waiting to sync',
    syncing: 'Syncing',
    synced: 'Synced',
    failed: 'Sync failed',
    conflict: 'Needs review',
}

export function labelFor(list, value) {
    const found = list.find((item) => String(item.value) === String(value))
    return found?.label || (value ? String(value) : '—')
}

export function formatWhen(iso) {
    if (!iso) return '—'
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return '—'
    return date.toLocaleString('en-GB', {
        timeZone: 'Asia/Kathmandu',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

export function surveyDate(iso) {
    if (!iso) return ''
    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return ''
    return date.toLocaleDateString('en-CA', { timeZone: 'Asia/Kathmandu' })
}
