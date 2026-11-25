export function createUuid() {
    if (globalThis.crypto?.randomUUID) {
        return crypto.randomUUID()
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16)
        const value = char === 'x' ? random : (random & 0x3) | 0x8
        return value.toString(16)
    })
}

export function nextFarmCode(existingCodes = []) {
    let max = 0
    for (const code of existingCodes) {
        const text = String(code ?? '').trim()
        if (/^\d+$/.test(text)) max = Math.max(max, Number(text))
    }
    return String(max + 1)
}

export function isNumberedFarmCode(code) {
    return /^\d+$/.test(String(code ?? '').trim())
}

export function createGoatCode(farmCode, sequence) {
    return `${farmCode}-G${pad(sequence)}`
}

function pad(value) {
    return String(value).padStart(2, '0')
}
