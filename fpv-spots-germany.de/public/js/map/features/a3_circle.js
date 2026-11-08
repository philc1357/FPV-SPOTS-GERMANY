// =============================================================
// A3-Abstandskreis (ES-Modul)
// =============================================================
import { state } from '../state.js';

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

export function clearA3Circle() {
    if (state.a3Circle) {
        state.map.removeLayer(state.a3Circle);
        state.a3Circle = null;
    }
}

export function showA3Circle(lat, lon) {
    clearA3Circle();
    if (state.pilotClass !== 'a3') return;
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

    state.a3Circle = L.circle([lat, lon], {
        radius:      A3_RADIUS_M,
        color:       '#fcc03c',
        weight:      2,
        dashArray:   '6 4',
        fillColor:   '#fcc03c',
        fillOpacity: 0.08,
        interactive: false,
    }).addTo(state.map);
}
