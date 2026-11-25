import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching'
import { NavigationRoute, registerRoute } from 'workbox-routing'
import { CacheFirst, NetworkFirst, NetworkOnly } from 'workbox-strategies'
import { clientsClaim } from 'workbox-core'

self.skipWaiting()
clientsClaim()
cleanupOutdatedCaches()
precacheAndRoute(self.__WB_MANIFEST)

const appShell = new NetworkFirst({
    cacheName: 'html-shell',
    networkTimeoutSeconds: 2,
    plugins: [
        {
            cacheKeyWillBeUsed: async () => `${self.location.origin}/`,
        },
        {
            requestWillFetch: async ({ request }) => {
                if (!self.navigator.onLine) throw new Error('offline')
                return request
            },
        },
    ],
})

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

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open('app-static').then((cache) => cache.addAll(staticFiles)).catch(() => {}),
    )
})

registerRoute(({ url }) => url.pathname.startsWith('/api/'), new NetworkOnly())
registerRoute(
    ({ url }) => url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/images/')
        || url.pathname === '/manifest.webmanifest'
        || url.pathname === '/favicon.ico',
    new CacheFirst({ cacheName: 'app-static' }),
)
registerRoute(new NavigationRoute(appShell, { denylist: [/^\/api\//] }))
