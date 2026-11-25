import Dexie from 'dexie'

export const db = new Dexie('goat-health-survey')

db.version(1).stores({
    farms: 'uuid, farm_code, ward_number, sync_status, updated_at, deleted_at',
    goats: 'uuid, farm_uuid, goat_code, sex, breed, sync_status, updated_at, deleted_at',
    syncQueue: '++id, &[entity+uuid], entity, uuid, status, created_at',
    appSettings: 'key',
})

export async function askPersistentStorage() {
    if (!navigator.storage?.persist) return false
    try {
        return await navigator.storage.persist()
    } catch {
        return false
    }
}

export async function storageEstimate() {
    if (!navigator.storage?.estimate) return null
    try {
        const estimate = await navigator.storage.estimate()
        const persisted = navigator.storage.persisted ? await navigator.storage.persisted() : false
        return {
            persisted,
            usage: estimate.usage || 0,
            quota: estimate.quota || 0,
        }
    } catch {
        return null
    }
}
