// Evenza Service Worker
const CACHE_NAME = 'evenza-pwa-v1';
const PRECACHE_ASSETS = [
    '/',
    '/index.html',
    '/student/',
    '/student/index.html',
    '/admin/',
    '/admin/index.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[Evenza SW] Pre-caching warning:', err);
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Bypass Firebase, Firestore, Google APIs, and external CDNs from caching to guarantee live real-time sync
    if (
        url.hostname.includes('firebase') ||
        url.hostname.includes('googleapis.com') ||
        url.hostname.includes('gstatic.com') ||
        event.request.method !== 'GET'
    ) {
        return;
    }

    // Network-first strategy for app pages so updates deploy immediately, falling back to cache
    event.respondWith(
        fetch(event.request)
            .then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const clonedResponse = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clonedResponse);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                return caches.match(event.request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    if (event.request.headers.get('accept')?.includes('text/html')) {
                        return caches.match('/index.html');
                    }
                });
            })
    );
});
