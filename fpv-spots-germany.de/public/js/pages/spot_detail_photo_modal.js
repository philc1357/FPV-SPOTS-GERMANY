// =============================================================
// Spot-Detail – Foto-Lightbox (Vollbild-Galerie)
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird synchron nach dem Modal-Markup geladen, d. h. das HTML ist
// beim Ausführen bereits vorhanden.
//
// Galerie-Quelle sind alle Foto-Buttons im Abschnitt "Fotos"
// (.sd-photo-btn). Die Mosaik-Kacheln oben öffnen dieselbe Galerie
// am passenden Bild (Abgleich über data-img-src).
// =============================================================
(function () {
    'use strict';

    var modal = document.getElementById('photoModal');
    if (!modal) return;

    var imgEl     = document.getElementById('photoModalImg');
    var stage     = document.getElementById('photoModalStage');
    var counterEl = document.getElementById('photoModalCounter');
    var captionEl = document.getElementById('photoModalCaption');
    var prevBtn   = document.getElementById('photoModalPrev');
    var nextBtn   = document.getElementById('photoModalNext');
    var fsBtn     = document.getElementById('photoModalFs');

    var items = [];   // [{src, alt, caption}]
    var index = 0;

    // ---------------------------------------------------------
    // Galerie einlesen: nur sichtbare Fotos (defekte Bilder blendet
    // ui_actions.js per data-hide-on-error mit display:none aus)
    // ---------------------------------------------------------
    function readItems() {
        var list = [];
        document.querySelectorAll('.sd-photo-btn').forEach(function (btn) {
            var fig = btn.closest('.sd-photo');
            if (fig && window.getComputedStyle(fig).display === 'none') return;
            var src = btn.getAttribute('data-img-src') || '';
            if (!src) return;
            list.push({
                src: src,
                alt: btn.getAttribute('data-img-alt') || '',
                caption: btn.getAttribute('data-img-caption') || ''
            });
        });
        return list;
    }

    function itemFromTrigger(trigger) {
        return {
            src: trigger.getAttribute('data-img-src') || '',
            alt: trigger.getAttribute('data-img-alt') || '',
            caption: trigger.getAttribute('data-img-caption') || ''
        };
    }

    // ---------------------------------------------------------
    // Anzeige: Bild, Zähler, Bildunterschrift, Nachbarn vorladen
    // ---------------------------------------------------------
    function preload(i) {
        if (items.length < 2) return;
        var item = items[(i + items.length) % items.length];
        var pre = new Image();
        pre.decoding = 'async';
        pre.src = item.src;
    }

    function show(i) {
        if (!items.length) return;
        index = (i % items.length + items.length) % items.length;   // endlos
        var item = items[index];
        imgEl.src = item.src;
        imgEl.alt = item.alt;
        counterEl.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
        captionEl.textContent = item.caption;
        preload(index + 1);
        preload(index - 1);
    }

    function step(delta) {
        if (items.length > 1) show(index + delta);
    }

    // ---------------------------------------------------------
    // Öffnen/Schließen des Modals
    // ---------------------------------------------------------
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;

        items = readItems();
        var src = trigger.getAttribute('data-img-src') || '';
        var start = -1;
        for (var i = 0; i < items.length; i++) {
            if (items[i].src === src) { start = i; break; }
        }
        // Fallback: Bild nicht in der Galerie → nur dieses eine zeigen
        if (start === -1) {
            items = [itemFromTrigger(trigger)];
            start = 0;
        }

        var multi = items.length > 1;
        prevBtn.hidden = !multi;
        nextBtn.hidden = !multi;
        show(start);
    });

    modal.addEventListener('shown.bs.modal', function () {
        document.addEventListener('keydown', onKey);
    });

    modal.addEventListener('hidden.bs.modal', function () {
        document.removeEventListener('keydown', onKey);
        if (document.fullscreenElement === modal && document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
        }
        imgEl.src = '';
        imgEl.alt = '';
        counterEl.textContent = '';
        captionEl.textContent = '';
    });

    // ---------------------------------------------------------
    // Steuerung: Buttons, Pfeiltasten, Wischen
    // ---------------------------------------------------------
    prevBtn.addEventListener('click', function () { step(-1); });
    nextBtn.addEventListener('click', function () { step(1); });

    function onKey(e) {
        if (e.altKey || e.ctrlKey || e.metaKey) return;
        if (e.key === 'ArrowLeft')  { e.preventDefault(); step(-1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
    }

    var touchX = null, touchY = null;
    stage.addEventListener('touchstart', function (e) {
        if (e.touches.length !== 1) { touchX = null; return; }
        touchX = e.touches[0].clientX;
        touchY = e.touches[0].clientY;
    }, { passive: true });

    stage.addEventListener('touchend', function (e) {
        if (touchX === null) return;
        var dx = e.changedTouches[0].clientX - touchX;
        var dy = e.changedTouches[0].clientY - touchY;
        touchX = null;
        // Nur deutlich horizontale Wischbewegungen werten
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            step(dx < 0 ? 1 : -1);
        }
    }, { passive: true });

    // ---------------------------------------------------------
    // Echtes Browser-Vollbild (Fullscreen-API). Nicht überall
    // verfügbar (z. B. iPhone-Safari) – dann bleibt der Button
    // ausgeblendet und die Lightbox füllt "nur" das Fenster.
    // ---------------------------------------------------------
    if (document.fullscreenEnabled && modal.requestFullscreen) {
        fsBtn.hidden = false;
        var fsIcon = fsBtn.querySelector('i');

        fsBtn.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(function () {});
            } else {
                modal.requestFullscreen().catch(function () {});
            }
        });

        document.addEventListener('fullscreenchange', function () {
            var active = document.fullscreenElement === modal;
            fsIcon.className = 'bi ' + (active ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen');
            var label = active ? 'Vollbild beenden' : 'Vollbild';
            fsBtn.setAttribute('aria-label', label);
            fsBtn.title = label;
        });
    }
})();
