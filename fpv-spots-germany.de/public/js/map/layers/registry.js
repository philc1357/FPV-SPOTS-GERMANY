// =============================================================
// Overlay-Registry (ES-Modul)
//
// Jeder Datenlayer (Geozonen, spaeter weitere) meldet sich hier an;
// die Registry baut daraus EINE Layer-Kontrolle und merkt sich die
// aktiven Overlays pro Sitzung (sessionStorage). Ein neuer Layer ist
// damit eine neue Datei mit registerOverlay() – ohne Eingriff in
// bestehenden Code.
//
// Reihenfolge: erst alle registerOverlay(), dann einmal mountOverlays().
// =============================================================

const STATE_KEY = 'fpv_map_overlays'; // sessionStorage: Namen der aktiven Overlays

/** label -> { layer, tooltip } (Einfuegereihenfolge = Anzeigereihenfolge) */
const overlays = {};

/**
 * @param {{label: string, layer: L.Layer, tooltip?: string}} def
 *   label   – Anzeigename UND Schluessel im sessionStorage
 *   tooltip – optionaler title-Text am Eintrag der Layer-Kontrolle
 */
export function registerOverlay({ label, layer, tooltip = '' }) {
    if (overlays[label]) throw new Error('Overlay bereits registriert: ' + label);
    overlays[label] = { layer, tooltip };
}

/** Namen der aktuell auf der Karte sichtbaren Overlays */
export function activeOverlayLabels(map) {
    return Object.keys(overlays).filter((label) => map.hasLayer(overlays[label].layer));
}

/**
 * Stellt zuletzt aktive Overlays wieder her, erzeugt die Layer-Kontrolle
 * und speichert Aenderungen. Gibt Kontrolle und deren Container zurueck.
 */
export function mountOverlays(map) {
    const layers = {};
    Object.keys(overlays).forEach((label) => { layers[label] = overlays[label].layer; });

    try {
        const saved = JSON.parse(sessionStorage.getItem(STATE_KEY) || '[]');
        if (Array.isArray(saved)) {
            saved.forEach((label) => {
                if (layers[label]) layers[label].addTo(map);
            });
        }
    } catch (_) { /* sessionStorage nicht verfügbar – ignorieren */ }

    const control = L.control.layers({}, layers, { position: 'bottomleft' }).addTo(map);
    const box = control.getContainer();

    // Eintraege erscheinen in Registrierungsreihenfolge
    const labels = box.querySelectorAll('.leaflet-control-layers-overlays label');
    Object.keys(overlays).forEach((label, i) => {
        if (labels[i] && overlays[label].tooltip) labels[i].title = overlays[label].tooltip;
    });

    map.on('overlayadd overlayremove', () => {
        try {
            sessionStorage.setItem(STATE_KEY, JSON.stringify(activeOverlayLabels(map)));
        } catch (_) {}
    });

    return { control, box };
}
