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

// Betriebsklasse des Piloten: u250 / c1c2 / a3. Serverseitig aus dem Cookie
// pilot_class gerendert (siehe private/php/core/geozones.php). Fehlt das
// Meta-Tag, gilt bewusst a3 – die strengste Annahme.
let PILOT_CLASS = (document.querySelector('meta[name="app-pilot-class"]') || {}).content || 'a3';

// ---------------------------------------------------------------
// Reichweitenmessung
//
// window.fpvTrack stammt aus public/js/analytics.js und existiert
// nur, wenn ueberhaupt gemessen wird – bei Opt-out, DNT oder
// Admin-Session rendert der Server das Script gar nicht erst.
// Deshalb hier ein Wrapper statt direkter Aufrufe; die Karte darf
// nicht davon abhaengen, ob Tracking aktiv ist.
//
// Es werden ausschliesslich Kategorien und oeffentliche Spot-IDs
// gemeldet, nie Koordinaten oder Nutzerdaten.
// ---------------------------------------------------------------
function track(name, data) {
    if (typeof window.fpvTrack === 'function') window.fpvTrack(name, data);
}

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
const map = L.map('map', { zoomControl: false, wheelPxPerZoomLevel: 120 }).setView(
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

// ---------------------------------------------------------------
// Tile-Layer: Straße (OSM), Satellit (Esri World Imagery) und Hybrid (Satellit + Beschriftungen)
// ---------------------------------------------------------------
const ESRI_IMAGERY_ATTR = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';

const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: MAP_MAX_ZOOM,
});

const satelliteLayer = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: ESRI_IMAGERY_ATTR,
    maxZoom: MAP_MAX_ZOOM,
});

const hybridLayer = L.layerGroup([
    L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: ESRI_IMAGERY_ATTR,
        maxZoom: MAP_MAX_ZOOM,
    }),
    L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}', {
        attribution: '',
        maxZoom: MAP_MAX_ZOOM,
    }),
    L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        attribution: '',
        maxZoom: MAP_MAX_ZOOM,
    }),
]);

const baseLayers = { 'Straße': streetLayer, 'Satellit': satelliteLayer, 'Hybrid': hybridLayer };
const savedLayerName = (() => { try { return sessionStorage.getItem('fpv_map_layer'); } catch (_) { return null; } })();
let activeBaseLayerName = baseLayers[savedLayerName] ? savedLayerName : 'Straße';
baseLayers[activeBaseLayerName].addTo(map);

// ---------------------------------------------------------------
// Basiskarten-Umschalter: schwebende Icon-Leiste statt der klassischen
// Leaflet-Radiobox – bleibt immer sichtbar, kein Aufklappen nötig.
// ---------------------------------------------------------------
const BASE_LAYER_ICONS = { 'Straße': 'bi-map', 'Satellit': 'bi-image', 'Hybrid': 'bi-layers-half' };

const BaseLayerSwitch = L.Control.extend({
    options: { position: 'bottomleft' },
    onAdd: function () {
        const container = L.DomUtil.create('div', 'layer-switch');
        L.DomEvent.disableClickPropagation(container);
        Object.keys(baseLayers).forEach((name) => {
            const btn = L.DomUtil.create('button', 'layer-switch-btn', container);
            btn.type = 'button';
            btn.title = name;
            btn.setAttribute('aria-label', name);
            btn.setAttribute('aria-pressed', String(name === activeBaseLayerName));
            btn.classList.toggle('active', name === activeBaseLayerName);
            btn.innerHTML = `<i class="bi ${BASE_LAYER_ICONS[name]}" aria-hidden="true"></i>`;
            L.DomEvent.on(btn, 'click', () => selectBaseLayer(name));
        });
        return container;
    },
});

function selectBaseLayer(name) {
    if (name === activeBaseLayerName || !baseLayers[name]) return;
    map.removeLayer(baseLayers[activeBaseLayerName]);
    map.addLayer(baseLayers[name]);
    activeBaseLayerName = name;
    try { sessionStorage.setItem('fpv_map_layer', name); } catch (_) { /* sessionStorage nicht verfügbar – ignorieren */ }
    baseLayerSwitchBox.querySelectorAll('.layer-switch-btn').forEach((btn) => {
        const isActive = btn.title === name;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-pressed', String(isActive));
    });
    track('karte:layer', { layer: name });
}

const baseLayerSwitchBox = new BaseLayerSwitch().addTo(map).getContainer();

// ---------------------------------------------------------------
// DIPUL-Geozonen als Overlay
//
// Die Kacheln kommen ausschliesslich vom eigenen Proxy /api/dipul_wms.php:
// die CSP erlaubt nur 'self', und so erreicht keine Besucher-IP die DFS.
// Die Buendelnamen muessen zu DIPUL_BUNDLES in
// private/php/core/geozones.php passen – dort liegt die einzige Quelle
// der Wahrheit fuer die Layer-Zuordnung.
//
// Unterhalb von Zoom 9 liefert der Proxy bewusst eine leere Kachel
// (Cache-Schutz), deshalb dieselbe Schwelle hier als minZoom.
// ---------------------------------------------------------------
const DIPUL_ATTRIBUTION = 'Quelle Geodaten: DFS, BKG';
const DIPUL_MIN_ZOOM    = 9;
const OVERLAY_STATE_KEY = 'fpv_map_overlays';

function dipulLayer(bundle) {
    return L.tileLayer.wms('/api/dipul_wms.php', {
        layers:      bundle,
        format:      'image/png',
        transparent: true,
        version:     '1.3.0',
        opacity:     0.6,
        minZoom:     DIPUL_MIN_ZOOM,
        maxZoom:     MAP_MAX_ZOOM,
        attribution: DIPUL_ATTRIBUTION,
    });
}

const overlayLayers = {
    'Luftverkehr':          dipulLayer('luftverkehr'),
    'Naturschutz':          dipulLayer('naturschutz'),
    'Siedlung & Behörden':  dipulLayer('siedlung'),
    'Infrastruktur':        dipulLayer('infrastruktur'),
    'Temporäre Sperrungen': dipulLayer('temporaer'),
};

// Zuletzt gewaehlte Overlays wiederherstellen (analog fpv_map_layer)
try {
    const savedOverlays = JSON.parse(sessionStorage.getItem(OVERLAY_STATE_KEY) || '[]');
    if (Array.isArray(savedOverlays)) {
        savedOverlays.forEach((name) => {
            if (overlayLayers[name]) overlayLayers[name].addTo(map);
        });
    }
} catch (_) { /* sessionStorage nicht verfügbar – ignorieren */ }

const layerControl = L.control.layers({}, overlayLayers, { position: 'bottomleft' }).addTo(map);

// Leaflet graut Overlay-Eintraege automatisch aus, solange der Kartenzoom unter
// deren minZoom liegt – ohne Erklaerung sieht das nach einem Fehler aus.
// Deshalb: Tooltip an jedem Eintrag und der Zoom-Hinweis erscheint bereits beim
// Aufklappen der Layer-Kontrolle, nicht erst bei aktivem Overlay.
const layerControlBox = layerControl.getContainer();
layerControlBox.querySelectorAll('.leaflet-control-layers-overlays label').forEach((label) => {
    label.title = 'Geozonen sind erst ab Zoomstufe ' + DIPUL_MIN_ZOOM + ' verfügbar';
});

map.on('overlayadd overlayremove', () => {
    try {
        const active = Object.keys(overlayLayers).filter((name) => map.hasLayer(overlayLayers[name]));
        sessionStorage.setItem(OVERLAY_STATE_KEY, JSON.stringify(active));
    } catch (_) {}
});

// Nur das Einschalten melden: interessant ist, welches Geozonen-Bundel
// ueberhaupt gebraucht wird. Das Ausschalten verdoppelt nur die Ereignisse
// und damit den Verbrauch am Monatskontingent.
map.on('overlayadd', (e) => {
    track('karte:geozonen', { bundel: e.name });
});

// Faellt die DFS aus, antwortet der Proxy mit einer transparenten Kachel und
// Status 200 – ein echter tileerror bedeutet also ein Netz-/Serverproblem.
// Genau einmal melden, sonst flutet jede fehlende Kachel die Konsole.
let dipulErrorReported = false;
Object.values(overlayLayers).forEach((layer) => {
    layer.on('tileerror', () => {
        if (dipulErrorReported) return;
        dipulErrorReported = true;
        console.warn('Geozonen-Overlay derzeit nicht erreichbar – bitte auf dipul.de prüfen.');
    });
});

// Hinweis einblenden, solange ein Overlay aktiv ist, der Zoom aber zu klein.
// Ohne ihn wirkt die leere Karte wie "hier gibt es keine Zonen".
//
// Position bewusst 'bottomleft': Leaflet haengt Controls in den unteren Ecken
// VOR die bereits vorhandenen (Control.addTo -> insertBefore), der Hinweis
// steht dadurch immer direkt ueber der Layer-Box – also genau dort, wo der
// Nutzer die Geozonen einschalten will. Unten rechts wurde er uebersehen.
const dipulZoomHint = L.control({ position: 'bottomleft' });
dipulZoomHint.onAdd = function () {
    const div = L.DomUtil.create('div', 'dipul-zoom-hint');
    div.setAttribute('role', 'status');
    const icon = L.DomUtil.create('i', 'bi bi-exclamation-triangle-fill', div);
    icon.setAttribute('aria-hidden', 'true');
    const text = L.DomUtil.create('span', '', div);
    text.textContent = 'Geozonen erst ab Zoomstufe ' + DIPUL_MIN_ZOOM + ' – näher heranzoomen';
    return div;
};
let dipulZoomHintVisible = false;

function updateDipulZoomHint() {
    const anyOverlay = Object.keys(overlayLayers).some((name) => map.hasLayer(overlayLayers[name]));
    const expanded   = layerControlBox.classList.contains('leaflet-control-layers-expanded');
    const needed     = (anyOverlay || expanded) && map.getZoom() < DIPUL_MIN_ZOOM;
    if (needed && !dipulZoomHintVisible) {
        dipulZoomHint.addTo(map);
        dipulZoomHintVisible = true;
    } else if (!needed && dipulZoomHintVisible) {
        map.removeControl(dipulZoomHint);
        dipulZoomHintVisible = false;
    }
}

map.on('overlayadd overlayremove zoomend', updateDipulZoomHint);
// Auf-/Zuklappen loest kein Leaflet-Event aus – daher direkt am Container lauschen.
['mouseenter', 'mouseleave', 'click'].forEach((ev) => {
    layerControlBox.addEventListener(ev, updateDipulZoomHint);
});
updateDipulZoomHint();

// ---------------------------------------------------------------
// Spot-Typ → Marker-Farbe
// ---------------------------------------------------------------
const TYPE_COLORS = {
    'Bando':    '#4b4e5a',
    'Feld':     '#ffe224',
    'Gebirge':  '#b1602d',
    'Park':     '#4ab52c',
    'Wald':     '#2d6a4f',
    'Windpark': '#74c2e0',
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
        const res = await fetch('/api/spots.php');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const spots = await res.json();
        spots.forEach(addMarker);
        filterMarkersByType();

        // Dashboard-Link: ?spot=ID → zum Spot fliegen (kein Popup)
        if (_urlSpotId && markerStore[_urlSpotId]) {
            const { spot } = markerStore[_urlSpotId];
            map.flyTo([spot.latitude, spot.longitude], 16, { duration: 1.2 });
            // Der A3-Hinweis auf der Spot-Detailseite verlinkt hierher, um den
            // 150-m-Radius zu zeigen. Der Kreis wird deshalb direkt gezeichnet –
            // showA3Circle() prueft selbst, ob die Betriebsklasse a3 aktiv ist.
            showA3Circle(parseFloat(spot.latitude), parseFloat(spot.longitude));
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
// A3-Abstandskreis (150 m)
//
// Jeder Eigenbau ab 250 g ohne C-Klasse – also der typische 5-Zöller –
// fliegt in der EU-Unterkategorie A3 und muss 150 m Abstand zu Wohn-,
// Gewerbe-, Industrie- und Erholungsgebieten halten. Diese Regel steht
// auf keiner Behördenkarte, deshalb zeichnen wir nur den Radius; ob im
// Kreis ein solches Gebiet liegt, beurteilt der Pilot selbst.
// ---------------------------------------------------------------
const A3_RADIUS_M = 150;
let a3Circle = null;
let _activeSpot = null;

function clearA3Circle() {
    if (a3Circle) {
        map.removeLayer(a3Circle);
        a3Circle = null;
    }
}

function showA3Circle(lat, lon) {
    clearA3Circle();
    if (PILOT_CLASS !== 'a3') return;
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

    a3Circle = L.circle([lat, lon], {
        radius:      A3_RADIUS_M,
        color:       '#fcc03c',
        weight:      2,
        dashArray:   '6 4',
        fillColor:   '#fcc03c',
        fillOpacity: 0.08,
        interactive: false,
    }).addTo(map);
}

// Kreis verschwindet mit dem Offcanvas, sonst bleibt er beim nächsten Spot stehen.
// Ausnahme: Im Platzierungs-Modus bzw. bei gesetztem Vorschau-Marker gehoert der
// Kreis bereits zum neuen Spot – dieses Event trifft dann verspaetet ein.
document.getElementById('spotDetailOffcanvas')
    .addEventListener('hidden.bs.offcanvas', () => {
        if (!_placingMode && !_newSpotMarker) clearA3Circle();
        _activeSpot = null;
    });

// ---------------------------------------------------------------
// Spot-Detail-Offcanvas öffnen
// ---------------------------------------------------------------
async function openSpotDetail(spot) {
    _activeSpot = spot;

    // Kategorien und oeffentliche Spot-ID, bewusst ohne Koordinaten:
    // die Position eines Spots ist zwar oeffentlich, gehoert aber nicht
    // in die Ereignisdaten (siehe Datenschutzerklaerung).
    track('spot:detail', {
        spot:            spot.id,
        typ:             spot.spot_type,
        schwierigkeit:   spot.difficulty,
        groesse:         spot.copter_size || 'unbekannt',
        ansicht:         'karte',
    });

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
        `${esc(spot.name)} <a href="/public/php/spot_detail.php?id=${spot.id}" class="btn btn-outline-light btn-sm ms-2" title="Detailansicht"><i class="bi bi-info-circle"></i> Details anzeigen</a>`;
    document.getElementById('spotDetailBody').innerHTML = `
        <div class="mb-3">
            <span class="badge me-1" style="background:${TYPE_COLORS[spot.spot_type] ?? '#fd7e14'};color:${TYPE_TEXT_COLORS[spot.spot_type] ?? '#ffffff'}">${esc(spot.spot_type)}</span>
            <span class="badge text-bg-${diffColor}">${esc(spot.difficulty)}</span>
            ${spot.copter_size ? spot.copter_size.split(',').map(s => `<span class="badge text-bg-secondary ms-1">${esc(s.trim())}</span>`).join('') : ''}
        </div>
        <p class="mb-3">${escNl2br(spot.description || 'Keine Beschreibung vorhanden.')}</p>
        <p class="mb-3"><strong>Parkmöglichkeit:</strong><br>${esc(spot.parking_info || 'Unbekannt')}</p>
        <p class="text-secondary small mb-1">
            <i class="bi bi-geo-alt-fill"></i> ${parseFloat(spot.latitude).toFixed(5)}, ${parseFloat(spot.longitude).toFixed(5)}
        </p>
        ${PILOT_CLASS === 'a3' ? `<div class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>A3:</strong> Der gelbe Kreis zeigt 150 m Abstand.
            Liegt darin ein Wohn-, Gewerbe-, Industrie- oder Erholungsgebiet, darfst du hier nicht starten.
        </div>` : ''}
        <p class="text-secondary small mb-0">
            <i class="bi bi-person-fill"></i> <a href="#" class="text-info text-decoration-none profile-link" data-user-id="${encodeURIComponent(spot.user_id)}">${esc(spot.username)}</a> &nbsp;&bull;&nbsp; <i class="bi bi-calendar3"></i> ${date}
        </p>
        <div id="spotPhotoSection"></div>
    `;

    showA3Circle(parseFloat(spot.latitude), parseFloat(spot.longitude));
    getOrCreateOffcanvas('spotDetailOffcanvas').show();

    try {
        const res = await fetch(`/api/spot.php?id=${spot.id}`);
        if (!res.ok) return;
        const data = await res.json();
        renderSpotPhotos(spot.id, data.images || [], !!data.has_more_images);
    } catch (e) {}
}

// Vorschaubild-URL zu einem Upload-Dateinamen: thumbs/<name>.jpg.
// Erzeugt werden die Dateien serverseitig, siehe private/php/core/image_utils.php.
function thumbSrc(filename) {
    const base = String(filename).replace(/\.[^.]+$/, '');
    return '/public/uploads/imgs/thumbs/' + encodeURIComponent(base) + '.jpg';
}

function renderSpotPhotos(spotId, images, hasMore) {
    const section = document.getElementById('spotPhotoSection');
    if (!section || images.length === 0) return;

    let html = '<div class="row g-2 mt-2">';
    images.forEach(filename => {
        const src   = '/public/uploads/imgs/' + encodeURIComponent(filename);
        const thumb = thumbSrc(filename);
        // Erster Fehler: auf das Original zurueckfallen (Thumbnail fehlt).
        // Zweiter Fehler: die Kachel ausblenden, das Bild gibt es nicht mehr.
        const onErr = `if(!this.dataset.fb){this.dataset.fb='1';this.src='${src}';}`
                    + `else{this.closest('.col-6').style.display='none';}`;
        html += `<div class="col-6">
            <div style="aspect-ratio:16/9;overflow:hidden;border-radius:.375rem;">
                <img src="${thumb}" alt="${esc(filename)}"
                     style="width:100%;height:100%;object-fit:cover;"
                     loading="lazy"
                     onerror="${onErr}">
            </div>
        </div>`;
    });
    if (hasMore) {
        html += `<div class="col-6 d-flex align-items-center justify-content-center">
            <a href="/spot_detail.php?id=${spotId}"
               class="text-info small text-decoration-none fw-semibold">
                Mehr Fotos anzeigen…
            </a>
        </div>`;
    }
    html += '</div>';
    section.innerHTML = html;
}

// ---------------------------------------------------------------
// Spot erstellen: Platzierungs-Modus
//
// Ein Karten-Klick allein legt bewusst KEINEN Spot mehr an – das
// oeffnete das Offcanvas auch beim Zuklappen von Legende oder
// Layer-Box und beim Danebentippen neben einen Marker. Stattdessen
// schaltet der Button "Spot erstellen" die Karte in einen Modus,
// in dem genau ein Klick die Koordinaten setzt; danach endet er.
// ---------------------------------------------------------------
let _pendingAction = null;
let _forceClose    = false;
let _placingMode   = false;

function createFormHasData() {
    const form = document.getElementById('createSpotForm');
    return form.querySelector('[name="name"]').value.trim() !== '' ||
           form.querySelector('[name="description"]').value.trim() !== '' ||
           form.querySelector('[name="spot_type"]').value !== '' ||
           form.querySelector('[name="difficulty"]').value !== '';
}

function applyNewSpotLocation(lat, lng) {
    document.getElementById('createSpotForm').reset();
    document.getElementById('createLat').value = lat;
    document.getElementById('createLng').value = lng;
    document.getElementById('createCoordsDisplay').textContent = `${lat}, ${lng}`;
    hideAlert('createErrorAlert');
    hideAlert('createSuccessAlert');
    getOrCreateOffcanvas('createSpotOffcanvas').show();
}

// Offcanvas-Close abfangen: Bootstrap feuert dieses Event BEVOR es das Offcanvas schließt,
// also noch bevor Leaflet den Map-Click verarbeitet. Nur so können wir rechtzeitig blockieren.
document.getElementById('createSpotOffcanvas').addEventListener('hide.bs.offcanvas', (e) => {
    if (_forceClose) return;
    if (createFormHasData()) {
        e.preventDefault();
        new bootstrap.Modal(document.getElementById('discardSpotModal')).show();
    }
});

// ---------------------------------------------------------------
// Vorschau-Marker fuer den noch nicht gespeicherten Spot
// ---------------------------------------------------------------
let _newSpotMarker = null;

function clearNewSpotMarker() {
    if (_newSpotMarker) {
        map.removeLayer(_newSpotMarker);
        _newSpotMarker = null;
    }
}

function setNewSpotMarker(latlng) {
    clearNewSpotMarker();
    _newSpotMarker = L.marker(latlng, {
        icon: L.divIcon({
            className:  '',
            html:       '<div class="new-spot-marker"></div>',
            iconSize:   [22, 22],
            iconAnchor: [11, 11],
        }),
        zIndexOffset: 1000,
        interactive:  false,
    }).addTo(map);

    // Zeigt beim Anlegen denselben 150-m-Radius wie die Spot-Detailansicht
    showA3Circle(latlng.lat, latlng.lng);
}

// ---------------------------------------------------------------
// Modus betreten / verlassen
// ---------------------------------------------------------------
function enterPlacingMode() {
    _placingMode = true;
    document.body.classList.add('spot-placing');
    document.getElementById('placingHint').hidden = false;

    const btn = document.getElementById('createSpotBtn');
    btn.classList.add('active');
    btn.setAttribute('aria-pressed', 'true');
    document.getElementById('createSpotBtnLabel').textContent = 'Abbrechen';
    document.getElementById('createSpotBtnIcon').className = 'bi bi-x-lg';

    // Offene Spot-Detailansicht weicht der Platzierung. Deren Kreis wird
    // hier direkt entfernt, weil der verspaetete hidden-Handler ihn im
    // Modus bewusst stehen laesst.
    const detailEl = document.getElementById('spotDetailOffcanvas');
    if (detailEl.classList.contains('show')) {
        getOrCreateOffcanvas('spotDetailOffcanvas').hide();
    }
    clearA3Circle();

    // Legende einklappen – sie ist im Modus ausgeblendet und soll
    // danach nicht aufgeklappt wieder auftauchen.
    closeLegend();
}

function exitPlacingMode() {
    _placingMode = false;
    document.body.classList.remove('spot-placing');
    document.getElementById('placingHint').hidden = true;

    const btn = document.getElementById('createSpotBtn');
    btn.classList.remove('active');
    btn.setAttribute('aria-pressed', 'false');
    document.getElementById('createSpotBtnLabel').textContent = 'Spot erstellen';
    document.getElementById('createSpotBtnIcon').className = 'bi bi-plus-lg';
}

// ---------------------------------------------------------------
// Karten-Klick: nur im Platzierungs-Modus wirksam
// ---------------------------------------------------------------
map.on('click', (e) => {
    if (!_placingMode) return;

    exitPlacingMode();
    setNewSpotMarker(e.latlng);
    applyNewSpotLocation(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
});

// ---------------------------------------------------------------
// Button "Spot erstellen"
// ---------------------------------------------------------------
document.getElementById('createSpotBtn').addEventListener('click', () => {
    if (!IS_LOGGED_IN) {
        new bootstrap.Modal(document.getElementById('loginModal')).show();
        return;
    }

    if (_placingMode) {
        exitPlacingMode();
        return;
    }

    // Offenes Formular mit Eingaben: erst nachfragen, dann neu platzieren
    const offcanvasEl = document.getElementById('createSpotOffcanvas');
    if (offcanvasEl.classList.contains('show')) {
        if (createFormHasData()) {
            _pendingAction = enterPlacingMode;
            new bootstrap.Modal(document.getElementById('discardSpotModal')).show();
            return;
        }
        _forceClose = true;
        getOrCreateOffcanvas('createSpotOffcanvas').hide();
        _forceClose = false;
    }

    enterPlacingMode();
});

// Abbrechen ueber das Banner oder die Escape-Taste
document.getElementById('placingCancelBtn').addEventListener('click', exitPlacingMode);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && _placingMode) exitPlacingMode();
});

// Vorschau-Marker und Kreis verschwinden mit dem Offcanvas
document.getElementById('createSpotOffcanvas')
    .addEventListener('hidden.bs.offcanvas', () => {
        clearNewSpotMarker();
        clearA3Circle();
    });

document.getElementById('discardConfirmBtn').addEventListener('click', () => {
    bootstrap.Modal.getInstance(document.getElementById('discardSpotModal')).hide();
    _forceClose = true;
    getOrCreateOffcanvas('createSpotOffcanvas').hide();
    _forceClose = false;
    if (_pendingAction) {
        _pendingAction();
        _pendingAction = null;
    }
});

document.getElementById('discardCancelBtn').addEventListener('click', () => {
    bootstrap.Modal.getInstance(document.getElementById('discardSpotModal')).hide();
    _pendingAction = null;
});

// Spot erstellen wird jetzt per klassischem Form-POST an spot_submit.php gesendet.

// ---------------------------------------------------------------
// Profillink-Klick: Login-Modal für nicht eingeloggte Nutzer
// ---------------------------------------------------------------
document.addEventListener('click', (e) => {
    const link = e.target.closest('.profile-link');
    if (!link) return;
    e.preventDefault();
    if (!IS_LOGGED_IN) {
        new bootstrap.Modal(document.getElementById('loginModal')).show();
        return;
    }
    window.location.href = '/profile.php?id=' + link.dataset.userId;
});

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
    const checkedSizes = new Set(
        Array.from(document.querySelectorAll('.legend-cb[data-filter="size"]:checked')).map(cb => cb.value)
    );

    for (const { marker, spot } of Object.values(markerStore)) {
        const passesSize = (() => {
            if (!spot.copter_size) return true; // Größe nicht angegeben → immer anzeigen
            return spot.copter_size.split(',').some(s => checkedSizes.has(s.trim()));
        })();
        if (checkedTypes.has(spot.spot_type) && checkedDiffs.has(spot.difficulty) && passesSize) {
            if (!map.hasLayer(marker)) map.addLayer(marker);
        } else {
            if (map.hasLayer(marker)) map.removeLayer(marker);
        }
    }
}

function saveLegendToSession() {
    const types = Array.from(document.querySelectorAll('.legend-cb[data-filter="type"]:checked')).map(cb => cb.value);
    const diffs = Array.from(document.querySelectorAll('.legend-cb[data-filter="diff"]:checked')).map(cb => cb.value);
    const sizes = Array.from(document.querySelectorAll('.legend-cb[data-filter="size"]:checked')).map(cb => cb.value);
    fetch('/api/save_legend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ types, diffs, sizes }),
        keepalive: true,
    }).catch(() => {});
}

// Der Filter wird beim Durchklicken der Legende schnell hintereinander
// mehrfach ausgeloest. Ein Ereignis pro Haken wuerde das Monatskontingent
// unnoetig belasten und misst ausserdem nur Zwischenstaende – deshalb erst
// melden, wenn eine Sekunde lang nichts mehr passiert ist.
let _filterTrackTimer = null;

function trackFilterChange() {
    clearTimeout(_filterTrackTimer);
    _filterTrackTimer = setTimeout(() => {
        const zahl = (dim) =>
            document.querySelectorAll('.legend-cb[data-filter="' + dim + '"]:checked').length;
        track('karte:filter', { typen: zahl('type'), schwierigkeit: zahl('diff'), groessen: zahl('size') });
    }, 1000);
}

document.querySelectorAll('.legend-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        filterMarkersByType();
        saveLegendToSession();
        trackFilterChange();
    });
});

// ---------------------------------------------------------------
// Legende auf-/zuklappen
//
// closeLegend() ist die eine Stelle, die den Zustand zurueckdreht –
// sie wird aus mehreren Richtungen gerufen (Header-Dropdown,
// Platzierungs-Modus, Karten-Klick).
// ---------------------------------------------------------------
function closeLegend() {
    const content = document.getElementById('legendContent');
    if (content.hidden) return;
    content.hidden = true;
    document.getElementById('legendToggle').setAttribute('aria-expanded', 'false');
    document.getElementById('legendArrow').innerHTML = '<i class="bi bi-chevron-down"></i>';
}

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
document.addEventListener('show.bs.dropdown', closeLegend);

// Legende schließen, sobald in die Karte geklickt wird. Die Legende
// und die Kartenkontrollen liegen ausserhalb des Karten-Containers
// bzw. sind von Leaflet abgeschirmt – ihre Klicks landen hier nicht.
map.on('click', closeLegend);

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

            // Aktiv-Zustand wie beim gewaehlten Kartenlayer: blau,
            // sobald ein Standort vorliegt und der Button etwas tut.
            const btn = document.getElementById('locateMeBtn');
            btn.classList.add('active');
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
            track('karte:standort', { entscheidung: 'erlaubt' });
            showUserLocation();
        }, { once: true });

        document.getElementById('locationDenyBtn').addEventListener('click', () => {
            setLocationCookie('denied');
            modal.hide();
            track('karte:standort', { entscheidung: 'abgelehnt' });
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

// Parking-Checkbox: Textarea freischalten/sperren
var parkingUnknown = document.getElementById('parkingUnknown');
if (parkingUnknown) {
    parkingUnknown.addEventListener('change', function () {
        var ta = document.getElementById('parkingInfo');
        if (this.checked) {
            ta.disabled = true;
            ta.value = '';
        } else {
            ta.disabled = false;
            ta.focus();
        }
    });
}

// ---------------------------------------------------------------
// Betriebsklassen-Umschalter in der Legende
//
// Speichert die Wahl im Cookie pilot_class (serverseitig, HttpOnly)
// und zeichnet den 150-m-Kreis sofort neu, damit die Umschaltung ohne
// Reload sichtbar wird. Schlaegt der Request fehl, bleibt die Anzeige
// trotzdem korrekt – nur die Persistenz fehlt.
// ---------------------------------------------------------------
document.querySelectorAll('.pilot-class-cb').forEach((radio) => {
    radio.addEventListener('change', async () => {
        if (!radio.checked) return;
        PILOT_CLASS = radio.value;

        // Offener Spot: Kreis und Hinweis an die neue Klasse anpassen
        if (_activeSpot) {
            openSpotDetail(_activeSpot);
        } else {
            clearA3Circle();
        }

        try {
            await fetch('/api/save_pilot_class.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ class: radio.value }),
            });
        } catch (_) { /* Netzfehler – Auswahl gilt nur für diese Sitzung */ }
    });
});
