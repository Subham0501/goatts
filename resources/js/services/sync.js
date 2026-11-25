import { db } from '../db'
import { api } from './api'
import { checkApi } from './connectivity'
import { validateFarm, validateGoat } from '../data/validate'
import {
    FARM_FIELDS,
    GOAT_FIELDS,
    dequeue,
    enqueue,
    farmPayload,
    goatPayload,
    recoverInterruptedSync,
} from './records'

let unauthorized = async () => {}
let running = null

export function setUnauthorizedHandler(handler) {
    unauthorized = handler
}

export async function prepareSync() {
    await recoverInterruptedSync()
}

export function syncNow() {
    if (running) return running
    running = runSync().finally(() => {
        running = null
    })
    return running
}

async function runSync() {
    const online = await checkApi()
    if (!online) {
        return {
            online: false,
            ok: false,
            idle: true,
            message: 'No connection to the server. Records remain on this device.',
        }
    }

    let confirmed = 0
    let waiting = 0
    try {
        confirmed += await push('farm')
        confirmed += await push('goat')
        await pullChanges()
    } catch (error) {
        if (error?.response?.status === 401) {
            await unauthorized()
            return {
                online: true,
                ok: false,
                idle: false,
                message: 'Sign in again to sync. Survey records are still on this device.',
            }
        }
        return {
            online: false,
            ok: false,
            idle: false,
            message: 'Sync stopped before it finished. Nothing already saved on this device was removed.',
        }
    }

    const farms = await db.farms.toArray()
    const goats = await db.goats.toArray()
    waiting = [...farms, ...goats].filter((row) => ['pending', 'failed', 'conflict', 'local'].includes(row.sync_status) && !row.deleted_at).length
        + [...farms, ...goats].filter((row) => row.deleted_at && ['pending', 'failed', 'conflict'].includes(row.sync_status)).length

    if (confirmed === 0 && waiting === 0) {
        return { online: true, ok: true, idle: true, message: 'Everything on this device matches the server.' }
    }

    if (waiting > 0) {
        return {
            online: true,
            ok: confirmed > 0,
            idle: false,
            message: confirmed > 0
                ? `${recordCount(confirmed)} confirmed. ${recordCount(waiting)} still need attention on this device.`
                : `${recordCount(waiting)} still need attention before they can be confirmed.`,
        }
    }

    return { online: true, ok: true, idle: false, message: `${recordCount(confirmed)} confirmed by the server.` }
}

function recordCount(count) {
    return `${count} ${count === 1 ? 'record' : 'records'}`
}

async function push(entity) {
    const table = entity === 'farm' ? db.farms : db.goats
    let rows = await table.where('sync_status').anyOf('pending', 'failed').toArray()

    if (entity === 'goat') {
        const farms = await db.farms.toArray()
        const farmsById = new Map(farms.map((farm) => [farm.uuid, farm]))
        rows = rows.filter((goat) => {
            const farm = farmsById.get(goat.farm_uuid)
            return farm && (farm.sync_status === 'synced' || farm.base_updated_at) && !farm.deleted_at
        })
    }

    rows = rows.filter((row) => {
        if (row.deleted_at) return true
        const errors = entity === 'farm' ? validateFarm(row) : validateGoat(row)
        return Object.keys(errors).length === 0
    })

    let confirmed = 0
    for (const group of chunk(rows, 40)) {
        const sent = new Map(group.map((row) => [row.uuid, row.updated_at]))
        for (const row of group) {
            await table.update(row.uuid, { sync_status: 'syncing' })
        }
        try {
            const payloads = group.map((row) => (entity === 'farm' ? farmPayload(row) : goatPayload(row)))
            const body = entity === 'farm' ? { farms: payloads, goats: [] } : { farms: [], goats: payloads }
            const { data } = await api.post('/sync', body)
            for (const result of data.results || []) {
                const ok = await applyResult(entity, result, sent.get(result.uuid))
                if (ok) confirmed += 1
            }
        } catch (error) {
            const message = error?.response?.data?.message || 'The server could not be reached. This record is still on this device.'
            for (const row of group) {
                const current = await table.get(row.uuid)
                if (!current) continue
                if (current.updated_at !== row.updated_at) {
                    await table.update(row.uuid, { sync_status: 'pending' })
                } else {
                    await table.update(row.uuid, {
                        sync_status: 'failed',
                        sync_error: message,
                    })
                    await markQueue(entity, row.uuid, 'failed', message)
                }
            }
            if (error?.response?.status === 401) throw error
        }
    }
    return confirmed
}

async function applyResult(entity, result, sentUpdatedAt) {
    const table = entity === 'farm' ? db.farms : db.goats
    const current = result?.uuid ? await table.get(result.uuid) : null
    if (!current) return false
    const editedSinceSend = sentUpdatedAt && current.updated_at !== sentUpdatedAt

    if (result.status === 'synced') {
        const replacement = result.replaced && result.record ? businessFromServer(entity, result.record) : {}
        await table.update(result.uuid, {
            ...replacement,
            deleted_at: result.replaced && result.record ? result.record.deleted_at : current.deleted_at,
            sync_status: editedSinceSend ? 'pending' : 'synced',
            base_updated_at: result.server_updated_at,
            server_updated_at: result.server_updated_at,
            server_id: result.id ?? result.record?.id ?? current.server_id,
            server_version: null,
            sync_error: null,
            pending_resolution: null,
            last_sync_at: new Date().toISOString(),
        })
        if (editedSinceSend) await enqueue(entity, result.uuid)
        else await dequeue(entity, result.uuid)
        return !editedSinceSend
    }

    if (result.status === 'conflict') {
        await table.update(result.uuid, {
            sync_status: 'conflict',
            server_version: result.record || null,
            sync_error: result.message || 'The server has a newer copy of this record.',
            pending_resolution: null,
        })
        await markQueue(entity, result.uuid, 'conflict', result.message)
        return false
    }

    if (result.status === 'blocked') {
        await table.update(result.uuid, {
            sync_status: 'pending',
            sync_error: result.message || 'Waiting for the parent farm.',
        })
        return false
    }

    await table.update(result.uuid, {
        sync_status: 'failed',
        sync_error: result.message || 'The server rejected this record.',
    })
    await markQueue(entity, result.uuid, 'failed', result.message)
    return false
}

async function pullChanges() {
    const state = await db.appSettings.get('sync')
    const response = await api.get('/sync/pull', {
        params: state?.server_time ? { since: state.server_time } : {},
    })
    for (const farm of response.data.farms || []) await mergeServerRecord('farm', farm)
    for (const goat of response.data.goats || []) await mergeServerRecord('goat', goat)
    await db.appSettings.put({
        key: 'sync',
        server_time: response.data.server_time,
        pulled_at: new Date().toISOString(),
    })
}

async function mergeServerRecord(entity, server) {
    const table = entity === 'farm' ? db.farms : db.goats
    const local = await table.get(server.uuid)
    if (!local) {
        await table.put({
            ...businessFromServer(entity, server),
            uuid: server.uuid,
            created_at: server.created_at,
            updated_at: server.updated_at,
            deleted_at: server.deleted_at,
            sync_status: 'synced',
            base_updated_at: server.server_updated_at,
            server_updated_at: server.server_updated_at,
            server_id: server.id,
            server_version: null,
            sync_error: null,
            pending_resolution: null,
            last_sync_at: new Date().toISOString(),
            draft_step: 0,
            code_custom: true,
        })
        return
    }

    const dirty = ['local', 'pending', 'failed', 'syncing', 'conflict'].includes(local.sync_status)
    if (!dirty) {
        await table.update(local.uuid, {
            ...businessFromServer(entity, server),
            created_at: server.created_at || local.created_at,
            updated_at: server.updated_at || local.updated_at,
            deleted_at: server.deleted_at,
            sync_status: 'synced',
            base_updated_at: server.server_updated_at,
            server_updated_at: server.server_updated_at,
            server_id: server.id,
            server_version: null,
            sync_error: null,
            pending_resolution: null,
            last_sync_at: new Date().toISOString(),
        })
        return
    }

    if (sameBusiness(entity, local, server)) {
        await table.update(local.uuid, {
            sync_status: 'synced',
            base_updated_at: server.server_updated_at,
            server_updated_at: server.server_updated_at,
            server_id: server.id,
            deleted_at: server.deleted_at,
            sync_error: null,
            server_version: null,
            pending_resolution: null,
            last_sync_at: new Date().toISOString(),
        })
        await dequeue(entity, local.uuid)
        return
    }

    const serverTime = server.server_updated_at ? new Date(server.server_updated_at).getTime() : 0
    const baseTime = local.base_updated_at ? new Date(local.base_updated_at).getTime() : 0
    const serverAhead = local.base_updated_at ? serverTime > baseTime + 1000 : local.sync_status !== 'local'
    if (serverAhead) {
        await table.update(local.uuid, {
            sync_status: 'conflict',
            server_version: server,
            sync_error: 'This record was also changed on the server.',
        })
    }
}

function businessFromServer(entity, server) {
    const fields = entity === 'farm' ? FARM_FIELDS : [...GOAT_FIELDS, 'farm_code']
    const copy = {}
    for (const field of fields) {
        if (field === 'base_updated_at' || field === 'created_at' || field === 'updated_at' || field === 'deleted_at') continue
        if (server[field] !== undefined) copy[field] = server[field]
    }
    return copy
}

function sameBusiness(entity, local, server) {
    if (Boolean(local.deleted_at) !== Boolean(server.deleted_at)) return false
    const fields = entity === 'farm' ? FARM_FIELDS : GOAT_FIELDS
    return fields.every((field) => {
        if (['created_at', 'updated_at', 'deleted_at', 'base_updated_at', 'uuid'].includes(field)) return true
        return norm(local[field]) === norm(server[field])
    })
}

function norm(value) {
    if (value === null || value === undefined) return ''
    return String(value).trim()
}

async function markQueue(entity, uuid, status, message) {
    const item = await db.syncQueue.where('[entity+uuid]').equals([entity, uuid]).first()
    if (!item) return
    await db.syncQueue.update(item.id, {
        status,
        attempts: (item.attempts || 0) + 1,
        last_error: message || null,
    })
}

function chunk(items, size) {
    const groups = []
    for (let index = 0; index < items.length; index += size) {
        groups.push(items.slice(index, index + size))
    }
    return groups
}
