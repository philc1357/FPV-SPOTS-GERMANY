// =============================================================
// Allgemeine Hilfsfunktionen der Karte (ES-Modul)
// =============================================================

/** XSS-Schutz: Text sicher in HTML einbetten (statt Rohwerte in innerHTML) */
export function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

export function escNl2br(str) {
    return esc(str).replace(/\n/g, '<br>');
}

// Vorschaubild-URL zu einem Upload-Dateinamen: thumbs/<name>.jpg.
// Erzeugt werden die Dateien serverseitig, siehe private/php/core/image_utils.php.
export function thumbSrc(filename) {
    const base = String(filename).replace(/\.[^.]+$/, '');
    return '/public/uploads/imgs/thumbs/' + encodeURIComponent(base) + '.jpg';
}

/** Alert-Box mit Text einblenden */
export function showAlert(id, message) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('d-none');
}

export function hideAlert(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('d-none');
}

/** Offcanvas-Instanz holen oder neu anlegen (Bootstrap-Singleton) */
export function getOrCreateOffcanvas(id) {
    const el = document.getElementById(id);
    return bootstrap.Offcanvas.getOrCreateInstance(el);
}
