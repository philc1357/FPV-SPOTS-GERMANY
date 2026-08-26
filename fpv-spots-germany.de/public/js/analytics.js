// =============================================================
// FPV Spots Germany – Ereignis-Erfassung (Umami)
//
// Diese Datei ergaenzt umami.js um alles, was ein blosses
// data-umami-event-Attribut nicht abbilden kann:
//
//   1. fpvTrack()    – sicherer Wrapper, den map.js, pwa.js und
//                      die Detailseite aufrufen duerfen, ohne zu
//                      wissen, ob ueberhaupt gemessen wird.
//   2. PRG-Marker    – serverseitige Erfolge (Spot angelegt,
//                      registriert, kommentiert) enden mit einem
//                      Redirect; im Moment des Erfolgs laeuft
//                      kein JavaScript. Der Redirect traegt
//                      deshalb ?ok=<marker>, der hier einmalig in
//                      ein Ereignis uebersetzt und danach aus der
//                      Adresszeile entfernt wird.
//   3. Verbindungsabbrueche der PWA.
//
// Fuer einfache Klicks braucht es nichts davon: der Tracker
// wertet data-umami-event="name" (plus data-umami-event-*) selbst
// aus. Solche Faelle stehen direkt im Markup.
//
// Grundregel aus der Datenschutzerklaerung: keine personen-
// bezogenen Ereignisdaten. Keine Benutzernamen, keine User-IDs,
// kein umami.identify(), keine Spot-Koordinaten. Oeffentliche
// Spot-IDs und Kategorien sind in Ordnung.
// =============================================================
(function () {
    'use strict';

    // ── Sicherer Wrapper ────────────────────────────────────
    // window.umami fehlt bei Opt-out, DNT, Admin-Session oder
    // wenn ein Blocker die Datei doch erwischt hat. Aufrufer
    // sollen das nicht pruefen muessen – hier passiert dann
    // einfach nichts. Ein Fehler im Tracking darf niemals eine
    // Seite beschaedigen, deshalb zusaetzlich try/catch.
    function track(name, data) {
        try {
            if (!window.umami || typeof window.umami.track !== 'function') return;
            if (typeof name !== 'string' || name === '') return;
            if (data && typeof data === 'object') {
                window.umami.track(name, data);
            } else {
                window.umami.track(name);
            }
        } catch (e) { /* Tracking ist nie wichtig genug fuer einen Fehler */ }
    }

    window.fpvTrack = track;

    // ── PRG-Marker aufloesen ────────────────────────────────
    // Whitelist: nur diese Marker erzeugen ein Ereignis. Damit
    // kann niemand ueber einen praeparierten Link beliebige
    // Ereignisnamen in die Statistik schreiben.
    var PRG_EVENTS = {
        spot:          'spot:erstellt',
        bewertung:     'spot:bewertet',
        kommentar:     'spot:kommentiert',
        bild:          'spot:bild',
        registrierung: 'konto:registrierung_ok',
        login:         'konto:login',
        tos:           'konto:tos_ok',
        vorschlag:     'vorschlag:abgeschickt',
        kontakt:       'kontakt:abgeschickt'
    };

    function resolvePrgMarker() {
        var params;
        try {
            params = new URLSearchParams(window.location.search);
        } catch (e) {
            return;
        }

        var marker = params.get('ok');
        if (!marker || !Object.prototype.hasOwnProperty.call(PRG_EVENTS, marker)) return;

        track(PRG_EVENTS[marker]);

        // Marker aus der Adresszeile nehmen, damit ein Reload oder
        // ein weitergegebener Link das Ereignis nicht wiederholt.
        // In den erfassten URLs taucht er ohnehin nicht auf – das
        // Script laeuft mit data-exclude-search.
        try {
            params.delete('ok');
            var query = params.toString();
            window.history.replaceState(
                window.history.state,
                '',
                window.location.pathname + (query ? '?' + query : '') + window.location.hash
            );
        } catch (e) { /* replaceState scheitert nur in Sonderfaellen */ }
    }

    resolvePrgMarker();

    // ── Verbindungsabbruch der PWA ──────────────────────────
    // Gemeldet wird erst beim Wiederverbinden, nicht beim
    // Abbruch: offline kommt kein Request mehr durch. Der Wert
    // ist eine grobe Dauerklasse, keine Zeitmessung am Nutzer.
    var offlineSince = 0;

    window.addEventListener('offline', function () {
        offlineSince = Date.now();
    });

    window.addEventListener('online', function () {
        if (!offlineSince) return;
        var sekunden = Math.round((Date.now() - offlineSince) / 1000);
        offlineSince = 0;
        track('pwa:offline', {
            dauer: sekunden < 30 ? 'kurz' : (sekunden < 300 ? 'mittel' : 'lang')
        });
    });
})();
