// =============================================================
// Hinweis-Modal: veralteter Service-Worker-Cache
// Ausgelagert aus public/includes/stale_cache_banner.php (CSP: keine
// Inline-Skripte). Bewusst eine eigene, neue Datei statt banners.js:
// Alte Service Worker haben sie nie gecacht und laden sie daher
// frisch vom Server. Keine Abhängigkeit von Bootstrap o. ä.
// =============================================================
'use strict';
(function () {
    // ---------------------------------------------------------------
    // Cache-Generationen, die eine veraltete map.js enthalten koennen.
    // Ab v3 stimmt der API-Pfad; v4 raeumt diese Caches beim Aktivieren
    // ohnehin auf – existieren sie noch, ist der alte Worker aktiv.
    // ---------------------------------------------------------------
    var DEFEKTE_CACHES = /^fpv-(static|cdn|tiles|api)-v[12]$/;
    var ALTER_API_PFAD = '/public/php/api/';
    var SPEICHER_KEY   = 'scb_dismissed';

    var box = document.getElementById('stale-cache-banner');
    if (!box || !('caches' in window) || !('serviceWorker' in navigator)) return;

    // sessionStorage kann im Privatmodus werfen – nie die Seite mitreissen
    function weggeklickt() {
        try { return sessionStorage.getItem(SPEICHER_KEY) === '1'; }
        catch (e) { return false; }
    }
    function merkeWeggeklickt() {
        try { sessionStorage.setItem(SPEICHER_KEY, '1'); } catch (e) {}
    }

    // ---------------------------------------------------------------
    // Betroffenheit pruefen – zweistufig, damit der Normalfall billig bleibt
    //   1. Vorfilter: liegt ueberhaupt eine alte Cache-Generation vor?
    //      Trifft auf die grosse Mehrheit nicht zu -> keine weitere Arbeit.
    //   2. Verifikation: liefert der Service Worker tatsaechlich eine
    //      map.js mit dem alten API-Pfad aus? Erst das ist der Beweis.
    //      So loest eine blosse Cache-Leiche ohne aktiven Worker
    //      (Spots werden normal angezeigt) keinen Fehlalarm aus.
    // ---------------------------------------------------------------
    function istBetroffen() {
        return caches.keys().then(function (keys) {
            if (!keys.some(function (k) { return DEFEKTE_CACHES.test(k); })) return false;
            return fetch('/public/js/map.js')
                .then(function (r) { return r.ok ? r.text() : ''; })
                .then(function (t) { return t.indexOf(ALTER_API_PFAD) !== -1; })
                .catch(function () { return false; });
        }).catch(function () { return false; });
    }

    // ---------------------------------------------------------------
    // Reparatur: Service Worker abmelden und alle App-Caches verwerfen.
    // Cookies und Session bleiben unangetastet – der Nutzer bleibt
    // eingeloggt. Danach laedt die Seite alles frisch vom Server.
    // ---------------------------------------------------------------
    function reparieren() {
        return navigator.serviceWorker.getRegistrations()
            .then(function (regs) {
                return Promise.all(regs.map(function (r) { return r.unregister(); }));
            })
            .then(function () { return caches.keys(); })
            .then(function (keys) {
                return Promise.all(keys.map(function (k) {
                    return k.indexOf('fpv-') === 0 ? caches.delete(k) : null;
                }));
            });
    }

    istBetroffen().then(function (betroffen) {
        if (!betroffen || weggeklickt()) return;

        box.hidden = false;

        var reparaturBtn = document.getElementById('scb-repair');
        reparaturBtn.addEventListener('click', function () {
            reparaturBtn.disabled = true;
            reparaturBtn.textContent = 'Wird repariert …';
            reparieren()
                .then(function () { location.reload(); })
                // Auch im Fehlerfall neu laden: schlimmstenfalls erscheint
                // der Hinweis erneut, samt Anleitung zur Neuinstallation.
                .catch(function () { location.reload(); });
        });

        document.getElementById('scb-dismiss').addEventListener('click', function () {
            merkeWeggeklickt();
            box.hidden = true;
        });
    });
}());
