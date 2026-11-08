// =============================================================
// Startseite (Karte)
// Ausgelagert aus index.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var checks = document.querySelectorAll('.create-copter-check');
    var btn = document.getElementById('createCopterBtn');
    if (!btn) return;
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var selected = Array.from(checks).filter(c => c.checked).map(c => c.value);
            btn.textContent = selected.length ? selected.join(', ') : 'Alle Größen wählen…';
        });
    });
})();

// =============================================================
// Dynamisches Hinzufügen/Entfernen von YouTube-Video-Zeilen
// =============================================================
(function () {
    var MAX_VIDEOS = 10;
    var container = document.getElementById('videoLinksContainer');
    var addBtn    = document.getElementById('addVideoLinkBtn');
    if (!container || !addBtn) return;

    function rowCount() {
        return container.querySelectorAll('.video-link-row').length;
    }
    function updateAddBtn() {
        addBtn.disabled = rowCount() >= MAX_VIDEOS;
    }
    addBtn.addEventListener('click', function () {
        if (rowCount() >= MAX_VIDEOS) return;
        var template = container.querySelector('.video-link-row');
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
        container.appendChild(clone);
        updateAddBtn();
    });
    container.addEventListener('click', function (e) {
        var btn = e.target.closest('.video-remove');
        if (!btn) return;
        var row = btn.closest('.video-link-row');
        if (!row) return;
        if (rowCount() > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
        }
        updateAddBtn();
    });
    updateAddBtn();
})();
