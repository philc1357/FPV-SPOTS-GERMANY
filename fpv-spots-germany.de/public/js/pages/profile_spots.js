// =============================================================
// Profil – Spot-Karte und Spot-Liste
// Ausgelagert aus public/php/account/profile.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
'use strict';
(function () {
    const PROFILE_USER_ID = Number(document.currentScript.dataset.profileId);

    const TYPE_COLORS = {
        'Bando':    '#4b4e5a',
        'Feld':     '#ffe224',
        'Gebirge':  '#b1602d',
        'Park':     '#3f9826',
        'Wald':     '#2d6a4f',
        'Windpark': '#74c2e0',
        'Sonstige': '#ffffff',
    };
    const TYPE_TEXT_COLORS = {
        'Feld':     '#000000',
        'Sonstige': '#000000',
    };
    const DIFF_COLORS = {
        'Anfänger':        '#54846c',
        'Mittel':          '#fcc03c',
        'Fortgeschritten': '#d83c48',
        'Profi':           '#242430',
    };

    function buildIcon(spotType, difficulty) {
        const color       = TYPE_COLORS[spotType]    || '#fd7e14';
        const borderColor = DIFF_COLORS[difficulty]  || '#fff';
        return L.divIcon({
            className: '',
            html: `<div style="background:${color};width:20px;height:20px;border-radius:50%;border:3px solid ${borderColor};box-shadow:0 2px 6px rgba(0,0,0,.45);"></div>`,
            iconSize:   [26, 26],
            iconAnchor: [13, 13],
            popupAnchor:[0, -14],
        });
    }

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str ?? ''));
        return d.innerHTML;
    }
    function escNl2br(str) {
        return esc(str).replace(/\n/g, '<br>');
    }

    const overlay      = document.getElementById('mapOverlay');
    const overlayTitle = document.getElementById('mapOverlayTitle');
    const overlayBody  = document.getElementById('mapOverlayBody');

    document.getElementById('mapOverlayClose').addEventListener('click', () => {
        overlay.style.display = 'none';
    });

    function renderSpotPhotos(spotId, images, hasMore) {
        const section = document.getElementById('spotPhotoSection');
        if (!section || images.length === 0) return;
        let html = '<div class="row g-2 mt-2">';
        images.forEach(filename => {
            const src   = '/public/uploads/imgs/' + encodeURIComponent(filename);
            // Vorschaubild laden, bei fehlendem Thumbnail auf das Original zurueckfallen
            const thumb = '/public/uploads/imgs/thumbs/' + encodeURIComponent(String(filename).replace(/\.[^.]+$/, '')) + '.jpg';
            html += `<div class="col-6"><div style="aspect-ratio:16/9;overflow:hidden;border-radius:.375rem;"><img src="${thumb}" alt="${esc(filename)}" style="width:100%;height:100%;object-fit:cover;" loading="lazy" data-fallback-src="${esc(src)}"></div></div>`;
        });
        if (hasMore) {
            html += `<div class="col-6 d-flex align-items-center justify-content-center"><a href="/public/php/spot_detail.php?id=${spotId}" class="text-info small text-decoration-none fw-semibold">Mehr Fotos anzeigen…</a></div>`;
        }
        html += '</div>';
        section.innerHTML = html;
    }

    async function openSpotDetail(spot) {
        const diffColor = {
            'Anfänger':        'success',
            'Mittel':          'warning',
            'Fortgeschritten': 'danger',
            'Profi':           'dark',
        }[spot.difficulty] ?? 'secondary';

        const date = new Date(spot.created_at).toLocaleDateString('de-DE', {
            day: '2-digit', month: '2-digit', year: 'numeric',
        });

        overlayTitle.textContent = spot.name;
        overlayBody.innerHTML = `
            <a href="/public/php/spot_detail.php?id=${spot.id}" class="btn btn-outline-light btn-sm mb-3">
                <i class="bi bi-info-circle me-1"></i>Details anzeigen
            </a>
            <div class="mb-3">
                <span class="badge me-1" style="background:${TYPE_COLORS[spot.spot_type] ?? '#fd7e14'};color:${TYPE_TEXT_COLORS[spot.spot_type] ?? '#ffffff'}">${esc(spot.spot_type)}</span>
                <span class="badge text-bg-${diffColor}">${esc(spot.difficulty)}</span>
            </div>
            <p class="mb-3">${escNl2br(spot.description || 'Keine Beschreibung vorhanden.')}</p>
            <p class="mb-3"><strong>Parkmöglichkeit:</strong><br>${esc(spot.parking_info || 'Unbekannt')}</p>
            <p class="text-secondary small mb-1">
                <i class="bi bi-geo-alt-fill"></i> ${parseFloat(spot.latitude).toFixed(5)}, ${parseFloat(spot.longitude).toFixed(5)}
            </p>
            <p class="text-secondary small mb-0">
                <i class="bi bi-person-fill"></i> <a href="/profile.php?id=${encodeURIComponent(spot.user_id)}" class="text-info text-decoration-none">${esc(spot.username)}</a> &nbsp;&bull;&nbsp; <i class="bi bi-calendar3"></i> ${date}
            </p>
            <div id="spotPhotoSection"></div>
        `;

        overlay.style.display = 'block';

        try {
            const res = await fetch(`/api/spot.php?id=${spot.id}`);
            if (!res.ok) return;
            const data = await res.json();
            renderSpotPhotos(spot.id, data.images || [], !!data.has_more_images);
        } catch (_) {}
    }

    // Karte initialisieren
    const map = L.map('profileMap', { zoomControl: true, scrollWheelZoom: false });
    setTimeout(() => map.invalidateSize(), 100);

    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    });
    const satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 19,
    });
    streetLayer.addTo(map);
    L.control.layers({ 'Straße': streetLayer, 'Satellit': satelliteLayer }, null, { position: 'bottomleft' }).addTo(map);

    fetch(`/api/spots.php?user_id=${PROFILE_USER_ID}`)
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(spots => {
            if (spots.length === 0) {
                map.setView([51.1657, 10.4515], 6);
                return;
            }
            const bounds = L.latLngBounds();
            spots.forEach(spot => {
                const marker = L.marker([spot.latitude, spot.longitude], {
                    icon: buildIcon(spot.spot_type, spot.difficulty),
                    title: spot.name,
                }).addTo(map);
                marker.on('click', () => openSpotDetail(spot));
                bounds.extend([spot.latitude, spot.longitude]);
            });
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        })
        .catch(err => {
            console.error('Fehler beim Laden der Profilkarte:', err);
            map.setView([51.1657, 10.4515], 6);
        });
})();
