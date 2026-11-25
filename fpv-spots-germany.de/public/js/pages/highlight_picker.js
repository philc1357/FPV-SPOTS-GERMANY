// =============================================================
// Highlight-Auswahl (Modal #highlightModal)
// Markup: public/includes/highlight_picker.php
//
// Antippen einer Kachel hängt das Foto als nächsten Platz an
// (max. 3), erneutes Antippen entfernt es – die anderen rücken
// nach. Die <select>-Felder bleiben die eigentliche Formular-
// eingabe und werden nur synchron gehalten (ohne JS sichtbar).
// =============================================================
(function () {
    var form = document.getElementById('highlightForm');
    if (!form) return;

    var max    = parseInt(form.dataset.max, 10) || 3;
    var status = document.getElementById('highlightStatus');
    var tiles  = Array.prototype.slice.call(form.querySelectorAll('.fsg-hl-tile'));
    var selects = {};
    Array.prototype.forEach.call(form.querySelectorAll('.fsg-hl-select'), function (sel) {
        selects[sel.dataset.hlId] = sel;
    });

    // ── Ausgangszustand aus den vorbelegten Selects lesen ────
    function readInitial() {
        var pairs = [];
        Object.keys(selects).forEach(function (id) {
            var p = parseInt(selects[id].value, 10) || 0;
            if (p > 0) pairs.push([p, id]);
        });
        pairs.sort(function (a, b) { return a[0] - b[0]; });
        return pairs.map(function (pair) { return pair[1]; }).slice(0, max);
    }

    var initial = readInitial();
    var order   = initial.slice(); // Bild-IDs, Index 0 = Platz 1
    var hint    = '';

    // ── Kacheln, Selects und Statuszeile neu zeichnen ────────
    function render() {
        tiles.forEach(function (tile) {
            var id    = tile.dataset.hlId;
            var pos   = order.indexOf(id) + 1;
            var badge = tile.querySelector('.fsg-hl-badge');
            if (pos > 0) {
                tile.dataset.pos = String(pos);
                tile.setAttribute('aria-pressed', 'true');
                badge.textContent = String(pos);
            } else {
                delete tile.dataset.pos;
                tile.setAttribute('aria-pressed', 'false');
                badge.textContent = '';
            }
            if (selects[id]) selects[id].value = String(pos);
        });

        var text = order.length === 0
            ? 'Keine Auswahl – es werden automatisch die neuesten Fotos gezeigt.'
            : order.length + ' von ' + max + ' gewählt' +
              (order.length < max ? ' – freie Plätze füllen die neuesten Fotos.' : '.');
        status.textContent = hint ? hint : text;
        status.classList.toggle('text-warning', hint !== '');
        hint = '';
    }

    // ── Antippen: anhängen oder entfernen ────────────────────
    tiles.forEach(function (tile) {
        tile.addEventListener('click', function () {
            var id  = tile.dataset.hlId;
            var idx = order.indexOf(id);
            if (idx !== -1) {
                order.splice(idx, 1);
            } else if (order.length < max) {
                order.push(id);
            } else {
                hint = 'Es sind schon ' + max + ' Fotos gewählt – tippe zuerst eins davon an, um es zu entfernen.';
            }
            render();
        });
    });

    // ── Abbrechen/Schließen verwirft ungespeicherte Änderungen ──
    var modal = document.getElementById('highlightModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
            order = initial.slice();
            render();
        });
    }

    form.classList.add('is-enhanced'); // blendet die Selects per CSS aus
    render();
})();
