// =============================================================
// Karte erzeugen und Kartenzustand persistieren (ES-Modul)
// =============================================================
import { config, state } from './state.js';

const { mapStateKey: MAP_STATE_KEY, mapDefaultCenter: MAP_DEFAULT_CENTER,
        mapDefaultZoom: MAP_DEFAULT_ZOOM, mapMaxZoom: MAP_MAX_ZOOM } = config;

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

/**
 * Erzeugt die Leaflet-Karte und legt sie in state.map ab.
 * @param {?string} urlSpotId ?spot=ID – bei direktem Spot-Link wird der
 *   gespeicherte Zustand ignoriert, damit flyTo ungestoert greift.
 */
export function createMap(urlSpotId) {
    const saved = urlSpotId ? null : readSavedMapState();
    const map = L.map('map', { zoomControl: false, wheelPxPerZoomLevel: 120 }).setView(
        saved ? [saved.lat, saved.lng] : MAP_DEFAULT_CENTER,
        saved ? saved.zoom : MAP_DEFAULT_ZOOM
    );
    state.map = map;

    // Zustand bei jeder Kartenbewegung / Zoomaenderung persistieren
    map.on('moveend zoomend', () => {
        try {
            const c = map.getCenter();
            sessionStorage.setItem(MAP_STATE_KEY, JSON.stringify({
                lat: c.lat, lng: c.lng, zoom: map.getZoom(),
            }));
        } catch (_) { /* sessionStorage nicht verfuegbar – ignorieren */ }
    });
    return map;
}
