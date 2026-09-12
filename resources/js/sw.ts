/// <reference lib="webworker" />

/**
 * Ribs Recipes service worker.
 *
 * Written by hand rather than generated, because the caching rules here need
 * to be deliberate:
 *
 *   - Build assets are precached and served cache-first. They are content
 *     hashed, so they can never go stale.
 *   - Recipe photos are cached at runtime with a bounded LRU, which is what
 *     makes a recipe stay usable — pictures and all — if the network drops
 *     mid-cook.
 *   - Page navigations are network-first with a cached fallback, so a recipe
 *     already visited still opens on a dead connection while a live network
 *     always wins.
 *   - /admin is never touched. Not cached, not served from cache, not
 *     intercepted. The Cloudflare Access token has a lifetime and admin data
 *     changes constantly; a service worker serving either from cache would be
 *     a genuine correctness bug.
 *   - Anything that is not a GET is passed straight through.
 */

declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: { url: string; revision: string | null }[]
}

const VERSION = 'v1'
const PRECACHE = `ribs-precache-${VERSION}`
const PAGES = `ribs-pages-${VERSION}`
const IMAGES = `ribs-images-${VERSION}`

const OFFLINE_URL = '/offline.html'
const MAX_PAGES = 40
const MAX_IMAGES = 120

const PRECACHE_URLS = [...(self.__WB_MANIFEST ?? []).map((entry) => entry.url), OFFLINE_URL]

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(PRECACHE)

            // Precache individually: one 404 must not fail the whole install.
            await Promise.allSettled(
                PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' }))),
            )

            await self.skipWaiting()
        })(),
    )
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keep = new Set([PRECACHE, PAGES, IMAGES])
            const names = await caches.keys()

            await Promise.all(names.filter((name) => !keep.has(name)).map((name) => caches.delete(name)))
            await self.clients.claim()
        })(),
    )
})

self.addEventListener('message', (event) => {
    if ((event.data as { type?: string } | undefined)?.type === 'SKIP_WAITING') {
        void self.skipWaiting()
    }
})

async function trim(cacheName: string, limit: number): Promise<void> {
    const cache = await caches.open(cacheName)
    const keys = await cache.keys()

    // Cache keys are insertion-ordered, so the oldest entries are first.
    if (keys.length <= limit) return

    await Promise.all(keys.slice(0, keys.length - limit).map((key) => cache.delete(key)))
}

async function networkFirstPage(request: Request): Promise<Response> {
    const cache = await caches.open(PAGES)

    try {
        const response = await fetch(request)

        if (response.ok && response.type === 'basic') {
            void cache.put(request, response.clone()).then(() => trim(PAGES, MAX_PAGES))
        }

        return response
    } catch {
        const cached = await cache.match(request)
        if (cached) return cached

        const offline = await caches.match(OFFLINE_URL)
        if (offline) return offline

        return new Response('You are offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        })
    }
}

async function cacheFirstAsset(request: Request, cacheName: string, limit?: number): Promise<Response> {
    const cache = await caches.open(cacheName)
    const cached = await cache.match(request)

    if (cached) return cached

    const response = await fetch(request)

    if (response.ok && (response.type === 'basic' || response.type === 'cors')) {
        void cache.put(request, response.clone()).then(() => (limit ? trim(cacheName, limit) : undefined))
    }

    return response
}

self.addEventListener('fetch', (event) => {
    const request = event.request

    if (request.method !== 'GET') return

    const url = new URL(request.url)

    // Never intercept the admin area, or anything cross-origin.
    if (url.origin !== self.location.origin) return
    if (url.pathname === '/admin' || url.pathname.startsWith('/admin/')) return

    // Inertia's XHR page requests carry live data; always go to the network.
    if (request.headers.get('X-Inertia')) return

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request))
        return
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirstAsset(request, PRECACHE))
        return
    }

    if (request.destination === 'image' && url.pathname.startsWith('/storage/')) {
        event.respondWith(cacheFirstAsset(request, IMAGES, MAX_IMAGES))
        return
    }

    if (request.destination === 'font') {
        event.respondWith(cacheFirstAsset(request, PRECACHE))
    }
})

export {}
