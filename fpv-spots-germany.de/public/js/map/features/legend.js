// =============================================================
// Legende: Filter, Auf-/Zuklappen, Speichern der Auswahl (ES-Modul)
// =============================================================
import { state } from '../state.js';
import { filterMarkersByType } from '../layers/spots.js';

// Wird bei jedem einzelnen Checkbox-Klick ausgeloest. Mehrere schnell
// hintereinander gesendete Requests koennen in unbestimmter Reihenfolge
// beim Server ankommen; ein AbortController hilft hier NICHT, weil Chrome
// eine bereits als keepalive markierte Anfrage trotz abort() weiter an den
// Server sendet und deren Set-Cookie trotzdem uebernimmt. Deshalb wird
// stattdessen debounct: waehrend eines Bursts wird nur EIN Request nach
// kurzer Ruhezeit verschickt. Damit ein Klick direkt vor dem Verlassen der
// Seite nicht verloren geht, wird ein noch ausstehender Request beim
// Verstecken der Seite sofort (mit keepalive) nachgeholt.
let _legendSaveTimer = null;
let _legendSavePending = false;

function saveLegendToSession() {
    _legendSavePending = true;
    clearTimeout(_legendSaveTimer);
    _legendSaveTimer = setTimeout(sendLegendToSession, 300);
}

function sendLegendToSession() {
    clearTimeout(_legendSaveTimer);
    _legendSavePending = false;
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

// ---------------------------------------------------------------
// Legende auf-/zuklappen
//
// closeLegend() ist die eine Stelle, die den Zustand zurueckdreht –
// sie wird aus mehreren Richtungen gerufen (Header-Dropdown,
// Platzierungs-Modus, Karten-Klick).
// ---------------------------------------------------------------
export function closeLegend() {
    const content = document.getElementById('legendContent');
    if (content.hidden) return;
    content.hidden = true;
    document.getElementById('legendToggle').setAttribute('aria-expanded', 'false');
    document.getElementById('legendArrow').innerHTML = '<i class="bi bi-chevron-down"></i>';
}

/** Haengt die Legenden-Handler ein (einmal beim Start) */
export function initLegend() {
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && _legendSavePending) {
            sendLegendToSession();
        }
    });

    document.querySelectorAll('.legend-cb').forEach(cb => {
        cb.addEventListener('change', () => {
            filterMarkersByType();
            saveLegendToSession();
        });
    });

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
    state.map.on('click', closeLegend);
}
