// =============================================================
// FPV Spots Germany – Karten-Logik
// Abhängigkeiten: Leaflet, Bootstrap 5 (geladen in index.php)
// =============================================================

'use strict';

// ---------------------------------------------------------------
// App-Konfiguration aus Meta-Tags lesen (kein Inline-JS nötig)
// ---------------------------------------------------------------
const IS_LOGGED_IN  = document.querySelector('meta[name="app-logged-in"]').content  === 'true';
const CURRENT_USER  = parseInt(document.querySelector('meta[name="app-user-id"]').content, 10);
const IS_ADMIN      = document.querySelector('meta[name="app-is-admin"]').content   === 'true';
const CSRF_TOKEN    = document.querySelector('meta[name="app-csrf-token"]').content;

// ---------------------------------------------------------------
// Karte initialisieren – Mittelpunkt Deutschland
// ---------------------------------------------------------------
const MAP_STATE_KEY   = 'fpv_map_state';
const MAP_DEFAULT_CENTER = [51.1657, 10.4515];
const MAP_DEFAULT_ZOOM   = 6;
const MAP_MAX_ZOOM       = 19;

// Dashboard-Link: ?spot=ID – früh auslesen, damit sessionStorage gezielt übersprungen werden kann
const _urlSpotId = new URLSearchParams(window.location.search).get('spot');

// Gespeicherten Kartenzustand aus sessionStorage lesen (überlebt Navigation zu spot_detail.php & zurück,
// wird beim Schließen des Tabs automatisch verworfen → keine Cookie-Einwilligung nötig).
function readSavedMapState() {
    try {
        const raw = sessionStorage.getItem(MAP_STATE_KEY);
        if (!raw) return null;
        const s = JSON.parse(raw);
        const lat  = Number(s.lat);
        const lng  = Number(s.lng);
        const zoom = Number(s.zoom);
        if (!Number.isFinite(lat) || lat < -90  || lat > 90)  return null;
        if (!Number.isFinite(lng) || lng < -180 || lng > 180) return null;
        if (!Number.isFinite(zoom) || zoom < 0 || zoom > MAP_MAX_ZOOM) return null;
        return { lat, lng, zoom };
    } catch (_) {
        try { sessionStorage.removeItem(MAP_STATE_KEY); } catch (_) {}
        return null;
    }
}

// Bei direktem Spot-Link sessionStorage ignorieren, damit flyTo ungestört greift
const savedMapState = _urlSpotId ? null : readSavedMapState();
const map = L.map('map', { zoomControl: true, wheelPxPerZoomLevel: 120 }).setView(
    savedMapState ? [savedMapState.lat, savedMapState.lng] : MAP_DEFAULT_CENTER,
    savedMapState ? savedMapState.zoom : MAP_DEFAULT_ZOOM
);

// Zustand bei jeder Kartenbewegung / Zoomänderung persistieren
map.on('moveend zoomend', () => {
    try {
        const c = map.getCenter();
        sessionStorage.setItem(MAP_STATE_KEY, JSON.stringify({
            lat: c.lat, lng: c.lng, zoom: map.getZoom(),
        }));
    } catch (_) { /* sessionStorage nicht verfügbar – ignorieren */ }
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: MAP_MAX_ZOOM,
}).addTo(map);

// ---------------------------------------------------------------
// Spot-Typ → Marker-Farbe
// ---------------------------------------------------------------
const TYPE_COLORS = {
    'Bando':    '#4b4e5a',
    'Feld':     '#ffe224',
    'Gebirge':  '#b1602d',
    'Park':     '#3f9826',
    'Verein':   '#ab3cbf',
    'Wasser':   '#2056ac',
    'Sonstige': '#ffffff',
};

const TYPE_TEXT_COLORS = {
    'Feld':     '#000000',
    'Sonstige': '#000000',
};

const DIFF_COLORS = {
    'Anfänger':        '#54846c',
    'Mittel':          '#fcc03c',
    'Fortgeschritten': '#d83c48',
    'Profi':           '#242430',
};

/** Erstellt ein farbiges, kreisförmiges Leaflet-Icon für einen Spot-Typ und Schwierigkeitsgrad */
function buildIcon(spotType, difficulty) {
    const color       = TYPE_COLORS[spotType] || '#fd7e14';
    const borderColor = DIFF_COLORS[difficulty] || '#fff';
    return L.divIcon({
        className: '',
        html: `<div style="
                    background:${color};
                    width:20px; height:20px;
                    border-radius:50%;
                    border:3px solid ${borderColor};
                    box-shadow:0 2px 6px rgba(0,0,0,.45);
               "></div>`,
        iconSize:   [26, 26],
        iconAnchor: [13, 13],
        popupAnchor:[0, -14],
    });
}

// ---------------------------------------------------------------
// Marker-Speicher: { spotId: { marker, spot } }
// ---------------------------------------------------------------
const markerStore = {};

// ---------------------------------------------------------------
// XSS-Schutz: Text sicher in HTML einbetten
// ---------------------------------------------------------------
function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

function escNl2br(str) {
    return esc(str).replace(/\n/g, '<br>');
}

// ---------------------------------------------------------------
// Alle Spots vom Server laden und als Marker eintragen
// ---------------------------------------------------------------
async function loadAllSpots() {
    try {
        const res = await fetch('/public/php/api/spots.php');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const spots = await res.json();
        spots.forEach(addMarker);
        filterMarkersByType();

        // Dashboard-Link: ?spot=ID → zum Spot fliegen (kein Popup)
        if (_urlSpotId && markerStore[_urlSpotId]) {
            const { spot } = markerStore[_urlSpotId];
            map.flyTo([spot.latitude, spot.longitude], 16, { duration: 1.2 });
            // URL bereinigen – kein erneutes Fliegen bei Reload, sessionStorage läuft normal weiter
            history.replaceState(null, '', window.location.pathname);
        }
    } catch (err) {
        console.error('Fehler beim Laden der Spots:', err);
    }
}

/** Legt einen Marker für einen Spot auf der Karte ab */
function addMarker(spot) {
    const marker = L.marker([spot.latitude, spot.longitude], {
        icon: buildIcon(spot.spot_type, spot.difficulty),
        title: spot.name,
    }).addTo(map);

    marker.on('click', () => openSpotDetail(spot));
    markerStore[spot.id] = { marker, spot };
}

// ---------------------------------------------------------------
// Spot-Detail-Offcanvas öffnen
// ---------------------------------------------------------------
async function openSpotDetail(spot) {
    // Schwierigkeits-Farbe für Badge
    const diffColor = {
        'Anfänger':      'success',
        'Mittel':        'warning',
        'Fortgeschritten': 'danger',
        'Profi':         'dark',
    }[spot.difficulty] ?? 'secondary';

    const date = new Date(spot.created_at).toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });

    document.getElementById('spotDetailLabel').innerHTML =
        `${esc(spot.name)} <a href="/public/php/spot_detail.php?id=${spot.id}" class="btn btn-outline-light btn-sm ms-2" title="Detailansicht"><i class="bi bi-search"></i> Details anzeigen</a>`;
    document.getElementById('spotDetailBody').innerHTML = `
        <div class="mb-3">
            <span class="badge me-1" style="background:${TYPE_COLORS[spot.spot_type] ?? '#fd7e14'};color:${TYPE_TEXT_COLORS[spot.spot_type] ?? '#ffffff'}">${esc(spot.spot_type)}</span>
            <span class="badge text-bg-${diffColor}">${esc(spot.difficulty)}</span>
        </div>
        <p class="mb-3">${escNl2br(spot.description || 'Keine Beschreibung vorhanden.')}</p>
        <p class="mb-3"><strong>Parkmöglichkeit:</strong><br>${esc(spot.parking_info || 'Unbekannt')}</p>
        <p class="text-secondary small mb-1">
            <i class="bi bi-geo-alt-fill"></i> ${parseFloat(spot.latitude).toFixed(5)}, ${parseFloat(spot.longitude).toFixed(5)}
        </p>
        <p class="text-secondary small mb-0">
            <i class="bi bi-person-fill"></i> <a href="/profile.php?id=${encodeURIComponent(spot.user_id)}" class="text-info text-decoration-none">${esc(spot.username)}</a> &nbsp;&bull;&nbsp; <i class="bi bi-calendar3"></i> ${date}
        </p>
        <div id="spotPhotoSection"></div>
    `;

    getOrCreateOffcanvas('spotDetailOffcanvas').show();

    try {
        const res = await fetch(`/public/php/api/spot.php?id=${spot.id}`);
        if (!res.ok) return;
        const data = await res.json();
        renderSpotPhotos(spot.id, data.images || [], !!data.has_more_images);
    } catch (e) {}
}

function renderSpotPhotos(spotId, images, hasMore) {
    const section = document.getElementById('spotPhotoSection');
    if (!section || images.length === 0) return;

    let html = '<div class="row g-2 mt-2">';
    images.forEach(filename => {
        const src = '/public/uploads/imgs/' + encodeURIComponent(filename);
        html += `<div class="col-6">
            <div style="aspect-ratio:16/9;overflow:hidden;border-radius:.375rem;">
                <img src="${src}" alt="${esc(filename)}"
                     style="width:100%;height:100%;object-fit:cover;"
                     loading="lazy">
            </div>
        </div>`;
    });
    if (hasMore) {
        html += `<div class="col-6 d-flex align-items-center justify-content-center">
            <a href="/public/php/spot_detail.php?id=${spotId}"
               class="text-info small text-decoration-none fw-semibold">
                Mehr Fotos anzeigen…
            </a>
        </div>`;
    }
    html += '</div>';
    section.innerHTML = html;
}

// ---------------------------------------------------------------
// Karten-Klick: Spot-Erstellen-Offcanvas öffnen
// ---------------------------------------------------------------
map.on('click', (e) => {
    if (!IS_LOGGED_IN) {
        // Nicht eingeloggten Nutzern Toast anzeigen
        new bootstrap.Toast(document.getElementById('loginHintToast')).show();
        return;
    }

    const lat = e.latlng.lat.toFixed(6);
    const lng = e.latlng.lng.toFixed(6);

    // Koordinaten ins Formular übertragen
    document.getElementById('createLat').value = lat;
    document.getElementById('createLng').value = lng;
    document.getElementById('createCoordsDisplay').textContent = `${lat}, ${lng}`;

    // Formular und Alerts zurücksetzen
    document.getElementById('createSpotForm').reset();
    document.getElementById('createLat').value = lat;   // reset() leert auch hidden inputs
    document.getElementById('createLng').value = lng;
    document.getElementById('createCoordsDisplay').textContent = `${lat}, ${lng}`;
    hideAlert('createErrorAlert');
    hideAlert('createSuccessAlert');

    getOrCreateOffcanvas('createSpotOffcanvas').show();
});

// Spot erstellen wird jetzt per klassischem Form-POST an spot_submit.php gesendet.

// ---------------------------------------------------------------
// Hilfsfunktionen für Alert-Anzeige
// ---------------------------------------------------------------
function showAlert(id, message) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
}

function hideAlert(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('d-none');
}

// ---------------------------------------------------------------
// Offcanvas-Instanz holen oder neu anlegen (Bootstrap-Singleton)
// ---------------------------------------------------------------
function getOrCreateOffcanvas(id) {
    const el = document.getElementById(id);
    return bootstrap.Offcanvas.getOrCreateInstance(el);
}

// ---------------------------------------------------------------
// Legende: Spots nach Kategorie filtern
// ---------------------------------------------------------------
function filterMarkersByType() {
    const checkedTypes = new Set(
        Array.from(document.querySelectorAll('.legend-cb[data-filter="type"]:checked')).map(cb => cb.value)
    );
    const checkedDiffs = new Set(
        Array.from(document.querySelectorAll('.legend-cb[data-filter="diff"]:checked')).map(cb => cb.value)
    );

    for (const { marker, spot } of Object.values(markerStore)) {
        if (checkedTypes.has(spot.spot_type) && checkedDiffs.has(spot.difficulty)) {
            if (!map.hasLayer(marker)) map.addLayer(marker);
        } else {
            if (map.hasLayer(marker)) map.removeLayer(marker);
        }
    }
}

function saveLegendToSession() {
    const types = Array.from(document.querySelectorAll('.legend-cb[data-filter="type"]:checked')).map(cb => cb.value);
    const diffs = Array.from(document.querySelectorAll('.legend-cb[data-filter="diff"]:checked')).map(cb => cb.value);
    fetch('/public/php/api/save_legend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ types, diffs }),
    }).catch(() => {});
}

document.querySelectorAll('.legend-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        filterMarkersByType();
        saveLegendToSession();
    });
});

// Legende Dropdown-Toggle
document.getElementById('legendToggle').addEventListener('click', () => {
    const content = document.getElementById('legendContent');
    const arrow   = document.getElementById('legendArrow');
    const btn     = document.getElementById('legendToggle');
    const open    = content.hidden;
    content.hidden = !open;
    btn.setAttribute('aria-expanded', open);
    arrow.innerHTML = open ? '<i class="bi bi-chevron-up"></i>' : '<i class="bi bi-chevron-down"></i>';
});

// Legende schließen, wenn das Header-Dropdown aufgeht
document.addEventListener('show.bs.dropdown', () => {
    const content = document.getElementById('legendContent');
    if (!content.hidden) {
        content.hidden = true;
        document.getElementById('legendToggle').setAttribute('aria-expanded', 'false');
        document.getElementById('legendArrow').innerHTML = '<i class="bi bi-chevron-down"></i>';
    }
});

// ---------------------------------------------------------------
// App starten
// ---------------------------------------------------------------
loadAllSpots();

// ---------------------------------------------------------------
// Standort-Feature: Erstbesuch-Modal + Marker
// ---------------------------------------------------------------
const LOCATION_KEY = 'location_consent';
const LOCATION_COOKIE_DAYS = 365;

function getLocationCookie() {
    const match = document.cookie.split('; ').find(row => row.startsWith(LOCATION_KEY + '='));
    return match ? match.split('=')[1] : null;
}

function setLocationCookie(value) {
    const expires = new Date(Date.now() + LOCATION_COOKIE_DAYS * 864e5).toUTCString();
    document.cookie = `${LOCATION_KEY}=${value}; expires=${expires}; path=/; SameSite=Lax`;
}

function showUserLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;
            const userIcon = L.divIcon({
                className: '',
                html: '<div class="user-location-marker"></div>',
                iconSize:   [20, 20],
                iconAnchor: [10, 10],
            });
            const userMarker = L.marker([latitude, longitude], { icon: userIcon, zIndexOffset: 1000 })
                .addTo(map)
                .bindPopup('Dein Standort');

            const btn = document.getElementById('locateMeBtn');
            btn.addEventListener('click', () => {
                map.setView([latitude, longitude], 13);
                userMarker.openPopup();
            });
        },
        () => { /* Standort nicht verfügbar – still ignorieren */ }
    );
}

function initLocationRequest() {
    const consent = getLocationCookie();
    if (consent === 'granted') {
        showUserLocation();
        return;
    }

    const btn   = document.getElementById('locateMeBtn');
    const modal = new bootstrap.Modal(document.getElementById('locationModal'), { backdrop: 'static' });

    function openModal() {
        document.getElementById('locationAllowBtn').addEventListener('click', () => {
            setLocationCookie('granted');
            modal.hide();
            btn.removeEventListener('click', openModal);
            showUserLocation();
        }, { once: true });

        document.getElementById('locationDenyBtn').addEventListener('click', () => {
            setLocationCookie('denied');
            modal.hide();
        }, { once: true });

        modal.show();
    }

    btn.addEventListener('click', openModal);

    // Erstbesuch (kein Cookie): Modal wie bisher nach 800 ms automatisch zeigen
    if (!consent) {
        setTimeout(openModal, 800);
    }
}

initLocationRequest();
