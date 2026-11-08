// =============================================================
// Karten-Zustand und Konfiguration (ES-Modul)
//
// Zwei Teile:
//   config  – unveraenderlich: Meta-Tag-Werte und Konstanten
//   state   – veraenderlich: gemeinsamer Laufzeit-Zustand
//
// WICHTIG: Importierte Bindings sind schreibgeschuetzt, ein
// `import { x }` kann x nicht neu zuweisen. Veraenderlicher Zustand
// liegt deshalb als Eigenschaft in EINEM Objekt (state.placingMode = true).
// Das ersetzt die frueheren losen Globals (_placingMode, a3Circle, …).
// =============================================================

/** App-Konfiguration aus Meta-Tags (kein Inline-JS noetig, siehe CSP) */
const meta = (name) => document.querySelector(`meta[name="${name}"]`);

export const config = Object.freeze({
    isLoggedIn:  meta('app-logged-in').content === 'true',
    currentUser: parseInt(meta('app-user-id').content, 10),
    isAdmin:     meta('app-is-admin').content === 'true',
    csrfToken:   meta('app-csrf-token').content,

    mapStateKey:      'fpv_map_state',
    mapDefaultCenter: [51.1657, 10.4515], // Mittelpunkt Deutschland
    mapDefaultZoom:   6,
    mapMaxZoom:       19,
});

/** Gemeinsamer, veraenderlicher Laufzeit-Zustand der Karte */
export const state = {
    // Betriebsklasse des Piloten: u250 / c1c2 / a3. Serverseitig aus dem
    // Cookie pilot_class gerendert (private/php/core/geozones.php). Fehlt das
    // Meta-Tag, gilt bewusst a3 – die strengste Annahme.
    pilotClass: (meta('app-pilot-class') || {}).content || 'a3',

    map:           null,  // Leaflet-Karte (wird beim Erzeugen gesetzt)
    activeSpot:    null,  // im Offcanvas geoeffneter Spot
    a3Circle:      null,  // aktueller 150-m-Kreis
    newSpotMarker: null,  // Vorschau-Marker beim Spot-Anlegen
    placingMode:   false, // Platzierungs-Modus aktiv
    pendingAction: null,  // Aktion nach Bestaetigung im Verwerfen-Dialog
    forceClose:    false, // Offcanvas-Close ohne Rueckfrage erlauben
};
