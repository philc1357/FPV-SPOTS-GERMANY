// =============================================================
// Betriebsklassen-Umschalter (ES-Modul)
// =============================================================
import { state } from '../state.js';
import { openSpotDetail } from './spot_panel.js';
import { clearA3Circle } from './a3_circle.js';

// Speichert die Wahl im Cookie pilot_class (serverseitig, HttpOnly)
// und zeichnet den 150-m-Kreis sofort neu, damit die Umschaltung ohne
// Reload sichtbar wird. Schlaegt der Request fehl, bleibt die Anzeige
// trotzdem korrekt – nur die Persistenz fehlt.
export function initPilotClassSwitch() {
    document.querySelectorAll('.pilot-class-cb').forEach((radio) => {
        radio.addEventListener('change', async () => {
            if (!radio.checked) return;
            state.pilotClass = radio.value;

            // Offener Spot: Kreis und Hinweis an die neue Klasse anpassen
            if (state.activeSpot) {
                openSpotDetail(state.activeSpot);
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
}
