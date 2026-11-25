// =============================================================
// Spot anlegen: Highlight-Fotos (max. 3) mit Vorschau (ES-Modul)
//
// Die gewählten Fotos werden die Highlights des neuen Spots. Die
// Vorschau zeigt die Plätze 1–3; ein Klick auf ein Foto macht es
// zum Titelbild (Platz 1). Die Reihenfolge geht als Liste von
// Dateiindizes in #createHighlightOrder an spot_submit.php – der
// Server prüft alle Dateien erneut (Typ, Größe, Inhalt).
//
// Ungültige oder überzählige Dateien werden per DataTransfer aus
// dem Input entfernt, damit die Indizes auf Client und Server
// übereinstimmen. Vorschau über data:-URLs (die CSP erlaubt
// data:, aber kein blob:).
// =============================================================

let input, info, preview, orderField;
let maxFiles = 3;
let maxSize  = 10 * 1024 * 1024;
let order    = []; // Dateiindizes, Index 0 = Titelbild
let urls     = []; // Vorschau-data:-URLs je Dateiindex

// ── Vorprüfung einer Datei (Server prüft erneut) ─────────────
function checkFile(f) {
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    const typeOk = ['jpg', 'jpeg', 'png'].includes(ext) &&
                   (f.type === '' || f.type === 'image/jpeg' || f.type === 'image/png');
    if (!typeOk) return 'kein JPG/PNG';
    if (f.size > maxSize) return 'größer als 10 MB';
    return null;
}

function setInfo(text, warn) {
    info.textContent = text;
    info.className = 'small mt-2' + (warn ? ' text-warning' : ' text-secondary');
}

// ── Vorschau-Kacheln zeichnen (nur textContent/Attribute) ────
function render() {
    preview.textContent = '';
    orderField.value = order.join(',');
    preview.hidden = order.length === 0;

    order.forEach((fileIndex, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'fsg-hl-tile';
        btn.dataset.pos = String(i + 1);
        btn.dataset.fileIndex = String(fileIndex);
        btn.setAttribute('aria-label', i === 0
            ? 'Foto ' + (fileIndex + 1) + ' – Titelbild'
            : 'Foto ' + (fileIndex + 1) + ' zum Titelbild machen');

        const img = document.createElement('img');
        img.alt = '';
        if (urls[fileIndex]) img.src = urls[fileIndex];

        const badge = document.createElement('span');
        badge.className = 'fsg-hl-badge';
        badge.setAttribute('aria-hidden', 'true');
        badge.textContent = String(i + 1);

        btn.append(img, badge);
        preview.appendChild(btn);
    });
}

// ── Auswahl geändert: prüfen, kürzen, Vorschau laden ─────────
function onChange() {
    const all     = Array.from(input.files || []);
    const valid   = []; // { file, index } – index = Position im Input
    const dropped = [];

    all.forEach((f, originalIndex) => {
        const reason = checkFile(f);
        if (reason) {
            dropped.push(f.name + ' (' + reason + ')');
        } else if (valid.length >= maxFiles) {
            dropped.push(f.name + ' (mehr als ' + maxFiles + ' Fotos)');
        } else {
            valid.push({ file: f, index: originalIndex });
        }
    });

    // Input auf die gültigen Dateien kürzen, damit die Indizes denen
    // auf dem Server entsprechen. Ohne DataTransfer (sehr alte Browser)
    // bleiben die Originalpositionen, der Server sortiert selbst aus.
    if (dropped.length && typeof DataTransfer === 'function') {
        try {
            const dt = new DataTransfer();
            valid.forEach((v) => dt.items.add(v.file));
            input.files = dt.files;
            valid.forEach((v, i) => { v.index = i; });
        } catch (err) {
            // Originalpositionen behalten
        }
    }

    order = valid.map((v) => v.index);
    urls  = [];

    if (dropped.length) {
        setInfo('Nicht übernommen: ' + dropped.join(', ') + '.', true);
    } else if (valid.length > 1) {
        setInfo('Tippe auf ein Foto, um es zum Titelbild zu machen.', false);
    } else {
        setInfo('', false);
    }

    render();

    valid.forEach((v) => {
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            urls[v.index] = typeof reader.result === 'string' ? reader.result : '';
            const img = preview.querySelector('[data-file-index="' + v.index + '"] img');
            if (img && urls[v.index]) img.src = urls[v.index];
        });
        reader.readAsDataURL(v.file);
    });
}

// ── Klick auf eine Vorschau: dieses Foto wird Platz 1 ────────
function onPreviewClick(e) {
    const tile = e.target.closest('.fsg-hl-tile');
    if (!tile) return;
    const fileIndex = parseInt(tile.dataset.fileIndex, 10);
    const pos = order.indexOf(fileIndex);
    if (pos <= 0) return;
    order.splice(pos, 1);
    order.unshift(fileIndex);
    render();
    const first = preview.querySelector('.fsg-hl-tile');
    if (first) first.focus();
}

/** Setzt Vorschau und Reihenfolge zurück (Formular-Reset/Neuplatzierung) */
export function resetCreatePhotos() {
    if (!input) return;
    input.value = '';
    order = [];
    urls  = [];
    setInfo('', false);
    render();
}

/** true, wenn Fotos gewählt sind (für die Verwerfen-Rückfrage) */
export function createPhotosSelected() {
    return !!(input && input.files && input.files.length > 0);
}

/** Haengt die Handler ein (einmal beim Start) */
export function initCreatePhotos() {
    input      = document.getElementById('createPhotos');
    info       = document.getElementById('createPhotosInfo');
    preview    = document.getElementById('createPhotosPreview');
    orderField = document.getElementById('createHighlightOrder');
    if (!input || !info || !preview || !orderField) return;

    maxFiles = parseInt(input.dataset.maxFiles, 10) || maxFiles;
    maxSize  = parseInt(input.dataset.maxSize, 10)  || maxSize;

    input.addEventListener('change', onChange);
    preview.addEventListener('click', onPreviewClick);
}
