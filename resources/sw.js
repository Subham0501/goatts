import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { CacheFirst, NetworkOnly } from 'workbox-strategies'
import { clientsClaim } from 'workbox-core'

self.skipWaiting()
clientsClaim()
cleanupOutdatedCaches()
const manifest = self.__WB_MANIFEST
precacheAndRoute(manifest)

const SHELL_CACHE = 'html-shell'
const SHELL_URL = `${self.location.origin}/`

const staticFiles = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.ico',
    '/manifest.webmanifest',
    '/images/hero-goats.jpg',
    '/images/farm-visit.jpg',
    '/images/sampled-goat.jpg',
    '/images/study-records.jpg',
]

function manifestUrls() {
    return (manifest || []).map((entry) => (typeof entry === 'string' ? entry : entry.url))
}

function fallbackShell() {
    const urls = manifestUrls()
    const css = urls.find((url) => url.endsWith('.css'))
    const js = urls.find((url) => url.includes('/app-') && url.endsWith('.js'))
    const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1d6b48">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png">
<title>Goat Health Survey</title>
${css ? `<link rel="stylesheet" href="${css}">` : ''}
</head>
<body>
<div id="app"></div>
${js ? `<script type="module" src="${js}"></script>` : ''}
</body>
</html>`
    return new Response(html, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' },
    })
}

async function rememberShell(response) {
    if (!response || !response.ok) return
    const cache = await caches.open(SHELL_CACHE)
    await cache.put(SHELL_URL, response.clone())
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open('app-static')
        await cache.addAll(staticFiles).catch(() => {})
        try {
            const response = await fetch(new Request('/', { cache: 'reload' }))
            await rememberShell(response)
        } catch {
            // The page can still open from the saved script if this visit has no network.
        }
    })())
})

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return

    event.respondWith((async () => {
        if (self.navigator.onLine) {
            try {
                const response = await fetch(event.request)
                if (response && response.ok) {
                    await rememberShell(response)
                    return response
                }
            } catch {
                // Use the saved survey page.
            }
        }

        const cache = await caches.open(SHELL_CACHE)
        const saved = await cache.match(SHELL_URL)
        return saved || fallbackShell()
    })())
})

registerRoute(({ url }) => url.pathname.startsWith('/api/'), new NetworkOnly())
registerRoute(
    ({ url }) => url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/images/')
        || url.pathname === '/manifest.webmanifest'
        || url.pathname === '/favicon.ico',
    new CacheFirst({ cacheName: 'app-static' }),
)
