const CACHE_NAME = 'klog-v1'
const OFFLINE_URL = '/offline'

// Pre-cache the offline page on install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL))
    )
    self.skipWaiting()
})

// Clean up old caches on activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    )
    self.clients.claim()
})

self.addEventListener('fetch', (event) => {
    const { request } = event
    const url = new URL(request.url)

    // Only handle same-origin requests
    if (url.origin !== self.location.origin) {
        return
    }

    // Auth-gated media — always go to network (never cache)
    if (url.pathname.startsWith('/media/')) {
        return
    }

    // Navigation requests — Network First, fall back to cached offline page
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        )
        return
    }

    // Vite assets (content-hashed, immutable) — Cache First
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone()
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
                    }
                    return response
                })
            })
        )
        return
    }

    // Static assets (icons, manifest, favicons) — Stale While Revalidate
    if (
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.webmanifest' ||
        url.pathname === '/favicon.svg' ||
        url.pathname === '/favicon-96x96.png' ||
        url.pathname === '/favicon.ico' ||
        url.pathname === '/apple-touch-icon.png'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const fetchPromise = fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone()
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
                    }
                    return response
                })
                return cached || fetchPromise
            })
        )
        return
    }

    // Everything else — Network Only (pass through)
})
