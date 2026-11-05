// =============================================================
// FPV Spots Germany – Service Worker
// =============================================================
const CACHE_VERSION = 'v28';

// Muss mit ASSET_VERSION in private/php/core/assets.php uebereinstimmen:
// die Seiten fordern CSS/JS als /pfad.css?v=N an, und caches.match()
// vergleicht die Query mit. Ohne den Parameter liefe der Pre-Cache ins
// Leere und offline fehlten genau diese Dateien.
const ASSET_VERSION = '27';
const v = (path) => path + '?v=' + ASSET_VERSION;
const STATIC_CACHE  = 'fpv-static-' + CACHE_VERSION;
const TILES_CACHE   = 'fpv-tiles-'  + CACHE_VERSION;
const API_CACHE     = 'fpv-api-'    + CACHE_VERSION;

const TILES_MAX_ENTRIES = 200;

// Statische Assets zum Pre-Cachen
const STATIC_ASSETS = [
    '/offline.html',
    v('/public/css/theme.css'),
    v('/public/css/banners.css'),
    v('/public/css/map.css'),
    v('/public/js/map.js'),
    v('/public/js/pwa.js'),
    '/public/imgs/logo.png',
    '/favicon.ico',
    '/public/imgs/icons/icon-192.png',
    '/public/imgs/icons/icon-512.png',
    v('/public/vendor/leaflet/leaflet.css'),
    v('/public/vendor/leaflet/leaflet.js'),
    v('/public/vendor/bootstrap/bootstrap.min.css'),
    v('/public/vendor/bootstrap/bootstrap.bundle.min.js'),
    v('/public/vendor/bootstrap-icons/bootstrap-icons.min.css'),
    '/public/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6'
];

// -------------------------------------------------------------
// Pre-Cache: jede URL einzeln und fehlertolerant
//
// cache.addAll() ist atomar – schlaegt EIN einziger Request fehl, wird der
// gesamte Aufruf verworfen, die Installation des Service Workers scheitert
// und der ALTE Service Worker bleibt aktiv. Deshalb: jede URL einzeln
// cachen, Fehler protokollieren und ueberspringen. Nicht vorgecachte
// Dateien laedt die jeweilige Fetch-Strategie spaeter nach.
//
// Alle Eintraege sind bewusst same-origin. Externe CDNs (unpkg.com,
// cdn.jsdelivr.net) standen hier frueher ebenfalls drin – sie wurden von
// der CSP-Direktive connect-src 'self' blockiert, die auch fuer fetch()
// INNERHALB des Service Workers gilt. Ergebnis: leerer CDN-Cache, 503 auf
// leaflet.js und eine Karte, die nicht mehr geladen wurde. Vendor-Libs
// liegen deshalb jetzt lokal unter /public/vendor/.
// -------------------------------------------------------------
function precache(cacheName, urls) {
    return caches.open(cacheName).then((cache) =>
        Promise.all(
            urls.map((url) =>
                cache.add(url).catch((err) => {
                    console.warn('[SW] Pre-Cache uebersprungen:', url, err);
                })
            )
        )
    );
}

// ── Install: Pre-Cache ──────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        precache(STATIC_CACHE, STATIC_ASSETS).then(() => self.skipWaiting())
    );
});

// ── Activate: alte Caches loeschen ──────────────────────────
self.addEventListener('activate', (event) => {
    const currentCaches = [STATIC_CACHE, TILES_CACHE, API_CACHE];
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
    if (isOsmTileHost(url.hostname)) {
        event.respondWith(tileStrategy(request));
        return;
    }

    // 2. Alle uebrigen Cross-Origin-Requests: nicht abfangen.
    //    Der Browser laedt sie direkt; dort greifen script-src/style-src/
    //    img-src statt connect-src, das im Service Worker nur 'self' erlaubt.
    if (url.origin !== self.location.origin) return;

    // 3. Geozonen-Kacheln: NETWORK-ONLY, niemals cachen.
    //
    // Kein respondWith() – der Browser laedt die Kachel selbst, damit sie in
    // keiner Strategie und in keinem Cache landet. Eine gecachte, veraltete
    // Sperrzone waere kein Komfortproblem, sondern ein Sicherheitsproblem:
    // temporaere Betriebseinschraenkungen aendern sich taeglich, und offline
    // eine Zone NICHT zu sehen, die inzwischen gilt, ist schlimmer als gar
    // keine Karte. Die Vorhaltung uebernimmt der serverseitige Kachel-Cache
    // in public/php/api/dipul_wms.php mit buendelabhaengiger TTL.
    if (url.pathname === '/api/dipul_wms.php') {
        return;
    }

    // 4. API-Aufrufe (Network-first mit Cache-Fallback)
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request, API_CACHE));
        return;
    }

    // 5. Eigene App-Logik: JS/CSS aus /public/ und /private/ (Network-first)
    if (isAppCode(url.pathname)) {
        event.respondWith(networkFirst(request, STATIC_CACHE));
        return;
    }

    // 6. Uebrige statische Assets: Bilder, Fonts, Vendor-Libs (Cache-first)
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // 7. Navigation / PHP-Seiten (Network-first, Offline-Fallback)
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

// -------------------------------------------------------------
// Hilfsfunktion: Eigene App-Logik erkennen (JS/CSS des Projekts)
//
// Diese Dateien tragen zwar seit Einfuehrung von asset() einen ?v=-Parameter,
// aber keine Versionsnummer im Dateinamen. Wuerden sie
// cache-first ausgeliefert, fuehren Besucher nach einem Deploy weiter die
// ALTE App-Logik aus, bis der Service Worker wechselt – genau dadurch rief
// eine veraltete map.js nach dem API-Routing-Fix noch den alten Endpunkt
// auf und die Karte blieb leer. Network-first liefert deshalb immer den
// aktuellen Stand; der Cache dient nur noch als Offline-Fallback.
//
// Vendor-Bibliotheken (/public/vendor/) sind bewusst NICHT enthalten:
// sie sind versioniert und aendern sich nicht zwischen Deploys.
// -------------------------------------------------------------
function isAppCode(pathname) {
    return /^\/(public|private)\/(js|css)\//.test(pathname);
}

// -------------------------------------------------------------
// Hilfsfunktion: OSM-Tile-Host erkennen
//
// Bewusst ein Suffix-Vergleich und kein hostname.includes(): letzteres
// haette auch Hosts wie "tile.openstreetmap.org.angreifer.de" akzeptiert
// und deren Antworten in den Tile-Cache geschrieben.
//
// map.js nutzt die Subdomain-Platzhalter {s} (a/b/c.tile.openstreetmap.org).
// Der Vergleich ist damit deckungsgleich mit der CSP-Quelle
// *.tile.openstreetmap.org in .htaccess.
// -------------------------------------------------------------
function isOsmTileHost(hostname) {
    return hostname.endsWith('.tile.openstreetmap.org');
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
