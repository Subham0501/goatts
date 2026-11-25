import { api } from './api'
import {
    ageUnits,
    bodyConditions,
    breeds,
    decisionMakers,
    doseMethods,
    farmAnthelmintics,
    farmDewormingFrequencies,
    feedingSystems,
    goatFrequencies,
    goatMedicines,
    labelFor,
    lastDewormingDurations,
    sexes,
    surveyDate,
} from '../data/options'

const farmHeaders = [
    'farm_code', 'farm_uuid', 'ward_number', 'total_goats', 'sampled_goats',
    'feeding_system', 'feeding_system_label', 'deworming_frequency', 'deworming_frequency_label',
    'common_anthelmintic', 'common_anthelmintic_label', 'other_anthelmintic',
    'schedule_decision_maker', 'schedule_decision_maker_label', 'other_decision_maker',
    'dose_determination_method', 'dose_determination_method_label',
    'sync_status', 'created_at', 'survey_date_npt', 'updated_at',
]

const goatHeaders = [
    'goat_code', 'goat_uuid', 'farm_code', 'farm_uuid', 'ward_number',
    'age_value', 'age_unit', 'sex', 'sex_label', 'breed', 'breed_label', 'other_breed',
    'body_condition_score', 'last_deworming_duration', 'last_deworming_duration_label',
    'last_deworming_medicine', 'last_deworming_medicine_label', 'other_last_deworming_medicine',
    'deworming_frequency_last_12_months', 'deworming_frequency_last_12_months_label',
    'farm_total_goats', 'farm_feeding_system', 'farm_feeding_system_label',
    'farm_deworming_frequency', 'farm_deworming_frequency_label', 'farm_common_anthelmintic',
    'sync_status', 'created_at', 'survey_date_npt', 'updated_at',
]

export function farmRows(farms, goats) {
    const sampled = new Map()
    for (const goat of goats) {
        if (goat.deleted_at) continue
        sampled.set(goat.farm_uuid, (sampled.get(goat.farm_uuid) || 0) + 1)
    }
    return farms.filter((farm) => !farm.deleted_at).map((farm) => [
        farm.farm_code,
        farm.uuid,
        farm.ward_number,
        farm.total_goats,
        sampled.get(farm.uuid) || 0,
        farm.feeding_system,
        labelFor(feedingSystems, farm.feeding_system),
        farm.deworming_frequency,
        labelFor(farmDewormingFrequencies, farm.deworming_frequency),
        farm.common_anthelmintic,
        labelFor(farmAnthelmintics, farm.common_anthelmintic),
        farm.other_anthelmintic,
        farm.schedule_decision_maker,
        labelFor(decisionMakers, farm.schedule_decision_maker),
        farm.other_decision_maker,
        farm.dose_determination_method,
        labelFor(doseMethods, farm.dose_determination_method),
        farm.sync_status,
        farm.created_at,
        surveyDate(farm.created_at),
        farm.updated_at,
    ])
}

export function goatRows(goats) {
    return goats.filter((goat) => !goat.deleted_at).map((goat) => [
        goat.goat_code,
        goat.uuid,
        goat.farm_code,
        goat.farm_uuid,
        goat.ward_number,
        goat.age_value,
        labelFor(ageUnits, goat.age_unit),
        goat.sex,
        labelFor(sexes, goat.sex),
        goat.breed,
        labelFor(breeds, goat.breed),
        goat.other_breed,
        goat.body_condition_score,
        goat.last_deworming_duration,
        labelFor(lastDewormingDurations, goat.last_deworming_duration),
        goat.last_deworming_medicine,
        labelFor(goatMedicines, goat.last_deworming_medicine),
        goat.other_last_deworming_medicine,
        goat.deworming_frequency_last_12_months,
        labelFor(goatFrequencies, goat.deworming_frequency_last_12_months),
        goat.farm_total_goats,
        goat.farm_feeding_system,
        labelFor(feedingSystems, goat.farm_feeding_system),
        goat.farm_deworming_frequency,
        labelFor(farmDewormingFrequencies, goat.farm_deworming_frequency),
        goat.farm_common_anthelmintic,
        goat.sync_status,
        goat.created_at,
        surveyDate(goat.created_at),
        goat.updated_at,
    ])
}

export function toCsv(headers, rows) {
    const lines = [headers, ...rows].map((row) => row.map(csvCell).join(','))
    return `\uFEFF${lines.join('\n')}`
}

function csvCell(value) {
    const text = value === null || value === undefined ? '' : String(value)
    if (/[",\n]/.test(text)) return `"${text.replace(/"/g, '""')}"`
    return text
}

export function downloadText(filename, contents, type) {
    const blob = new Blob([contents], { type })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
}

export async function downloadWorkbook(filename, sheets) {
    const XLSX = await import('xlsx')
    const book = XLSX.utils.book_new()
    for (const sheet of sheets) {
        const data = [sheet.headers, ...sheet.rows]
        const worksheet = XLSX.utils.aoa_to_sheet(data)
        XLSX.utils.book_append_sheet(book, worksheet, sheet.name)
    }
    XLSX.writeFile(book, filename)
}

export async function downloadServerExport(path, filename) {
    const response = await api.get(path, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
}

export function scopeRows(records, scope) {
    if (scope === 'synced') return records.filter((record) => record.sync_status === 'synced')
    if (scope === 'pending') return records.filter((record) => record.sync_status !== 'synced')
    return records
}

export { farmHeaders, goatHeaders }
