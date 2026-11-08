// =============================================================
// Spot-Layer: Marker laden, anzeigen und filtern (ES-Modul)
// =============================================================
import { state } from '../state.js';
import { buildIcon } from '../spot_style.js';
import { showA3Circle } from '../features/a3_circle.js';

// Marker-Speicher: { spotId: { marker, spot } }
export const markerStore = {};

// ---------------------------------------------------------------
// Spots laden – nur der sichtbare Ausschnitt (bbox)
//
// Beim Start und nach jeder Kartenbewegung wird der Ausschnitt samt
// Rand (50 %) geladen. Bereits geladene Rechtecke werden gemerkt: liegt
// die Ansicht komplett darin, entfaellt der Request. Marker werden nie
// entfernt (Cache), doppelte IDs ueberspringt addMarker.
// ---------------------------------------------------------------
const loadedRects = [];   // [{minLng, minLat, maxLng, maxLat}]

function rectContainsView(r, v) {
    return r.minLng <= v.minLng && r.minLat <= v.minLat && r.maxLng >= v.maxLng && r.maxLat >= v.maxLat;
}

const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n));

/** Laedt alle Spots im aktuellen Kartenausschnitt (plus Rand) nach */
export async function loadSpotsInView(onSelect) {
    const b = state.map.getBounds().pad(0.5);
    const rect = {
        minLng: clamp(b.getWest(),  -180, 180), minLat: clamp(b.getSouth(), -90, 90),
        maxLng: clamp(b.getEast(),  -180, 180), maxLat: clamp(b.getNorth(), -90, 90),
    };
    const view = state.map.getBounds();
    const v = { minLng: view.getWest(), minLat: view.getSouth(), maxLng: view.getEast(), maxLat: view.getNorth() };
    if (loadedRects.some(r => rectContainsView(r, v))) return;

    const bbox = [rect.minLng, rect.minLat, rect.maxLng, rect.maxLat].map(n => n.toFixed(6)).join(',');
    try {
        const res = await fetch('/api/spots.php?bbox=' + encodeURIComponent(bbox));
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const spots = await res.json();
        spots.forEach((spot) => addMarker(spot, onSelect));
        loadedRects.push(rect);
        filterMarkersByType();
    } catch (err) {
        console.error('Fehler beim Laden der Spots:', err);
    }
}

// ---------------------------------------------------------------
// Start: Spots laden und bei Kartenbewegung nachladen
// ---------------------------------------------------------------
/**
 * @param {{onSelect: (spot: object) => void, focusSpotId: ?string}} opts
 *   onSelect    – Klick auf einen Marker (oeffnet das Spot-Panel)
 *   focusSpotId – ?spot=ID aus der URL: dorthin fliegen
 */
export async function loadAllSpots({ onSelect, focusSpotId }) {
    if (focusSpotId) {
        // Der Spot kann ausserhalb des Ausschnitts liegen → einmal alles laden
        try {
            const res = await fetch('/api/spots.php');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const spots = await res.json();
            spots.forEach((spot) => addMarker(spot, onSelect));
            loadedRects.push({ minLng: -180, minLat: -90, maxLng: 180, maxLat: 90 });
            filterMarkersByType();

            // Dashboard-Link: ?spot=ID → zum Spot fliegen (kein Popup)
            if (markerStore[focusSpotId]) {
                const { spot } = markerStore[focusSpotId];
                state.map.flyTo([spot.latitude, spot.longitude], 16, { duration: 1.2 });
                // Der A3-Hinweis auf der Spot-Detailseite verlinkt hierher, um den
                // 150-m-Radius zu zeigen. showA3Circle() prueft selbst, ob a3 aktiv ist.
                showA3Circle(parseFloat(spot.latitude), parseFloat(spot.longitude));
                // URL bereinigen – kein erneutes Fliegen bei Reload
                history.replaceState(null, '', window.location.pathname);
            }
        } catch (err) {
            console.error('Fehler beim Laden der Spots:', err);
        }
    } else {
        await loadSpotsInView(onSelect);
    }
    state.map.on('moveend', () => loadSpotsInView(onSelect));
}

/** Legt einen Marker für einen Spot auf der Karte ab */
function addMarker(spot, onSelect) {
    if (markerStore[spot.id]) return; // schon geladen
    const marker = L.marker([spot.latitude, spot.longitude], {
        icon: buildIcon(spot.spot_type, spot.difficulty),
        title: spot.name,
    }).addTo(state.map);

    marker.on('click', () => onSelect(spot));
    markerStore[spot.id] = { marker, spot };
}

// ---------------------------------------------------------------
// Spots nach Kategorie filtern (gesteuert ueber die Legende)
// ---------------------------------------------------------------
export function filterMarkersByType() {
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
            if (!state.map.hasLayer(marker)) state.map.addLayer(marker);
        } else {
            if (state.map.hasLayer(marker)) state.map.removeLayer(marker);
        }
    }
}
