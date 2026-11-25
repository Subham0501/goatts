export async function checkApi() {
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), 4000)

    try {
        const response = await fetch('/api/health', {
            method: 'GET',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
        if (!response.ok) return false
        const body = await response.json()
        return body?.ok === true
    } catch {
        return false
    } finally {
        clearTimeout(timer)
    }
}
