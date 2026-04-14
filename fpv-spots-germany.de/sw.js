// =============================================================
// FPV Spots Germany – Service Worker
// =============================================================
const CACHE_VERSION = 'v1';
const STATIC_CACHE  = 'fpv-static-' + CACHE_VERSION;
const CDN_CACHE     = 'fpv-cdn-'    + CACHE_VERSION;
const TILES_CACHE   = 'fpv-tiles-'  + CACHE_VERSION;
const API_CACHE     = 'fpv-api-'    + CACHE_VERSION;

const TILES_MAX_ENTRIES = 200;

// Statische Assets zum Pre-Cachen
const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/public/css/map.css',
    '/public/js/map.js',
    '/public/js/pwa.js',
    '/public/imgs/logo.png',
    '/public/imgs/logo2.png',
    '/favicon.ico',
    '/public/imgs/icons/icon-192.png',
    '/public/imgs/icons/icon-512.png'
];

// CDN-Ressourcen zum Pre-Cachen
const CDN_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];

// ── Install: Pre-Cache ──────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS)),
            caches.open(CDN_CACHE).then((cache) => cache.addAll(CDN_ASSETS))
        ]).then(() => self.skipWaiting())
    );
});

// ── Activate: alte Caches loeschen ──────────────────────────
self.addEventListener('activate', (event) => {
    const currentCaches = [STATIC_CACHE, CDN_CACHE, TILES_CACHE, API_CACHE];
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names
                    .filter((name) => !currentCaches.includes(name))
                    .map((name) => caches.delete(name))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch: Routing nach Ressourcentyp ───────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Nur GET-Requests cachen
    if (request.method !== 'GET') return;

    // 1. Map-Tiles (Stale-while-revalidate)
    if (url.hostname.includes('tile.openstreetmap.org')) {
        event.respondWith(tileStrategy(request));
        return;
    }

    // 2. CDN-Ressourcen (Cache-first)
    if (url.hostname.includes('cdn.jsdelivr.net') || url.hostname.includes('unpkg.com')) {
        event.respondWith(cacheFirst(request, CDN_CACHE));
        return;
    }

    // 3. API-Aufrufe (Network-first mit Cache-Fallback)
    if (url.pathname.startsWith('/public/php/api/')) {
        event.respondWith(networkFirst(request, API_CACHE));
        return;
    }

    // 4. Statische Assets (Cache-first)
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // 5. Navigation / PHP-Seiten (Network-first, Offline-Fallback)
    if (request.mode === 'navigate') {
        event.respondWith(navigationStrategy(request));
        return;
    }
});

// ── Strategien ──────────────────────────────────────────────

function cacheFirst(request, cacheName) {
    return caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
            if (response.ok) {
                const clone = response.clone();
                caches.open(cacheName).then((cache) => cache.put(request, clone));
            }
            return response;
        });
    });
}

function networkFirst(request, cacheName) {
    return fetch(request)
        .then((response) => {
            if (response.ok) {
                const clone = response.clone();
                caches.open(cacheName).then((cache) => cache.put(request, clone));
            }
            return response;
        })
        .catch(() => caches.match(request));
}

function navigationStrategy(request) {
    return fetch(request).catch(() =>
        caches.match('/offline.html')
    );
}

function tileStrategy(request) {
    return caches.match(request).then((cached) => {
        const networkFetch = fetch(request).then((response) => {
            if (response.ok) {
                const clone = response.clone();
                caches.open(TILES_CACHE).then((cache) => {
                    cache.put(request, clone);
                    trimCache(TILES_CACHE, TILES_MAX_ENTRIES);
                });
            }
            return response;
        });
        return cached || networkFetch;
    });
}

// Cache auf max. Eintraege begrenzen (aelteste zuerst loeschen)
function trimCache(cacheName, maxEntries) {
    caches.open(cacheName).then((cache) => {
        cache.keys().then((keys) => {
            if (keys.length > maxEntries) {
                cache.delete(keys[0]).then(() => trimCache(cacheName, maxEntries));
            }
        });
    });
}

// Hilfsfunktion: Statische Assets erkennen
function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot)$/i.test(pathname);
}

// ── Message: Update-Trigger ─────────────────────────────────
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
