const CACHE_PREFIX = 'pup-prism-shell-';
const CACHE_NAME = `${CACHE_PREFIX}v4`;
const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.ico',
    '/Icons/prism-icon-192x192.png',
    '/Icons/prism-icon-512x512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            await cache.add('/offline.html');

            await Promise.allSettled(
                PRECACHE_URLS.filter((url) => url !== '/offline.html').map(
                    (url) => cache.add(url),
                ),
            );
        }),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();

            await Promise.all(
                keys
                    .filter(
                        (key) =>
                            key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME,
                    )
                    .map((key) => caches.delete(key)),
            );

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);
    const isSameOrigin = requestUrl.origin === self.location.origin;

    if (event.request.mode === 'navigate' && isSameOrigin) {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cachedResponse = await caches.match('/offline.html');

                return cachedResponse || Response.error();
            }),
        );

        return;
    }

    if (!isSameOrigin) {
        return;
    }

    if (PRECACHE_URLS.includes(requestUrl.pathname)) {
        event.respondWith(
            caches.match(event.request).then(async (cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }

                const networkResponse = await fetch(event.request);

                if (!networkResponse.ok) {
                    return networkResponse;
                }

                const cache = await caches.open(CACHE_NAME);

                await cache.put(event.request, networkResponse.clone());

                return networkResponse;
            }),
        );
    }
});
