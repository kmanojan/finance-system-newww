const CACHE_NAME = "finance-system-v2";
const STATIC_ASSETS = [
    "/manifest.json",
    "/offline.html",
    "/icons/icon-192x192.png",
    "/icons/icon-512x512.png",
    "/icons/icon-maskable.png",
    "/icons/icon.svg"
];

// Install Event
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const asset of STATIC_ASSETS) {
                try {
                    await cache.add(asset);
                } catch (e) {
                    console.warn("SW: failed to pre-cache", asset, e);
                }
            }
        })
    );
    self.skipWaiting();
});

// Activate Event - Clean old caches
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch Event
self.addEventListener("fetch", (event) => {
    const request = event.request;

    // Only handle GET requests
    if (request.method !== "GET") return;

    // HTML Navigation requests: Network first, fallback to cache, fallback to offline.html
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    return response;
                })
                .catch(() => {
                    return caches.match(request).then((cachedResponse) => {
                        return cachedResponse || caches.match("/offline.html");
                    });
                })
        );
        return;
    }

    // Static Assets (CSS, JS, Fonts, Images): Cache First with background update
    if (
        request.destination === "style" ||
        request.destination === "script" ||
        request.destination === "image" ||
        request.destination === "font"
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                }).catch(() => {/* ignore background update errors */});

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default network with cache fallback
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});