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
//   data-password-toggle       Passwortfeld bekommt einen Augen-Button
//                              zum Ein-/Ausblenden des Passworts
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

    // ---------------------------------------------------------
    // Passwort anzeigen/verbergen
    // Felder mit data-password-toggle werden in eine Bootstrap-
    // input-group gepackt und erhalten einen Augen-Button. Abstands-
    // klassen (mb-*) wandern vom Feld auf die Gruppe, damit das
    // Layout gleich bleibt. Beim Absenden wird das Feld wieder auf
    // type="password" gesetzt, damit der Browser es korrekt als
    // Passwort behandelt (Passwort-Manager, Formular-Cache).
    // ---------------------------------------------------------
    function setPasswordVisible(input, btn, visible) {
        input.type = visible ? 'text' : 'password';
        btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
        btn.setAttribute('aria-label', visible ? 'Passwort verbergen' : 'Passwort anzeigen');
        btn.title = btn.getAttribute('aria-label');
        const icon = btn.querySelector('i');
        if (icon) icon.className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    function enhancePasswordInput(input) {
        if (input.dataset.passwordToggleReady) return;
        input.dataset.passwordToggleReady = '1';

        const group = document.createElement('div');
        group.className = 'input-group';
        Array.from(input.classList).forEach(function (cls) {
            if (/^m[tbsexy]?-\d$/.test(cls)) {
                input.classList.remove(cls);
                group.classList.add(cls);
            }
        });

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary';
        if (input.id) btn.setAttribute('aria-controls', input.id);
        const icon = document.createElement('i');
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);
        setPasswordVisible(input, btn, false);

        btn.addEventListener('click', function () {
            setPasswordVisible(input, btn, input.type === 'password');
            input.focus();
        });

        input.parentNode.insertBefore(group, input);
        group.appendChild(input);
        group.appendChild(btn);

        if (input.form) {
            input.form.addEventListener('submit', function () {
                setPasswordVisible(input, btn, false);
            });
        }
    }

    function initPasswordToggles() {
        document.querySelectorAll('input[data-password-toggle]').forEach(enhancePasswordInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordToggles);
    } else {
        initPasswordToggles();
    }
})();
