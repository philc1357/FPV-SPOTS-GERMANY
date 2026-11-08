// =============================================================
// DIPUL-Geozonen als Overlay (ES-Modul)
//
// Die Kacheln kommen ausschliesslich vom eigenen Proxy /api/dipul_wms.php:
// die CSP erlaubt nur 'self', und so erreicht keine Besucher-IP die DFS.
// Die Buendelnamen muessen zu DIPUL_BUNDLES in
// private/php/core/geozones.php passen – dort liegt die einzige Quelle
// der Wahrheit fuer die Layer-Zuordnung.
//
// Unterhalb von Zoom 9 liefert der Proxy bewusst eine leere Kachel
// (Cache-Schutz), deshalb dieselbe Schwelle hier als minZoom.
// =============================================================
import { config } from '../state.js';
import { registerOverlay } from './registry.js';

const ATTRIBUTION = 'Quelle Geodaten: DFS, BKG';
const MIN_ZOOM    = 9;

const BUNDLES = {
    'Luftverkehr':          'luftverkehr',
    'Naturschutz':          'naturschutz',
    'Siedlung & Behörden':  'siedlung',
    'Infrastruktur':        'infrastruktur',
    'Temporäre Sperrungen': 'temporaer',
};

function dipulLayer(bundle) {
    return L.tileLayer.wms('/api/dipul_wms.php', {
        layers:      bundle,
        format:      'image/png',
        transparent: true,
        version:     '1.3.0',
        opacity:     0.6,
        minZoom:     MIN_ZOOM,
        maxZoom:     config.mapMaxZoom,
        attribution: ATTRIBUTION,
    });
}

/** Eigene Layer, fuer den Zoom-Hinweis und die Fehlermeldung */
const layers = [];

/** Meldet die Geozonen-Overlays bei der Registry an (vor mountOverlays()) */
export function registerDipulOverlays() {
    // Faellt die DFS aus, antwortet der Proxy mit einer transparenten Kachel und
    // Status 200 – ein echter tileerror bedeutet also ein Netz-/Serverproblem.
    // Genau einmal melden, sonst flutet jede fehlende Kachel die Konsole.
    let errorReported = false;

    Object.entries(BUNDLES).forEach(([label, bundle]) => {
        const layer = dipulLayer(bundle);
        layer.on('tileerror', () => {
            if (errorReported) return;
            errorReported = true;
            console.warn('Geozonen-Overlay derzeit nicht erreichbar – bitte auf dipul.de prüfen.');
        });
        layers.push(layer);
        // Leaflet graut Overlay-Eintraege unter deren minZoom aus – ohne
        // Erklaerung sieht das nach einem Fehler aus, daher der Tooltip.
        registerOverlay({
            label,
            layer,
            tooltip: 'Geozonen sind erst ab Zoomstufe ' + MIN_ZOOM + ' verfügbar',
        });
    });
}

/**
 * Hinweis einblenden, solange ein Overlay aktiv ist, der Zoom aber zu klein.
 * Ohne ihn wirkt die leere Karte wie "hier gibt es keine Zonen".
 * Er erscheint bereits beim Aufklappen der Layer-Kontrolle.
 *
 * Position bewusst 'bottomleft': Leaflet haengt Controls in den unteren Ecken
 * VOR die bereits vorhandenen (Control.addTo -> insertBefore), der Hinweis
 * steht dadurch immer direkt ueber der Layer-Box – also genau dort, wo der
 * Nutzer die Geozonen einschalten will. Unten rechts wurde er uebersehen.
 *
 * @param box Container der Layer-Kontrolle (aus mountOverlays())
 */
export function initDipulZoomHint(map, box) {
    const hint = L.control({ position: 'bottomleft' });
    hint.onAdd = function () {
        const div = L.DomUtil.create('div', 'dipul-zoom-hint');
        div.setAttribute('role', 'status');
        const icon = L.DomUtil.create('i', 'bi bi-exclamation-triangle-fill', div);
        icon.setAttribute('aria-hidden', 'true');
        const text = L.DomUtil.create('span', '', div);
        text.textContent = 'Geozonen erst ab Zoomstufe ' + MIN_ZOOM + ' – näher heranzoomen';
        return div;
    };
    let visible = false;

    function update() {
        const anyOverlay = layers.some((layer) => map.hasLayer(layer));
        const expanded   = box.classList.contains('leaflet-control-layers-expanded');
        const needed     = (anyOverlay || expanded) && map.getZoom() < MIN_ZOOM;
        if (needed && !visible) {
            hint.addTo(map);
            visible = true;
        } else if (!needed && visible) {
            map.removeControl(hint);
            visible = false;
        }
    }

    map.on('overlayadd overlayremove zoomend', update);
    // Auf-/Zuklappen loest kein Leaflet-Event aus – daher direkt am Container lauschen.
    ['mouseenter', 'mouseleave', 'click'].forEach((ev) => box.addEventListener(ev, update));
    update();
}
