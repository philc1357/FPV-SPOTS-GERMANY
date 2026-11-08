// =============================================================
// Spot-Detail-Offcanvas (ES-Modul)
// =============================================================
import { config, state } from '../state.js';
import { esc, escNl2br, thumbSrc, getOrCreateOffcanvas } from '../util.js';
import { TYPE_COLORS, TYPE_TEXT_COLORS } from '../spot_style.js';
import { showA3Circle, clearA3Circle } from './a3_circle.js';

/** Haengt die Offcanvas-/Profillink-Handler ein (einmal beim Start) */
export function initSpotPanel() {
    // Kreis verschwindet mit dem Offcanvas, sonst bleibt er beim nächsten Spot stehen.
    // Ausnahme: Im Platzierungs-Modus bzw. bei gesetztem Vorschau-Marker gehoert der
    // Kreis bereits zum neuen Spot – dieses Event trifft dann verspaetet ein.
    document.getElementById('spotDetailOffcanvas')
        .addEventListener('hidden.bs.offcanvas', () => {
            if (!state.placingMode && !state.newSpotMarker) clearA3Circle();
            state.activeSpot = null;
        });


    // ---------------------------------------------------------------
    // Profillink-Klick: Login-Modal für nicht eingeloggte Nutzer
    // ---------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.profile-link');
        if (!link) return;
        e.preventDefault();
        if (!config.isLoggedIn) {
            new bootstrap.Modal(document.getElementById('loginModal')).show();
            return;
        }
        window.location.href = '/profile.php?id=' + link.dataset.userId;
    });

}

// ---------------------------------------------------------------
// Spot-Detail-Offcanvas öffnen
// ---------------------------------------------------------------
export async function openSpotDetail(spot) {
    state.activeSpot = spot;

    // Schwierigkeits-Farbe für Badge
    const diffColor = {
        'Anfänger':      'success',
        'Mittel':        'warning',
        'Fortgeschritten': 'danger',
        'Profi':         'dark',
    }[spot.difficulty] ?? 'secondary';

    const date = new Date(spot.created_at).toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });

    document.getElementById('spotDetailLabel').innerHTML =
        `${esc(spot.name)} <a href="/public/php/spot_detail.php?id=${spot.id}" class="btn btn-outline-light btn-sm ms-2" title="Detailansicht"><i class="bi bi-info-circle"></i> Details anzeigen</a>`;
    document.getElementById('spotDetailBody').innerHTML = `
        <div class="mb-3">
            <span class="badge me-1" style="background:${TYPE_COLORS[spot.spot_type] ?? '#fd7e14'};color:${TYPE_TEXT_COLORS[spot.spot_type] ?? '#ffffff'}">${esc(spot.spot_type)}</span>
            <span class="badge text-bg-${diffColor}">${esc(spot.difficulty)}</span>
            ${spot.copter_size ? spot.copter_size.split(',').map(s => `<span class="badge text-bg-secondary ms-1">${esc(s.trim())}</span>`).join('') : ''}
        </div>
        <p class="mb-3">${escNl2br(spot.description || 'Keine Beschreibung vorhanden.')}</p>
        <p class="mb-3"><strong>Parkmöglichkeit:</strong><br>${esc(spot.parking_info || 'Unbekannt')}</p>
        <p class="text-secondary small mb-1">
            <i class="bi bi-geo-alt-fill"></i> ${parseFloat(spot.latitude).toFixed(5)}, ${parseFloat(spot.longitude).toFixed(5)}
        </p>
        ${state.pilotClass === 'a3' ? `<div class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>A3:</strong> Der gelbe Kreis zeigt 150 m Abstand.
            Liegt darin ein Wohn-, Gewerbe-, Industrie- oder Erholungsgebiet, darfst du hier nicht starten.
        </div>` : ''}
        <p class="text-secondary small mb-0">
            <i class="bi bi-person-fill"></i> <a href="#" class="text-info text-decoration-none profile-link" data-user-id="${encodeURIComponent(spot.user_id)}">${esc(spot.username)}</a> &nbsp;&bull;&nbsp; <i class="bi bi-calendar3"></i> ${date}
        </p>
        <div id="spotPhotoSection"></div>
    `;

    showA3Circle(parseFloat(spot.latitude), parseFloat(spot.longitude));
    getOrCreateOffcanvas('spotDetailOffcanvas').show();

    try {
        const res = await fetch(`/api/spot.php?id=${spot.id}`);
        if (!res.ok) return;
        const data = await res.json();
        renderSpotPhotos(spot.id, data.images || [], !!data.has_more_images);
    } catch (e) {}
}

function renderSpotPhotos(spotId, images, hasMore) {
    const section = document.getElementById('spotPhotoSection');
    if (!section || images.length === 0) return;

    let html = '<div class="row g-2 mt-2">';
    images.forEach(filename => {
        const src   = '/public/uploads/imgs/' + encodeURIComponent(filename);
        const thumb = thumbSrc(filename);
        // Erster Fehler: auf das Original zurueckfallen (Thumbnail fehlt).
        // Zweiter Fehler: die Kachel ausblenden, das Bild gibt es nicht mehr.
        // Umsetzung per data-Attribut in public/js/ui_actions.js (CSP: keine Inline-Handler).
        html += `<div class="col-6">
            <div style="aspect-ratio:16/9;overflow:hidden;border-radius:.375rem;">
                <img src="${thumb}" alt="${esc(filename)}"
                     style="width:100%;height:100%;object-fit:cover;"
                     loading="lazy"
                     data-fallback-src="${esc(src)}"
                     data-hide-on-error=".col-6">
            </div>
        </div>`;
    });
    if (hasMore) {
        html += `<div class="col-6 d-flex align-items-center justify-content-center">
            <a href="/spot_detail.php?id=${spotId}"
               class="text-info small text-decoration-none fw-semibold">
                Mehr Fotos anzeigen…
            </a>
        </div>`;
    }
    html += '</div>';
    section.innerHTML = html;
}
