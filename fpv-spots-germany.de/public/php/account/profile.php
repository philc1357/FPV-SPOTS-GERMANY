<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Öffentliches Benutzerprofil
// Nur für eingeloggte Benutzer aufrufbar.
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

// Eingeloggt-Check zuerst (Profile nur für eingeloggte Benutzer)
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token für Header-Formulare (z. B. Logout)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/../../../private/php/core/db.php';

// Profil-ID aus URL validieren
$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$profileId) {
    header('Location: /');
    exit;
}

// Eigenes Profil → auf Dashboard umleiten
if ($profileId === (int)$_SESSION['user_id']) {
    header('Location: /dashboard.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$isLoggedIn = true;

// Profil-Daten laden (nur öffentlich sichtbare Felder)
$stmt = $pdo->prepare("SELECT username, bio, created_at FROM users WHERE id = ?");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: /');
    exit;
}

// Spots des Profilbenutzers laden
$stmt = $pdo->prepare(
    "SELECT id, name, spot_type, difficulty, latitude, longitude, created_at
     FROM spots
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$profileId]);
$userSpots = $stmt->fetchAll();

$profileUsername = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8');
$memberSince     = date('m.Y', strtotime($profile['created_at']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil von <?= $profileUsername ?> – FPV Spots Germany</title>
    <meta name="description" content="Öffentliches Profil von <?= $profileUsername ?> auf FPV Spots Germany.">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <meta name="app-csrf-token" content="<?= $csrfToken ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-4">
    <div class="row g-4">

        <!-- ====================================================
             Karte: Öffentliche Profil-Infos
        ==================================================== -->
        <div class="col-12">
            <article class="card card-dark text-light p-4 h-100">
                <h1 class="h4 mb-3">
                    <i class="bi bi-person-fill me-1"></i><?= $profileUsername ?>
                    <button class="btn btn-outline-success btn-sm ms-2"
                            data-bs-toggle="modal" data-bs-target="#sendMessageModal"
                            title="Nachricht senden">
                        <i class="bi bi-envelope-fill"></i>
                    </button>
                </h1>

                <table class="table table-dark table-borderless mb-3">
                    <tbody>
                        <tr>
                            <td class="text-secondary fw-semibold">Benutzername</td>
                            <td><?= $profileUsername ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary fw-semibold">Mitglied seit</td>
                            <td><?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Über mich / Bio (read-only) -->
                <div class="mb-0">
                    <p class="text-secondary fw-semibold mb-1">
                        <i class="bi bi-card-text me-1"></i>Über mich
                    </p>
                    <div class="text-light small">
                        <?php if (!empty($profile['bio'])): ?>
                            <?= nl2br(htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8')) ?>
                        <?php else: ?>
                            <span class="text-secondary fst-italic">Keine Beschreibung vorhanden.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>

        <!-- ====================================================
             Mini-Karte: Spots dieses Benutzers
        ==================================================== -->
        <div class="col-12">
            <section class="card card-dark text-light p-0 overflow-hidden position-relative" aria-label="Karte der Spots von <?= $profileUsername ?>">
                <h2 class="h5 px-4 pt-3 pb-3 mb-0">
                    <i class="bi bi-pin-map-fill me-1"></i>Spots von <?= $profileUsername ?>
                </h2>
                <div id="profileMap" style="height:360px;width:100%;border-radius:0 0 .375rem .375rem;"></div>

                <!-- Spot-Detail-Overlay (überdeckt die Karte) -->
                <div id="mapOverlay" style="display:none;position:absolute;left:0;right:0;bottom:0;height:360px;background:#1e1e2e;overflow-y:auto;z-index:1000;">
                    <div class="d-flex justify-content-between align-items-start p-3 border-bottom border-secondary">
                        <h3 class="h6 mb-0 fw-semibold text-light" id="mapOverlayTitle"></h3>
                        <button type="button" class="btn-close btn-close-white ms-3 flex-shrink-0" id="mapOverlayClose" aria-label="Schließen"></button>
                    </div>
                    <div class="p-3" id="mapOverlayBody"></div>
                </div>
            </section>
        </div>

        <!-- ====================================================
             Liste: Spots dieses Benutzers
        ==================================================== -->
        <div class="col-12">
            <section class="card card-dark text-light p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-pin-map-fill me-1"></i>
                        Listenansicht
                    </h2>
                    <span class="badge bg-primary"><?= count($userSpots) ?></span>
                </div>

                <?php if (empty($userSpots)): ?>
                    <p class="text-secondary mb-0">
                        <?= $profileUsername ?> hat noch keine Spots erstellt.
                    </p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php
                        $diffBadgeClass = [
                            'Anfänger'        => 'text-bg-success',
                            'Mittel'          => 'text-bg-warning',
                            'Fortgeschritten' => 'text-bg-danger',
                            'Profi'           => 'text-bg-dark',
                        ];
                        foreach ($userSpots as $spot):
                            $createdDate = date('d.m.Y', strtotime($spot['created_at']));
                            $badgeClass  = $diffBadgeClass[$spot['difficulty']] ?? 'text-bg-secondary';
                            $spotId      = (int)$spot['id'];
                        ?>
                        <div class="list-group-item bg-transparent border-secondary py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-1 fw-semibold text-light">
                                        <?= htmlspecialchars($spot['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <small class="text-secondary">
                                        <?= htmlspecialchars($spot['spot_type'], ENT_QUOTES, 'UTF-8') ?>
                                        &bull;
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($spot['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                                        &bull; <?= $createdDate ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="/?spot=<?= $spotId ?>"
                                       class="btn btn-outline-light btn-sm"
                                       title="Auf der Karte anzeigen">
                                        <i class="bi bi-map"></i>
                                    </a>
                                    <a href="/public/php/spot_detail.php?id=<?= $spotId ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       title="Spot-Details anzeigen">
                                        <i class="bi bi-info-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

    </div><!-- /.row -->
</main><!-- /.container -->

<!-- Nachricht senden Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="sendMessageModalLabel">
                    <i class="bi bi-envelope-fill me-1"></i>Nachricht an <?= $profileUsername ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <div id="msgSuccessAlert" class="alert alert-success d-none py-2 small"></div>
                <div id="msgErrorAlert" class="alert alert-danger d-none py-2 small"></div>
                <textarea id="msgBody" class="form-control bg-secondary text-light border-0"
                          rows="4" maxlength="2000"
                          placeholder="Deine Nachricht..."></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small id="msgCharCount" class="text-secondary">0 / 2000</small>
                    <button id="msgSendBtn" class="btn btn-success btn-sm">
                        <i class="bi bi-send-fill me-1"></i>Senden
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
'use strict';
(function () {
    const PROFILE_USER_ID = <?= $profileId ?>;

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
            const src = '/public/uploads/imgs/' + encodeURIComponent(filename);
            html += `<div class="col-6"><div style="aspect-ratio:16/9;overflow:hidden;border-radius:.375rem;"><img src="${src}" alt="${esc(filename)}" style="width:100%;height:100%;object-fit:cover;" loading="lazy"></div></div>`;
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
            const res = await fetch(`/public/php/api/spot.php?id=${spot.id}`);
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

    fetch(`/public/php/api/spots.php?user_id=${PROFILE_USER_ID}`)
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
</script>

<script>
'use strict';
(function () {
    const CSRF         = document.querySelector('meta[name="app-csrf-token"]').content;
    const PROFILE_ID   = <?= $profileId ?>;
    const API          = '/public/php/api/messages.php';
    const msgBody      = document.getElementById('msgBody');
    const msgCharCount = document.getElementById('msgCharCount');
    const msgSendBtn   = document.getElementById('msgSendBtn');
    const msgSuccess   = document.getElementById('msgSuccessAlert');
    const msgError     = document.getElementById('msgErrorAlert');

    msgBody.addEventListener('input', () => {
        msgCharCount.textContent = msgBody.value.length + ' / 2000';
    });

    msgSendBtn.addEventListener('click', async () => {
        const body = msgBody.value.trim();
        if (!body) { msgBody.focus(); return; }

        msgSendBtn.disabled = true;
        msgSuccess.classList.add('d-none');
        msgError.classList.add('d-none');

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send',
                    recipient_id: PROFILE_ID,
                    body: body,
                    csrf_token: CSRF,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                msgError.textContent = data.error || 'Fehler beim Senden.';
                msgError.classList.remove('d-none');
                return;
            }
            msgBody.value = '';
            msgCharCount.textContent = '0 / 2000';
            msgSuccess.innerHTML = 'Nachricht gesendet! <a href="/messages.php?conversation_id=' + data.conversation_id + '" class="alert-link">Zur Konversation</a>';
            msgSuccess.classList.remove('d-none');
        } catch (e) {
            msgError.textContent = 'Netzwerkfehler beim Senden.';
            msgError.classList.remove('d-none');
        } finally {
            msgSendBtn.disabled = false;
        }
    });

    // Zuruecksetzen beim Schliessen
    document.getElementById('sendMessageModal').addEventListener('hidden.bs.modal', () => {
        msgSuccess.classList.add('d-none');
        msgError.classList.add('d-none');
    });
})();
</script>
</body>
</html>
