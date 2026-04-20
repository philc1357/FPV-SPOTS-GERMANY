<?php
// =============================================================
// FPV Spots Germany – Spot-Detailansicht mit Kommentaren & Bewertungen
// =============================================================
session_start();
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

require_once __DIR__ . '/../../../private/php/core/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Spot-ID aus URL
$spotId = (int)($_GET['id'] ?? 0);
if ($spotId <= 0) {
    header('Location: /');
    exit;
}

// Spot laden
$stmt = $pdo->prepare(
    "SELECT s.id, s.user_id, s.name, s.description,
            s.latitude, s.longitude, s.spot_type, s.difficulty,
            s.parking_info, s.parking_updated_by, s.parking_updated_at,
            s.created_at, u.username,
            pu.username AS parking_editor
     FROM spots s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN users pu ON s.parking_updated_by = pu.id
     WHERE s.id = ?"
);
$stmt->execute([$spotId]);
$spot = $stmt->fetch();

if (!$spot) {
    header('Location: /');
    exit;
}

// Durchschnittsbewertung + Anzahl
$stmt = $pdo->prepare("SELECT AVG(stars) AS avg_stars, COUNT(*) AS count FROM ratings WHERE spot_id = ?");
$stmt->execute([$spotId]);
$ratingData = $stmt->fetch();
$avgStars   = $ratingData['avg_stars'] ? round($ratingData['avg_stars'], 1) : null;
$ratingCount = (int)$ratingData['count'];

// Eigene Bewertung des Users
$userRating = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT stars FROM ratings WHERE spot_id = ? AND user_id = ?");
    $stmt->execute([$spotId, $userId]);
    $row = $stmt->fetch();
    if ($row) $userRating = (int)$row['stars'];
}

// Bilder laden
$stmt = $pdo->prepare(
    "SELECT si.filename, si.created_at, u.username
     FROM spot_images si
     JOIN users u ON si.user_id = u.id
     WHERE si.spot_id = ?
     ORDER BY si.created_at DESC"
);
$stmt->execute([$spotId]);
$images = $stmt->fetchAll();

$uploadDir = __DIR__ . '/../../uploads/imgs/';
$images = array_values(array_filter($images, fn($img) => is_file($uploadDir . $img['filename'])));

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

// Kommentare laden
$stmt = $pdo->prepare(
    "SELECT c.id, c.user_id, c.body, c.created_at, u.username
     FROM comments c
     JOIN users u ON c.user_id = u.id
     WHERE c.spot_id = ?
     ORDER BY c.created_at DESC"
);
$stmt->execute([$spotId]);
$comments = $stmt->fetchAll();

// Favoriten-Status des Users
$isFavorite = false;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT 1 FROM spot_favorites WHERE user_id = ? AND spot_id = ?");
    $stmt->execute([$userId, $spotId]);
    $isFavorite = (bool)$stmt->fetchColumn();
}

// Typ-Farben
$typeColors = [
    'Bando'    => '#4b4e5a', 'Feld'     => '#ffe224',
    'Gebirge'  => '#b1602d', 'Park'     => '#3f9826',
    'Wald'     => '#2d6a4f', 'Windpark' => '#74c2e0',
    'Sonstige' => '#ffffff',
];
$diffColors = [
    'Anfänger' => 'success', 'Mittel' => 'warning',
    'Fortgeschritten' => 'danger', 'Profi' => 'dark',
];

$typeColor     = $typeColors[$spot['spot_type']] ?? '#fd7e14';
$typeTextColor = in_array($spot['spot_type'], ['Feld', 'Sonstige']) ? '#000000' : '#ffffff';
$diffColor = $diffColors[$spot['difficulty']] ?? 'secondary';
$createdDate = date('d.m.Y', strtotime($spot['created_at']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($spot['name']) ?> – FPV Spots Germany</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr($spot['description'] ?: $spot['name'] . ' – FPV Spot in Deutschland', 0, 155)) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= htmlspecialchars($spot['name']) ?> – FPV Spots Germany">
    <meta property="og:description" content="<?= htmlspecialchars(mb_substr($spot['description'] ?: 'FPV Spot in Deutschland', 0, 155)) ?>">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="de_DE">
    <link rel="canonical" href="https://fpv-spots-germany.de/public/php/spot_detail.php?id=<?= $spotId ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/spot_detail.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<?php include __DIR__ . '/../../includes/login_modal.php'; ?>

<main class="container py-4">
    <?php if (!empty($_GET['reported'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Deine Meldung wurde erfolgreich übermittelt. Vielen Dank!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>
    <div class="alert alert-info alert-dismissible fade show small" role="note">
        <i class="bi bi-info-circle-fill me-1"></i>
        Diese Spotinformation ist nutzergeneriert. Informiere dich vor dem Besuch über geltende Gesetze
        (Betretungsrechte, Luftrecht, Naturschutz).
        <a href="/nutzungsbedingungen.php" class="alert-link">Mehr erfahren &rarr;</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>

    <div class="row g-4 justify-content-center">

        <!-- ====================================================
             Spot-Info
        ==================================================== -->
        <div class="col-12 col-lg-8">
            <div class="card card-dark text-light p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3 mb-0"><?= htmlspecialchars($spot['name']) ?></h1>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="/?spot=<?= $spotId ?>" class="btn btn-outline-light btn-sm" title="Auf der Karte anzeigen"><i class="bi bi-map"></i></a>
                        <?php if ($isLoggedIn): ?>
                            <form method="POST" action="/private/php/spots/favorite_submit.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                                <input type="hidden" name="redirect" value="/public/php/spot_detail.php?id=<?= $spotId ?>">
                                <button type="submit" class="btn btn-sm <?= $isFavorite ? 'btn-warning' : 'btn-outline-warning' ?>"
                                        title="<?= $isFavorite ? 'Aus Favoriten entfernen' : 'Als Favorit speichern' ?>">
                                    <i class="bi bi-heart<?= $isFavorite ? '-fill' : '' ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                                <li>
                                    <button class="dropdown-item" onclick="copySpotLink(this)">
                                        <i class="bi bi-share me-2"></i>Link kopieren
                                    </button>
                                </li>
                                <?php if ($isLoggedIn): ?>
                                    <li>
                                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reportModal">
                                            <i class="bi bi-flag me-2"></i>Spot melden
                                        </button>
                                    </li>
                                <?php endif; ?>
                                <?php if ($isLoggedIn && ($userId === (int)$spot['user_id'] || !empty($_SESSION['is_admin']))): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="/public/php/edit_spot.php?id=<?= $spotId ?>">
                                            <i class="bi bi-pencil me-2"></i>Spot bearbeiten
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="/private/php/spots/delete_spot_submit.php"
                                              onsubmit="return confirm('Spot wirklich löschen? Alle Kommentare, Bewertungen und Fotos werden ebenfalls gelöscht.')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash3 me-2"></i>Spot löschen
                                            </button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="badge me-1" style="background:<?= $typeColor ?>;color:<?= $typeTextColor ?>"><?= htmlspecialchars($spot['spot_type']) ?></span>
                    <span class="badge text-bg-<?= $diffColor ?>"><?= htmlspecialchars($spot['difficulty']) ?></span>
                </div>

                <p class="mb-3"><?= nl2br(htmlspecialchars($spot['description'] ?: 'Keine Beschreibung vorhanden.')) ?></p>

                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong>Parkmöglichkeit:</strong>
                        <?php if ($isLoggedIn): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1"
                                    onclick="toggleEditParking()" title="Parkmöglichkeit bearbeiten"><i class="bi bi-pencil"></i></button>
                        <?php endif; ?>
                    </div>

                    <div id="parking-display">
                        <?= nl2br(htmlspecialchars($spot['parking_info'] ?: 'Unbekannt')) ?>
                        <?php if ($spot['parking_editor'] && $spot['parking_updated_at']): ?>
                            <small class="text-secondary d-block mt-1">
                                <i class="bi bi-pencil"></i> <?= htmlspecialchars($spot['parking_editor']) ?> &bull; <?= date('d.m.Y H:i', strtotime($spot['parking_updated_at'])) ?> Uhr
                            </small>
                        <?php endif; ?>
                    </div>

                    <?php if ($isLoggedIn): ?>
                    <div id="parking-edit" class="d-none mt-2">
                        <form method="POST" action="/private/php/spots/parking_info_submit.php">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="detailParkingUnknown"
                                       <?= ($spot['parking_info'] ?: 'Unbekannt') === 'Unbekannt' ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="detailParkingUnknown">Unbekannt</label>
                            </div>
                            <textarea name="parking_info" id="detailParkingInfo"
                                      class="form-control bg-secondary text-light border-0 mb-2"
                                      placeholder="z. B. Kostenloser Parkplatz direkt am Feld…"
                                      rows="2" maxlength="500"
                                      <?= ($spot['parking_info'] ?: 'Unbekannt') === 'Unbekannt' ? 'disabled' : '' ?>><?= ($spot['parking_info'] ?: 'Unbekannt') !== 'Unbekannt' ? htmlspecialchars($spot['parking_info'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleEditParking()">Abbrechen</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <p class="text-secondary small mb-1">
                    <i class="bi bi-geo-alt-fill"></i> <?= number_format($spot['latitude'], 5) ?>, <?= number_format($spot['longitude'], 5) ?>
                </p>
                <p class="text-secondary small mb-0">
                    <i class="bi bi-person-fill"></i> <a href="/profile.php?id=<?= (int)$spot['user_id'] ?>" class="text-info text-decoration-none profile-link" title="Profil"><?= htmlspecialchars($spot['username']) ?></a> &bull; <i class="bi bi-calendar3"></i> <?= $createdDate ?>
                </p>
            </div>

            <!-- ====================================================
                 Fotos
            ==================================================== -->
            <div class="card card-dark text-light p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Fotos</h2>
                    <span class="badge bg-primary"><?= count($images) ?></span>
                </div>

                <?php if ($uploadError): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($uploadError) ?></div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/upload_submit.php" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" name="photo" accept=".jpg,.jpeg,.png"
                                   class="form-control form-control-sm bg-secondary text-light border-0" required>
                            <button type="submit" class="btn btn-success btn-sm text-nowrap">Foto hochladen</button>
                        </div>
                        <small class="text-secondary">JPG/PNG, max. 5 MB</small>
                    </form>
                <?php else: ?>
                    <p class="text-secondary small mb-3">
                        <a href="/public/php/login.php" class="text-success">Einloggen</a>, um Fotos hochzuladen.
                    </p>
                <?php endif; ?>

                <?php if (empty($images)): ?>
                    <p class="text-secondary">Noch keine Fotos vorhanden.</p>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($images as $img): ?>
                            <div class="col-6 col-md-4">
                                <a href="/public/uploads/imgs/<?= htmlspecialchars($img['filename']) ?>" target="_blank">
                                    <img src="/public/uploads/imgs/<?= htmlspecialchars($img['filename']) ?>"
                                         alt="Foto von <?= htmlspecialchars($spot['name']) ?>, hochgeladen von <?= htmlspecialchars($img['username']) ?>"
                                         class="img-fluid rounded"
                                         loading="lazy"
                                         style="width:100%; height:160px; object-fit:cover;"
                                         onerror="this.closest('.col-6').style.display='none'">
                                </a>
                                <small class="text-secondary d-block mt-1">
                                    <?= htmlspecialchars($img['username']) ?> &bull; <?= date('d.m.Y', strtotime($img['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ====================================================
                 Bewertung
            ==================================================== -->
            <div class="card card-dark text-light p-4 mt-4">
                <h2 class="h5 mb-3">Bewertung</h2>

                <?php if ($avgStars !== null): ?>
                    <p class="mb-2">
                        <span class="stars-display fs-5">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= $i <= round($avgStars) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>' ?>
                            <?php endfor; ?>
                        </span>
                        <span class="text-secondary ms-2"><?= $avgStars ?> / 5 (<?= $ratingCount ?> <?= $ratingCount === 1 ? 'Bewertung' : 'Bewertungen' ?>)</span>
                    </p>
                <?php else: ?>
                    <p class="text-secondary mb-2">Noch keine Bewertungen.</p>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/rate_submit.php" class="d-flex align-items-center gap-3">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <div id="starRating" class="d-flex flex-row-reverse justify-content-end">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="stars" value="<?= $i ?>" id="star<?= $i ?>" class="d-none" <?= $userRating === $i ? 'checked' : '' ?>>
                                <label for="star<?= $i ?>" class="star <?= $userRating >= $i ? 'active' : '' ?>"><i class="bi bi-star-fill"></i></label>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-warning">Bewerten</button>
                        <?php if ($userRating > 0): ?>
                            <span class="text-secondary small">Deine Bewertung: <?= $userRating ?>/5</span>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="text-secondary small mb-0">
                        <a href="/public/php/login.php" class="text-success">Einloggen</a>, um zu bewerten.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ====================================================
                 Kommentare
            ==================================================== -->
            <div class="card card-dark text-light p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Kommentare</h2>
                    <span class="badge bg-primary"><?= count($comments) ?></span>
                </div>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/comments/comment_submit.php" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <textarea name="body" class="form-control bg-secondary text-light border-0 mb-2"
                                  placeholder="Kommentar schreiben..." rows="3"
                                  minlength="3" maxlength="1000" required></textarea>
                        <button type="submit" class="btn btn-success btn-sm">Kommentar absenden</button>
                    </form>
                <?php else: ?>
                    <p class="text-secondary small mb-3">
                        <a href="/public/php/login.php" class="text-success">Einloggen</a>, um zu kommentieren.
                    </p>
                <?php endif; ?>

                <?php if (empty($comments)): ?>
                    <p class="text-secondary">Noch keine Kommentare. Sei der Erste!</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment):
                        $commentDate = date('d.m.Y H:i', strtotime($comment['created_at']));
                        $isCommentOwner = $isLoggedIn && $userId === (int)$comment['user_id'];
                        $isAdmin = !empty($_SESSION['is_admin']);
                        $canDelete = $isCommentOwner || $isAdmin;
                    ?>
                    <article class="comment-item py-3" id="comment-<?= $comment['id'] ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($comment['username']) ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-secondary small"><?= $commentDate ?></span>
                                <?php if ($isCommentOwner): ?>
                                    <button class="btn btn-outline-primary btn-sm py-0 px-1"
                                            onclick="toggleEditComment(<?= $comment['id'] ?>)"
                                            title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <form method="POST" action="/private/php/comments/comment_delete_submit.php"
                                          class="d-inline"
                                          onsubmit="return confirm('Kommentar wirklich löschen?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                title="Löschen"><i class="bi bi-trash3"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Anzeige -->
                        <div id="comment-display-<?= $comment['id'] ?>">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['body'])) ?></p>
                        </div>

                        <?php if ($isCommentOwner): ?>
                        <!-- Bearbeiten-Formular (versteckt) -->
                        <div id="comment-edit-<?= $comment['id'] ?>" class="d-none mt-2">
                            <form method="POST" action="/private/php/comments/comment_edit_submit.php">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                <textarea name="body" class="form-control bg-secondary text-light border-0 mb-2"
                                          rows="3" minlength="3" maxlength="1000" required><?= htmlspecialchars($comment['body']) ?></textarea>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="toggleEditComment(<?= $comment['id'] ?>)">Abbrechen</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script>
// Spot-Link in Zwischenablage kopieren
function copySpotLink(btn) {
    const url = window.location.href;
    const original = btn.innerHTML;
    const done = () => {
        btn.innerHTML = '<i class="bi bi-check2"></i> Kopiert!';
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(done).catch(() => { fallbackCopy(url); done(); });
    } else {
        fallbackCopy(url); done();
    }
}
function fallbackCopy(text) {
    const el = document.createElement('textarea');
    el.value = text;
    el.style.position = 'fixed';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
}

// Kommentar-Bearbeitung ein-/ausblenden
function toggleEditComment(id) {
    document.getElementById('comment-display-' + id).classList.toggle('d-none');
    document.getElementById('comment-edit-' + id).classList.toggle('d-none');
}

// Parkmöglichkeit bearbeiten ein-/ausblenden
function toggleEditParking() {
    document.getElementById('parking-display').classList.toggle('d-none');
    document.getElementById('parking-edit').classList.toggle('d-none');
}
(function () {
    var cb = document.getElementById('detailParkingUnknown');
    if (!cb) return;
    cb.addEventListener('change', function () {
        var ta = document.getElementById('detailParkingInfo');
        if (this.checked) {
            ta.disabled = true;
            ta.value = '';
        } else {
            ta.disabled = false;
            ta.focus();
        }
    });
})();

// Stern-Hover-Effekt
document.querySelectorAll('#starRating .star').forEach(label => {
    label.addEventListener('mouseenter', () => {
        const val = parseInt(label.getAttribute('for').replace('star', ''));
        document.querySelectorAll('#starRating .star').forEach(s => {
            const sv = parseInt(s.getAttribute('for').replace('star', ''));
            s.classList.toggle('active', sv <= val);
        });
    });
});
const starRating = document.getElementById('starRating');
if (starRating) {
    starRating.addEventListener('mouseleave', () => {
        const checked = starRating.querySelector('input:checked');
        const val = checked ? parseInt(checked.value) : 0;
        document.querySelectorAll('#starRating .star').forEach(s => {
            const sv = parseInt(s.getAttribute('for').replace('star', ''));
            s.classList.toggle('active', sv <= val);
        });
    });
}
</script>

<?php if ($isLoggedIn): ?>
<!-- ============================================================
     Spot-Melden-Modal
============================================================ -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h2 class="modal-title h5" id="reportModalLabel"><i class="bi bi-flag me-2"></i>Spot melden</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="POST" action="/private/php/contact/report_submit.php" id="reportForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Art der Meldung</label>
                        <select class="form-select bg-dark text-light border-secondary" id="reportType" name="report_type" required>
                            <option value="" disabled selected>Bitte wählen …</option>
                            <option value="Spot-Allgemein">Spot-Allgemein (z.B. existiert nicht mehr)</option>
                            <option value="Spot-Info">Spot-Info (falsche oder veraltete Angaben)</option>
                            <option value="Foto">Foto (unangemessenes oder falsches Bild)</option>
                            <option value="Kommentar">Kommentar (unangemessener Kommentar)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reportBody" class="form-label">Beschreibung <small class="text-secondary">(mind. 10 Zeichen)</small></label>
                        <textarea class="form-control bg-dark text-light border-secondary" id="reportBody" name="body"
                                  rows="4" minlength="10" maxlength="1000" required
                                  placeholder="Beschreibe das Problem genauer …"></textarea>
                        <div class="form-text text-secondary"><span id="reportCharCount">0</span> / 1000</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">Meldung absenden</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var ta = document.getElementById('reportBody');
    var counter = document.getElementById('reportCharCount');
    if (!ta || !counter) return;
    ta.addEventListener('input', function () {
        counter.textContent = ta.value.length;
    });
})();
</script>
<?php endif; ?>

</body>
</html>
