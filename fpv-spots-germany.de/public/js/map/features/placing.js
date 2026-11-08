// =============================================================
// Spot anlegen: Platzierungs-Modus, Vorschau-Marker, Verwerfen-Dialog
// (ES-Modul)
// =============================================================
import { config, state } from '../state.js';
import { hideAlert, getOrCreateOffcanvas } from '../util.js';
import { showA3Circle, clearA3Circle } from './a3_circle.js';
import { closeLegend } from './legend.js';

// ---------------------------------------------------------------
// Spot erstellen: Platzierungs-Modus
//
// Ein Karten-Klick allein legt bewusst KEINEN Spot mehr an – das
// oeffnete das Offcanvas auch beim Zuklappen von Legende oder
// Layer-Box und beim Danebentippen neben einen Marker. Stattdessen
// schaltet der Button "Spot erstellen" die Karte in einen Modus,
// in dem genau ein Klick die Koordinaten setzt; danach endet er.
// ---------------------------------------------------------------

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

// ---------------------------------------------------------------
// Vorschau-Marker fuer den noch nicht gespeicherten Spot
// ---------------------------------------------------------------

function clearNewSpotMarker() {
    if (state.newSpotMarker) {
        state.map.removeLayer(state.newSpotMarker);
        state.newSpotMarker = null;
    }
}

function setNewSpotMarker(latlng) {
    clearNewSpotMarker();
    state.newSpotMarker = L.marker(latlng, {
        icon: L.divIcon({
            className:  '',
            html:       '<div class="new-spot-marker"></div>',
            iconSize:   [22, 22],
            iconAnchor: [11, 11],
        }),
        zIndexOffset: 1000,
        interactive:  false,
    }).addTo(state.map);

    // Zeigt beim Anlegen denselben 150-m-Radius wie die Spot-Detailansicht
    showA3Circle(latlng.lat, latlng.lng);
}

// ---------------------------------------------------------------
// Modus betreten / verlassen
// ---------------------------------------------------------------
export function enterPlacingMode() {
    state.placingMode = true;
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

export function exitPlacingMode() {
    state.placingMode = false;
    document.body.classList.remove('spot-placing');
    document.getElementById('placingHint').hidden = true;

    const btn = document.getElementById('createSpotBtn');
    btn.classList.remove('active');
    btn.setAttribute('aria-pressed', 'false');
    document.getElementById('createSpotBtnLabel').textContent = 'Spot erstellen';
    document.getElementById('createSpotBtnIcon').className = 'bi bi-plus-lg';
}

/** Haengt alle Handler des Anlegen-Ablaufs ein (einmal beim Start) */
export function initPlacing() {
    // Offcanvas-Close abfangen: Bootstrap feuert dieses Event BEVOR es das Offcanvas schließt,
    // also noch bevor Leaflet den Map-Click verarbeitet. Nur so können wir rechtzeitig blockieren.
    document.getElementById('createSpotOffcanvas').addEventListener('hide.bs.offcanvas', (e) => {
        if (state.forceClose) return;
        if (createFormHasData()) {
            e.preventDefault();
            new bootstrap.Modal(document.getElementById('discardSpotModal')).show();
        }
    });

    // ---------------------------------------------------------------
    // Karten-Klick: nur im Platzierungs-Modus wirksam
    // ---------------------------------------------------------------
    state.map.on('click', (e) => {
        if (!state.placingMode) return;

        exitPlacingMode();
        setNewSpotMarker(e.latlng);
        applyNewSpotLocation(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
    });

    // ---------------------------------------------------------------
    // Button "Spot erstellen"
    // ---------------------------------------------------------------
    document.getElementById('createSpotBtn').addEventListener('click', () => {
        if (!config.isLoggedIn) {
            new bootstrap.Modal(document.getElementById('loginModal')).show();
            return;
        }

        if (state.placingMode) {
            exitPlacingMode();
            return;
        }

        // Offenes Formular mit Eingaben: erst nachfragen, dann neu platzieren
        const offcanvasEl = document.getElementById('createSpotOffcanvas');
        if (offcanvasEl.classList.contains('show')) {
            if (createFormHasData()) {
                state.pendingAction = enterPlacingMode;
                new bootstrap.Modal(document.getElementById('discardSpotModal')).show();
                return;
            }
            state.forceClose = true;
            getOrCreateOffcanvas('createSpotOffcanvas').hide();
            state.forceClose = false;
        }

        enterPlacingMode();
    });

    // Abbrechen ueber das Banner oder die Escape-Taste
    document.getElementById('placingCancelBtn').addEventListener('click', exitPlacingMode);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && state.placingMode) exitPlacingMode();
    });

    // Vorschau-Marker und Kreis verschwinden mit dem Offcanvas
    document.getElementById('createSpotOffcanvas')
        .addEventListener('hidden.bs.offcanvas', () => {
            clearNewSpotMarker();
            clearA3Circle();
        });

    document.getElementById('discardConfirmBtn').addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('discardSpotModal')).hide();
        state.forceClose = true;
        getOrCreateOffcanvas('createSpotOffcanvas').hide();
        state.forceClose = false;
        if (state.pendingAction) {
            state.pendingAction();
            state.pendingAction = null;
        }
    });

    document.getElementById('discardCancelBtn').addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('discardSpotModal')).hide();
        state.pendingAction = null;
    });

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
}
