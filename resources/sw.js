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
    networkTimeoutSeconds: 3,
    plugins: [
        {
            cacheKeyWillBeUsed: async () => `${self.location.origin}/`,
        },
    ],
})

registerRoute(({ url }) => url.pathname.startsWith('/api/'), new NetworkOnly())
registerRoute(
    ({ url }) => url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest',
    new CacheFirst({ cacheName: 'app-icons' }),
)
registerRoute(new NavigationRoute(appShell, { denylist: [/^\/api\//] }))
