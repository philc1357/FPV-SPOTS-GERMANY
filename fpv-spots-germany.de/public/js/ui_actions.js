// =============================================================
// Deklarative UI-Aktionen (Ersatz für Inline-Event-Handler)
//
// Die Content-Security-Policy erlaubt keine Inline-Skripte mehr
// (kein 'unsafe-inline' bei script-src). onclick=, onsubmit= und
// onerror= im HTML würden vom Browser blockiert. Stattdessen werden
// hier zentrale, delegierte Listener registriert, die über
// data-Attribute gesteuert werden:
//
//   data-confirm="Text"        Formular/Button/Link nur nach Bestätigung
//   data-fallback-src="url"    Bild lädt nicht → einmalig auf diese URL
//                              ausweichen (z. B. Original statt Thumbnail)
//   data-hide-on-error="sel"   Bild ausblenden, wenn es nicht lädt:
//                              "self" → nur das Bild (visibility),
//                              sonst CSS-Selektor des Containers (display)
//   data-action="name"         ruft eine per UiActions.register()
//                              angemeldete Funktion (el, event) auf
//
// Wird über public/includes/foot_assets.php auf jeder Seite geladen.
// =============================================================
(function () {
    'use strict';

    // ---------------------------------------------------------
    // Aktions-Register: Seiten-Skripte melden hier ihre Handler an
    // ---------------------------------------------------------
    const actions = Object.create(null);

    window.UiActions = {
        register(name, fn) {
            if (typeof name === 'string' && typeof fn === 'function') {
                actions[name] = fn;
            }
        },
    };

    // ---------------------------------------------------------
    // Bestätigungsdialoge
    // Capture-Phase am document: läuft VOR allen Listenern am
    // Element selbst; bei Abbruch werden diese gar nicht erst
    // ausgeführt.
    // ---------------------------------------------------------
    function confirmOrCancel(el, event) {
        if (!el || !el.dataset.confirm) return true;
        if (window.confirm(el.dataset.confirm)) return true;
        event.preventDefault();
        event.stopImmediatePropagation();
        return false;
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form instanceof HTMLFormElement) confirmOrCancel(form, e);
    }, true);

    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) return;

        // Buttons/Links mit eigener Bestätigung (nicht das Formular –
        // das prüft der submit-Listener)
        const confirmEl = e.target.closest('a[data-confirm], button[data-confirm]');
        if (confirmEl && !confirmOrCancel(confirmEl, e)) return;

        // Registrierte Aktionen
        const actionEl = e.target.closest('[data-action]');
        if (!actionEl) return;
        const fn = actions[actionEl.dataset.action];
        if (fn) fn(actionEl, e);
    }, true);

    // ---------------------------------------------------------
    // Defekte Bilder ausblenden
    // error-Events blubbern nicht, daher Capture-Phase. Bilder, die
    // schon vor dem Laden dieses Skripts gescheitert sind, werden
    // beim Start einmal nachgeprüft.
    // ---------------------------------------------------------
    function hideBrokenImage(img) {
        // Erst auf die Ausweich-URL wechseln, erst danach ausblenden
        const fallback = img.dataset.fallbackSrc;
        if (fallback && !img.dataset.fallbackUsed) {
            img.dataset.fallbackUsed = '1';
            img.src = fallback;
            return;
        }

        const target = img.dataset.hideOnError;
        if (!target) return;
        if (target === 'self') {
            img.style.visibility = 'hidden';
            return;
        }
        const container = img.closest(target);
        if (container) container.style.display = 'none';
    }

    document.addEventListener('error', function (e) {
        const el = e.target;
        if (el instanceof HTMLImageElement) hideBrokenImage(el);
    }, true);

    function checkAlreadyBroken() {
        document.querySelectorAll('img[data-hide-on-error], img[data-fallback-src]').forEach(function (img) {
            // currentSrc ist leer, solange ein lazy-Bild noch nicht angefragt wurde
            if (img.complete && img.currentSrc && img.naturalWidth === 0) {
                hideBrokenImage(img);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAlreadyBroken);
    } else {
        checkAlreadyBroken();
    }
})();
