// =============================================================
// Spot-Detail – Foto-Upload per AJAX
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var form = document.getElementById('photoUploadForm');
    if (!form || !window.fetch || !window.FormData) return; // ohne JS/fetch: normaler Formular-Post

    var input    = document.getElementById('photoInput');
    var btn      = document.getElementById('photoUploadBtn');
    var info     = document.getElementById('photoSelectionInfo');
    var list     = document.getElementById('photoSelectionList');
    var progress = document.getElementById('photoUploadProgress');
    var bar      = progress.querySelector('.progress-bar');
    var barWrap  = progress.querySelector('.progress');
    var status   = document.getElementById('photoUploadStatus');

    var maxFiles = parseInt(form.dataset.maxFiles, 10) || 0;   // 0 = unbegrenzt
    var maxSize  = parseInt(form.dataset.maxSize, 10) || 10485760;
    var STORE_KEY = 'fpvUploadSkipped_' + form.querySelector('[name="spot_id"]').value;

    var valid = [];    // File-Objekte, die hochgeladen werden
    var skipped = [];  // {name, reason} – im Browser aussortiert
    var busy = false;

    // ── Vorprüfung einer Datei ───────────────────────────────
    function checkFile(f) {
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        var typeOk = ['jpg', 'jpeg', 'png'].indexOf(ext) !== -1 &&
                     (f.type === '' || f.type === 'image/jpeg' || f.type === 'image/png');
        if (!typeOk) return 'Kein erlaubtes Format (nur JPG/PNG)';
        if (f.size > maxSize) return 'Datei zu groß (max. 10 MB)';
        return null;
    }

    function addItem(text, cls) {
        var li = document.createElement('li');
        li.className = cls;
        li.textContent = text;
        list.appendChild(li);
    }

    // ── Auswahl geändert: Liste aufbauen ─────────────────────
    input.addEventListener('change', function () {
        valid = [];
        skipped = [];
        list.textContent = '';
        info.textContent = '';
        info.className = 'small mt-2';

        var files = Array.prototype.slice.call(input.files || []);
        if (maxFiles > 0 && files.length > maxFiles) {
            info.textContent = 'Es sind maximal ' + maxFiles + ' Bilder auf einmal möglich – nur die ersten ' + maxFiles + ' werden übernommen.';
            info.className = 'small mt-2 text-warning';
            files.slice(maxFiles).forEach(function (f) {
                skipped.push({ name: f.name, reason: 'Mehr als ' + maxFiles + ' Bilder ausgewählt' });
            });
            files = files.slice(0, maxFiles);
        }

        files.forEach(function (f) {
            var err = checkFile(f);
            if (err) {
                skipped.push({ name: f.name, reason: err });
                addItem('✗ ' + f.name + ' – wird übersprungen: ' + err, 'text-danger');
            } else {
                valid.push(f);
                addItem('✓ ' + f.name, 'text-success');
            }
        });
        btn.disabled = valid.length === 0;
    });

    // ── Fortschritt anzeigen ─────────────────────────────────
    function setProgress(done, total) {
        var pct = total ? Math.round(done / total * 100) : 0;
        bar.style.width = pct + '%';
        barWrap.setAttribute('aria-valuenow', String(pct));
        status.textContent = done + ' / ' + total + ' hochgeladen';
    }

    function finish() {
        try {
            if (skipped.length) sessionStorage.setItem(STORE_KEY, JSON.stringify(skipped.slice(0, 50)));
        } catch (e) { /* ohne Storage fehlt nur der Zusatz-Hinweis */ }
        window.location.reload();
    }

    // ── Absenden: Bilder einzeln nacheinander senden ─────────
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (busy) return;
        if (!valid.length) {
            if (skipped.length) finish();
            return;
        }
        busy = true;
        btn.disabled = true;
        input.disabled = true;
        progress.classList.remove('d-none');

        var csrf = form.querySelector('[name="csrf_token"]').value;
        var spotId = form.querySelector('[name="spot_id"]').value;
        var total = valid.length;
        var i = 0;
        setProgress(0, total);

        function next() {
            if (i >= total) return finish();
            var f = valid[i];
            var fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('spot_id', spotId);
            fd.append('ajax', '1');
            fd.append('photos[]', f, f.name);

            fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Serverfehler' }; }); })
                .then(function (res) {
                    i++;
                    setProgress(i, total);
                    if (!res.ok) {
                        // Harter Fehler (Sitzung, Login …): Rest nicht mehr versuchen
                        valid.slice(i - 1).forEach(function (rf) {
                            skipped.push({ name: rf.name, reason: res.error || 'Upload-Fehler' });
                        });
                        return finish();
                    }
                    if (res.rate_limited) {
                        // Stundenlimit: übrige Dateien direkt als übersprungen melden
                        valid.slice(i).forEach(function (rf) {
                            skipped.push({ name: rf.name, reason: 'Stundenlimit erreicht (50 Bilder pro Stunde)' });
                        });
                        return finish();
                    }
                    next();
                })
                .catch(function () {
                    i++;
                    skipped.push({ name: f.name, reason: 'Netzwerkfehler' });
                    setProgress(i, total);
                    next();
                });
        }
        next();
    });
})();

// ── Nach dem Reload: im Browser aussortierte Dateien anhängen ──
(function () {
    var form = document.getElementById('photoUploadForm');
    var box  = document.getElementById('uploadReport');
    if (!form || !box) return;
    var key = 'fpvUploadSkipped_' + form.querySelector('[name="spot_id"]').value;
    var items;
    try {
        items = JSON.parse(sessionStorage.getItem(key) || '[]');
        sessionStorage.removeItem(key);
    } catch (e) { return; }
    if (!Array.isArray(items) || !items.length) return;

    var ul = document.getElementById('uploadSkippedList');
    if (!ul) {
        var alertBox = document.createElement('div');
        alertBox.className = 'alert alert-warning py-2';
        alertBox.setAttribute('role', 'alert');
        var strong = document.createElement('strong');
        strong.textContent = 'Nicht hochgeladen:';
        ul = document.createElement('ul');
        ul.className = 'mb-0 small';
        alertBox.appendChild(strong);
        alertBox.appendChild(ul);
        box.appendChild(alertBox);
    }
    items.slice(0, 50).forEach(function (it) {
        if (!it || typeof it.name !== 'string' || typeof it.reason !== 'string') return;
        var li = document.createElement('li');
        li.textContent = it.name.slice(0, 100) + ' – ' + it.reason.slice(0, 100);
        ul.appendChild(li);
    });
})();
