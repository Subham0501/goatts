import { db } from '../db'
import { isNumberedFarmCode, nextFarmCode } from '../data/ids'

const FARM_FIELDS = [
    'uuid', 'farm_code', 'ward_number', 'total_goats', 'feeding_system', 'deworming_frequency',
    'common_anthelmintic', 'other_anthelmintic', 'schedule_decision_maker', 'other_decision_maker',
    'dose_determination_method', 'created_at', 'updated_at', 'deleted_at', 'base_updated_at',
]

const GOAT_FIELDS = [
    'uuid', 'farm_uuid', 'goat_code', 'age_value', 'age_unit', 'sex', 'breed', 'other_breed',
    'body_condition_score', 'last_deworming_duration', 'last_deworming_medicine',
    'other_last_deworming_medicine', 'deworming_frequency_last_12_months',
    'created_at', 'updated_at', 'deleted_at', 'base_updated_at',
]

function numberOrNull(value) {
    if (value === '' || value === null || value === undefined) return null
    const number = Number(value)
    return Number.isFinite(number) ? number : null
}

export function normalizeFarm(farm) {
    return {
        ...farm,
        farm_code: String(farm.farm_code || '').trim(),
        ward_number: numberOrNull(farm.ward_number),
        total_goats: numberOrNull(farm.total_goats),
        other_anthelmintic: farm.common_anthelmintic === 'other' ? String(farm.other_anthelmintic || '').trim() || null : null,
        other_decision_maker: farm.schedule_decision_maker === 'other' ? String(farm.other_decision_maker || '').trim() || null : null,
    }
}

export function normalizeGoat(goat) {
    return {
        ...goat,
        goat_code: String(goat.goat_code || '').trim(),
        age_value: numberOrNull(goat.age_value),
        body_condition_score: numberOrNull(goat.body_condition_score),
        other_breed: goat.breed === 'other' ? String(goat.other_breed || '').trim() || null : null,
        other_last_deworming_medicine: goat.last_deworming_medicine === 'other'
            ? String(goat.other_last_deworming_medicine || '').trim() || null
            : null,
    }
}

export function farmPayload(farm) {
    const source = normalizeFarm(farm)
    const payload = {}
    for (const key of FARM_FIELDS) payload[key] = source[key] ?? null
    if (farm.pending_resolution) payload.resolution = farm.pending_resolution
    return payload
}

export function goatPayload(goat) {
    const source = normalizeGoat(goat)
    const payload = {}
    for (const key of GOAT_FIELDS) payload[key] = source[key] ?? null
    if (goat.pending_resolution) payload.resolution = goat.pending_resolution
    return payload
}

export async function enqueue(entity, uuid) {
    await db.syncQueue.where('[entity+uuid]').equals([entity, uuid]).delete()
    await db.syncQueue.add({
        entity,
        uuid,
        operation: 'upsert',
        status: 'pending',
        attempts: 0,
        last_error: null,
        created_at: new Date().toISOString(),
    })
}

export async function dequeue(entity, uuid) {
    await db.syncQueue.where('[entity+uuid]').equals([entity, uuid]).delete()
}

export async function persistFarm(input, mode = 'draft') {
    const existing = input.uuid ? await db.farms.get(input.uuid) : null
    const now = new Date().toISOString()
    const normalized = normalizeFarm({ ...(existing || {}), ...input })
    const inPipeline = existing && existing.sync_status && existing.sync_status !== 'local'
    const next = {
        ...normalized,
        created_at: existing?.created_at || input.created_at || now,
        updated_at: now,
        deleted_at: input.deleted_at ?? null,
        base_updated_at: input.base_updated_at ?? existing?.base_updated_at ?? null,
        server_id: existing?.server_id ?? null,
        server_updated_at: existing?.server_updated_at ?? null,
        server_version: existing?.server_version ?? null,
        last_sync_at: existing?.last_sync_at ?? null,
        pending_resolution: input.pending_resolution ?? null,
        sync_status: mode === 'queue' || inPipeline ? 'pending' : 'local',
        sync_error: null,
    }
    await db.farms.put(next)
    if (next.sync_status === 'pending') await enqueue('farm', next.uuid)
    else await dequeue('farm', next.uuid)
    return next
}

export async function persistGoat(input, mode = 'draft') {
    const existing = input.uuid ? await db.goats.get(input.uuid) : null
    const now = new Date().toISOString()
    const normalized = normalizeGoat({ ...(existing || {}), ...input })
    const inPipeline = existing && existing.sync_status && existing.sync_status !== 'local'
    const next = {
        ...normalized,
        created_at: existing?.created_at || input.created_at || now,
        updated_at: now,
        deleted_at: input.deleted_at ?? null,
        base_updated_at: input.base_updated_at ?? existing?.base_updated_at ?? null,
        server_id: existing?.server_id ?? null,
        server_updated_at: existing?.server_updated_at ?? null,
        server_version: existing?.server_version ?? null,
        last_sync_at: existing?.last_sync_at ?? null,
        pending_resolution: input.pending_resolution ?? null,
        sync_status: mode === 'queue' || inPipeline ? 'pending' : 'local',
        sync_error: null,
    }
    await db.goats.put(next)
    if (next.sync_status === 'pending') await enqueue('goat', next.uuid)
    else await dequeue('goat', next.uuid)
    return next
}

export async function getFarm(uuid) {
    return db.farms.get(uuid)
}

export async function getGoat(uuid) {
    return db.goats.get(uuid)
}

export async function listFarms() {
    const rows = await db.farms.filter((farm) => !farm.deleted_at).toArray()
    return rows.sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)))
}

export async function listGoats() {
    const [goats, farms] = await Promise.all([
        db.goats.filter((goat) => !goat.deleted_at).toArray(),
        db.farms.toArray(),
    ])
    const farmsById = new Map(farms.map((farm) => [farm.uuid, farm]))
    return goats
        .map((goat) => ({
            ...goat,
            farm_code: farmsById.get(goat.farm_uuid)?.farm_code || goat.farm_code || '—',
            ward_number: farmsById.get(goat.farm_uuid)?.ward_number ?? null,
            feeding_system: farmsById.get(goat.farm_uuid)?.feeding_system ?? null,
            deworming_frequency: farmsById.get(goat.farm_uuid)?.deworming_frequency ?? null,
        }))
        .sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)))
}

export async function goatsForFarm(farmUuid) {
    const rows = await db.goats.where('farm_uuid').equals(farmUuid).filter((goat) => !goat.deleted_at).toArray()
    return rows.sort((a, b) => String(a.goat_code).localeCompare(String(b.goat_code)))
}

export async function deleteFarm(uuid) {
    const farm = await db.farms.get(uuid)
    if (!farm) return
    const goats = await db.goats.where('farm_uuid').equals(uuid).toArray()
    if (farm.sync_status === 'local' && !farm.base_updated_at) {
        await db.transaction('rw', [db.farms, db.goats, db.syncQueue], async () => {
            await db.farms.delete(uuid)
            for (const goat of goats) {
                await db.goats.delete(goat.uuid)
                await db.syncQueue.where('uuid').equals(goat.uuid).delete()
            }
            await db.syncQueue.where('uuid').equals(uuid).delete()
        })
        return
    }
    const now = new Date().toISOString()
    await db.farms.update(uuid, { deleted_at: now, updated_at: now, sync_status: 'pending', sync_error: null })
    await enqueue('farm', uuid)
    for (const goat of goats) {
        if (goat.deleted_at) continue
        if (goat.sync_status === 'local' && !goat.base_updated_at) {
            await db.goats.delete(goat.uuid)
            await dequeue('goat', goat.uuid)
        } else {
            await db.goats.update(goat.uuid, { deleted_at: now, updated_at: now, sync_status: 'pending', sync_error: null })
            await enqueue('goat', goat.uuid)
        }
    }
}

export async function deleteGoat(uuid) {
    const goat = await db.goats.get(uuid)
    if (!goat) return
    if (goat.sync_status === 'local' && !goat.base_updated_at) {
        await db.goats.delete(uuid)
        await dequeue('goat', uuid)
        return
    }
    const now = new Date().toISOString()
    await db.goats.update(uuid, { deleted_at: now, updated_at: now, sync_status: 'pending', sync_error: null })
    await enqueue('goat', uuid)
}

export async function surveyCounts() {
    const [farms, goats] = await Promise.all([db.farms.toArray(), db.goats.toArray()])
    const activeFarms = farms.filter((farm) => !farm.deleted_at)
    const activeGoats = goats.filter((goat) => !goat.deleted_at)
    const pendingStatus = (row) => ['local', 'pending', 'syncing'].includes(row.sync_status)
    const recent = activeFarms
        .slice()
        .sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at)))
        .slice(0, 6)
        .map((farm) => ({
            ...farm,
            sampled: activeGoats.filter((goat) => goat.farm_uuid === farm.uuid).length,
        }))

    return {
        farms: activeFarms.length,
        goats: activeGoats.length,
        syncedFarms: activeFarms.filter((farm) => farm.sync_status === 'synced').length,
        syncedGoats: activeGoats.filter((goat) => goat.sync_status === 'synced').length,
        pending: activeFarms.filter(pendingStatus).length + activeGoats.filter(pendingStatus).length,
        failed: [...activeFarms, ...activeGoats].filter((row) => row.sync_status === 'failed').length,
        conflicts: [...farms, ...goats].filter((row) => row.sync_status === 'conflict').length,
        recent,
    }
}

export async function recordsNeedingAttention() {
    const [farms, goats] = await Promise.all([db.farms.toArray(), db.goats.toArray()])
    const interesting = (row) => ['local', 'pending', 'syncing', 'failed', 'conflict'].includes(row.sync_status)
    return {
        farms: farms.filter(interesting).sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at))),
        goats: goats.filter(interesting).sort((a, b) => String(b.updated_at).localeCompare(String(a.updated_at))),
        queue: await db.syncQueue.orderBy('created_at').toArray(),
    }
}

export async function shortenFarmCodes() {
    const farms = await db.farms.toArray()
    const pending = farms
        .filter((farm) => !isNumberedFarmCode(farm.farm_code))
        .sort((a, b) => String(a.created_at).localeCompare(String(b.created_at)))
    if (!pending.length) return

    const used = farms.filter((farm) => isNumberedFarmCode(farm.farm_code)).map((farm) => farm.farm_code)
    for (const farm of pending) {
        const farmCode = nextFarmCode(used)
        used.push(farmCode)
        const now = new Date().toISOString()
        const goats = await db.goats.where('farm_uuid').equals(farm.uuid).toArray()
        const status = farm.sync_status === 'local' ? 'local' : 'pending'
        await db.farms.update(farm.uuid, { farm_code: farmCode, updated_at: now, sync_status: status, sync_error: null })
        if (status === 'pending') await enqueue('farm', farm.uuid)

        goats.sort((a, b) => String(a.created_at).localeCompare(String(b.created_at)))
        for (const [index, goat] of goats.entries()) {
            const match = String(goat.goat_code || '').match(/-G(\d+)$/i)
            const sequence = match ? match[1].padStart(2, '0') : String(index + 1).padStart(2, '0')
            const goatStatus = goat.sync_status === 'local' ? 'local' : 'pending'
            await db.goats.update(goat.uuid, {
                goat_code: `${farmCode}-G${sequence}`,
                updated_at: now,
                sync_status: goatStatus,
                sync_error: null,
            })
            if (goatStatus === 'pending') await enqueue('goat', goat.uuid)
        }
    }
}

export async function recoverInterruptedSync() {
    await db.farms.where('sync_status').equals('syncing').modify({ sync_status: 'pending' })
    await db.goats.where('sync_status').equals('syncing').modify({ sync_status: 'pending' })
    const farms = await db.farms.where('sync_status').anyOf('pending', 'failed').toArray()
    const goats = await db.goats.where('sync_status').anyOf('pending', 'failed').toArray()
    for (const farm of farms) await enqueue('farm', farm.uuid)
    for (const goat of goats) await enqueue('goat', goat.uuid)
}

export async function resolveRecord(entity, uuid, resolution) {
    const table = entity === 'farm' ? db.farms : db.goats
    await table.update(uuid, {
        pending_resolution: resolution,
        sync_status: 'pending',
        sync_error: null,
    })
    await enqueue(entity, uuid)
}

export { FARM_FIELDS, GOAT_FIELDS }
