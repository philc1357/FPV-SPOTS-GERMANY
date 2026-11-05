<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Spot-Detailansicht mit Kommentaren & Bewertungen
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

require_once __DIR__ . '/../../../private/php/core/db.php';
require_once __DIR__ . '/../../../private/php/core/geozones.php';
require_once __DIR__ . '/../../../private/php/core/image_utils.php';

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
            s.copter_size,
            s.parking_info, s.parking_updated_by, s.parking_updated_at,
            s.created_at, s.zones_checked_at, u.username,
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

// Null-Normalisierung für String-Felder
$spot['name']        = (string)($spot['name']        ?? '');
$spot['description'] = (string)($spot['description'] ?? '');
$spot['spot_type']   = (string)($spot['spot_type']   ?? '');
$spot['difficulty']  = (string)($spot['difficulty']  ?? '');
$spot['copter_size'] = (string)($spot['copter_size'] ?? '');
$spot['parking_info']= (string)($spot['parking_info']?? 'Unbekannt');
$spot['username']    = (string)($spot['username']    ?? '');

// -------------------------------------------------------------
// Geozonen-Auswertung fuer diesen Spot
//
// $zonesChecked unterscheidet "keine Zone gefunden" von "nie geprueft".
// Ohne diese Trennung wuerde ein fehlgeschlagener Check wie eine
// Freigabe aussehen – der einzige wirklich gefaehrliche Fehler hier.
// -------------------------------------------------------------
$pilotClass   = pilot_class_from_cookie();
// Liegt der Spot im Ausland, kennt DIPUL ihn gar nicht. "Keine Zone
// gefunden" waere dort eine Fehlentwarnung – deshalb ein eigener Zustand.
$inDipulArea  = point_in_dipul_area((float)$spot['latitude'], (float)$spot['longitude']);
$zonesChecked = $inDipulArea && !empty($spot['zones_checked_at']);
$spotZones    = $zonesChecked ? load_spot_zones($pdo, $spotId, $pilotClass) : [];

// Konnten die Zonen nicht gelesen werden (null), gilt der Spot wieder als
// ungeprueft. Sonst stuende dort "keine Zone gefunden" – eine Entwarnung
// auf Basis eines Datenbankfehlers.
if ($spotZones === null) {
    $zonesChecked = false;
    $spotZones    = [];
}

// Pseudo-Zone: der 150-m-Abstand der Unterkategorie A3 steht in keiner
// amtlichen Karte und wird deshalb unabhaengig vom WFS-Ergebnis gezeigt
$a3Hint = zone_verdict('a3_abstand', $pilotClass);

// Strengste gefundene Stufe bestimmt die Farbe der Kopfzeile
$worstAmpel = 'info';
foreach ($spotZones as $z) {
    if (zone_ampel_rank($z['ampel']) > zone_ampel_rank($worstAmpel)) {
        $worstAmpel = $z['ampel'];
    }
}

// Ampel -> Bootstrap-Kontextklasse und Symbol
$ampelStyle = [
    'rot'  => ['badge' => 'text-bg-danger',  'icon' => 'bi-x-octagon-fill',        'wort' => 'Verboten'],
    'gelb' => ['badge' => 'text-bg-warning', 'icon' => 'bi-exclamation-triangle-fill', 'wort' => 'Eingeschränkt'],
    'info' => ['badge' => 'text-bg-info',    'icon' => 'bi-info-circle-fill',      'wort' => 'Hinweis'],
];

// Durchschnittsbewertung + Anzahl
$stmt = $pdo->prepare("SELECT AVG(stars) AS avg_stars, COUNT(*) AS count FROM ratings WHERE spot_id = ?");
$stmt->execute([$spotId]);
$ratingData = $stmt->fetch();
$avgStars   = $ratingData['avg_stars'] !== null ? round((float)$ratingData['avg_stars'], 1) : null;
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
    "SELECT si.id, si.user_id, si.filename, si.created_at, u.username
     FROM spot_images si
     JOIN users u ON si.user_id = u.id
     WHERE si.spot_id = ?
     ORDER BY si.created_at DESC"
);
$stmt->execute([$spotId]);
$images = $stmt->fetchAll();

$images = array_map(static function (array $img): array {
    $img['username'] = (string)($img['username'] ?? '');
    $img['filename'] = (string)($img['filename'] ?? '');
    return $img;
}, $images);

$uploadDir = __DIR__ . '/../../uploads/imgs/';
$images = array_values(array_filter($images, fn($img) => is_file($uploadDir . $img['filename'])));

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);

// Ergebnis eines (Mehrfach-)Uploads – nur für diesen Spot, einmalig anzeigen
$uploadReport = null;
if (is_array($_SESSION['upload_report'] ?? null)) {
    if ((int)($_SESSION['upload_report']['spot_id'] ?? 0) === $spotId) {
        $uploadReport = $_SESSION['upload_report'];
    }
    unset($_SESSION['upload_report']);
}
$uploadMaxFiles = !empty($_SESSION['is_admin']) ? 0 : 10; // 0 = unbegrenzt (Admin)

// YouTube-Videos laden
$stmt = $pdo->prepare(
    "SELECT title, youtube_id FROM spot_videos WHERE spot_id = ? ORDER BY position ASC, id ASC"
);
$stmt->execute([$spotId]);
$videos = $stmt->fetchAll();

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

$comments = array_map(static function (array $comment): array {
    $comment['username'] = (string)($comment['username'] ?? '');
    $comment['body']     = (string)($comment['body']     ?? '');
    return $comment;
}, $comments);

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
$createdDate = $spot['created_at'] ? date('d.m.Y', strtotime((string)$spot['created_at'])) : 'Unbekannt';
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
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/spot_detail.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

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
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                                <li>
                                    <a class="dropdown-item" href="/?spot=<?= $spotId ?>">
                                        <i class="bi bi-map me-2"></i>Auf der Karte anzeigen
                                    </a>
                                </li>
                                <?php if ($spot['latitude'] !== null && $spot['longitude'] !== null): ?>
                                    <li>
                                        <a class="dropdown-item"
                                           href="https://www.google.com/maps/search/?api=1&query=<?= urlencode(number_format((float)$spot['latitude'], 6, '.', '') . ',' . number_format((float)$spot['longitude'], 6, '.', '')) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer">
                                            <i class="bi bi-google me-2"></i>Auf Google Maps anzeigen
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($isLoggedIn): ?>
                                    <li>
                                        <form method="POST" action="/private/php/spots/favorite_submit.php">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                                            <input type="hidden" name="redirect" value="/public/php/spot_detail.php?id=<?= $spotId ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-heart<?= $isFavorite ? '-fill' : '' ?> me-2"></i><?= $isFavorite ? 'Aus Favoriten entfernen' : 'Als Favorit speichern' ?>
                                            </button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" onclick="copySpotLink(this)">
                                        <i class="bi bi-share me-2"></i>Spot teilen
                                    </button>
                                </li>
                                <?php if ($spot['latitude'] !== null && $spot['longitude'] !== null): ?>
                                    <li>
                                        <button class="dropdown-item" onclick="copySpotCoords(this)"
                                                data-lat="<?= htmlspecialchars(number_format((float)$spot['latitude'], 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-lng="<?= htmlspecialchars(number_format((float)$spot['longitude'], 6, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-crosshair me-2"></i>Koordinaten kopieren
                                        </button>
                                    </li>
                                <?php endif; ?>
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
                    <?php if (!empty($spot['copter_size'])): ?>
                        <?php foreach (explode(',', $spot['copter_size']) as $cs): ?>
                            <span class="badge text-bg-secondary ms-1"><?= htmlspecialchars(trim($cs), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                                <i class="bi bi-pencil"></i> <?= htmlspecialchars($spot['parking_editor']) ?> &bull; <?= date('d.m.Y H:i', strtotime((string)$spot['parking_updated_at'])) ?> Uhr
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
                    <i class="bi bi-geo-alt-fill"></i> <?= $spot['latitude'] !== null ? number_format((float)$spot['latitude'], 5) : 'N/A' ?>, <?= $spot['longitude'] !== null ? number_format((float)$spot['longitude'], 5) : 'N/A' ?>
                </p>
                <p class="text-secondary small mb-0">
                    <i class="bi bi-person-fill"></i> <a href="/profile.php?id=<?= (int)$spot['user_id'] ?>" class="text-info text-decoration-none profile-link" title="Profil"><?= htmlspecialchars($spot['username']) ?></a> &bull; <i class="bi bi-calendar3"></i> <?= $createdDate ?>
                </p>
            </div>

            <!-- ====================================================
                 Darf ich hier fliegen? – DIPUL-Geozonen

                 Reihenfolge bewusst: erst der Pruefstatus, dann die
                 gefundenen Zonen (strengste zuerst), dann die Pflichten
                 der gewaehlten Betriebsklasse, zuletzt der Haftungs-
                 ausschluss. Es gibt keine gruene Freigabe – die beste
                 Aussage lautet "keine Zone in unseren Daten".

                 Bewusst zugeklappt wie der Wetter-Bereich: der Badge-
                 Status bleibt im Header sichtbar, der Inhalt ist bereits
                 serverseitig gerendert (kein Nachladen per JS noetig).
            ==================================================== -->
            <section class="card card-dark text-light p-4 mt-4" id="geozonen" aria-labelledby="geozonenTitel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button class="btn btn-link text-light text-decoration-none p-0 d-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse" data-bs-target="#geozonenCollapse"
                            aria-expanded="false" aria-controls="geozonenCollapse">
                        <h2 class="h5 mb-0" id="geozonenTitel">
                            <i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>Darf ich hier fliegen?
                        </h2>
                        <i class="bi bi-chevron-down small wx-chevron" aria-hidden="true"></i>
                    </button>
                    <?php if (!$inDipulArea): ?>
                        <span class="badge text-bg-secondary">Außerhalb Deutschlands</span>
                    <?php elseif ($zonesChecked): ?>
                        <span class="badge <?= count($spotZones) ? $ampelStyle[$worstAmpel]['badge'] : 'text-bg-secondary' ?>">
                            <?= count($spotZones)
                                ? htmlspecialchars($ampelStyle[$worstAmpel]['wort'], ENT_QUOTES, 'UTF-8') . ' – ' . count($spotZones) . ' Zone(n)'
                                : 'Keine Zone gefunden' ?>
                        </span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Noch nicht geprüft</span>
                    <?php endif; ?>
                </div>

                <div class="collapse" id="geozonenCollapse">
                <div class="mt-3">

                <!-- Betriebsklasse: steuert Rechtstexte und Pflichtenliste -->
                <form method="POST" action="/api/save_pilot_class.php" id="pilotClassForm" class="mb-4">
                    <input type="hidden" name="redirect" value="/spot_detail.php?id=<?= $spotId ?>#geozonen">
                    <fieldset>
                        <legend class="float-none form-label small text-secondary mb-2">
                            Mein Copter – die Betriebsklasse bestimmt, welche Regeln gelten:
                        </legend>
                        <div class="btn-group flex-wrap" role="group" aria-label="Betriebsklasse wählen">
                            <?php foreach (PILOT_CLASSES as $pcKey => $pcMeta): ?>
                            <input type="radio" class="btn-check pilot-class-cb" name="pilot_class"
                                   id="detailPilotClass-<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>"
                                   value="<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>"
                                   autocomplete="off"<?= $pilotClass === $pcKey ? ' checked' : '' ?>>
                            <label class="btn btn-outline-light btn-sm text-start"
                                   for="detailPilotClass-<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="d-block"><?= htmlspecialchars($pcMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="d-block opacity-75 small"><?= htmlspecialchars($pcMeta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="small text-secondary mt-2 mb-0">
                            <?= htmlspecialchars(PILOT_CLASSES[$pilotClass]['eu'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <!-- Ohne JavaScript bleibt der Knopf sichtbar und die Seite
                             funktioniert per PRG genauso; mit JS blendet er sich aus
                             und das Formular schickt sich beim Umschalten selbst ab. -->
                        <button type="submit" class="btn btn-primary btn-sm mt-2" id="pilotClassSubmit">Übernehmen</button>
                    </fieldset>
                </form>

                <?php if (!$inDipulArea): ?>
                    <div class="alert alert-secondary py-2 px-3 small">
                        <i class="bi bi-globe-europe-africa"></i>
                        Dieser Spot liegt <strong>außerhalb Deutschlands</strong>. Die amtlichen
                        Geozonen der DFS decken nur das deutsche Hoheitsgebiet ab – wir können
                        hier <strong>keine Aussage treffen</strong>. Bitte die Drohnenkarte des
                        jeweiligen Landes prüfen; die unten genannten EU-Regeln
                        (Betriebskategorien, 120 m, Registrierung) gelten dagegen EU-weit.
                    </div>
                <?php elseif (!$zonesChecked): ?>
                    <div class="alert alert-secondary py-2 px-3 small">
                        <i class="bi bi-hourglass-split"></i>
                        Dieser Spot wurde noch nicht gegen die amtlichen Geozonen geprüft –
                        das ist <strong>keine Entwarnung</strong>. Bitte selbst auf
                        <a href="<?= htmlspecialchars(DIPUL_INFO_URL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="alert-link">dipul.de</a>
                        nachsehen.
                    </div>
                <?php elseif (!$spotZones): ?>
                    <div class="alert alert-secondary py-2 px-3 small">
                        <i class="bi bi-check2"></i>
                        Im Umkreis von <?= GEOZONE_CHECK_RADIUS_M ?> Metern liegt <strong>keine der geprüften Geozonen</strong>
                        (Stand <?= date('d.m.Y', strtotime((string)$spot['zones_checked_at'])) ?>).
                        Das heißt nicht automatisch, dass der Flug erlaubt ist: temporäre
                        Einschränkungen, NOTAM und örtliche Verbote sind hier nicht enthalten.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ($spotZones as $zone):
                            $style = $ampelStyle[$zone['ampel']] ?? $ampelStyle['info']; ?>
                        <li class="d-flex gap-3 mb-3">
                            <span class="badge <?= $style['badge'] ?> flex-shrink-0" style="height:fit-content">
                                <i class="bi <?= $style['icon'] ?>"></i> <?= htmlspecialchars($style['wort'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <!-- min-width:0 + text-break: ohne beides sprengen lange
                                 Zonennamen ("Universitätsklinikum …") die Flexbox auf dem Handy -->
                            <div class="text-break" style="min-width:0">
                                <strong><?= htmlspecialchars($zone['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($zone['zone_name'] !== ''): ?>
                                    <span class="text-secondary">– <?= htmlspecialchars($zone['zone_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <div class="small mt-1"><?= htmlspecialchars($zone['text'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-secondary mt-1">
                                    <i class="bi bi-journal-text"></i> <?= htmlspecialchars($zone['paragraf'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="small text-secondary">
                        Geprüft am <?= date('d.m.Y', strtotime((string)$spot['zones_checked_at'])) ?>
                        im Umkreis von <?= GEOZONE_CHECK_RADIUS_M ?> Metern um die Spot-Koordinate.
                    </p>
                <?php endif; ?>

                <?php if ($a3Hint !== null): ?>
                <!-- A3-Abstandsregel: steht in keiner amtlichen Karte, deshalb immer zeigen -->
                <div class="alert alert-warning py-2 px-3 small">
                    <strong><i class="bi bi-rulers"></i> <?= htmlspecialchars($a3Hint['label'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                    <?= htmlspecialchars($a3Hint['text'], ENT_QUOTES, 'UTF-8') ?><br>
                    <span class="text-secondary"><?= htmlspecialchars($a3Hint['paragraf'], ENT_QUOTES, 'UTF-8') ?></span><br>
                    <a href="/?spot=<?= $spotId ?>" class="alert-link">
                        <i class="bi bi-map"></i> Auf der Karte mit 150-Meter-Kreis ansehen
                    </a>
                </div>
                <?php endif; ?>

                <!-- Pflichten der gewaehlten Betriebsklasse -->
                <details class="mb-3">
                    <summary class="fw-semibold" style="cursor:pointer">
                        Deine Pflichten als <?= htmlspecialchars(PILOT_CLASSES[$pilotClass]['label'], ENT_QUOTES, 'UTF-8') ?>-Pilot
                    </summary>
                    <ul class="small mt-2 mb-0">
                        <?php foreach (PILOT_CHECKLIST[$pilotClass] as $duty): ?>
                        <li class="mb-1"><?= htmlspecialchars($duty, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>

                <!-- Haftungsausschluss bewusst direkt hier, nicht im Footer -->
                <p class="small text-secondary border-top border-secondary pt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    Diese Auswertung ist ein Hinweis, <strong>keine Rechtsberatung und keine Freigabe</strong>.
                    Maßgeblich ist allein die amtliche Karte auf
                    <a href="<?= htmlspecialchars(DIPUL_INFO_URL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-info">dipul.de</a>.
                    Temporäre Betriebseinschränkungen, NOTAM und örtliche Allgemeinverfügungen
                    sind hier nicht enthalten und ändern sich täglich.
                    <br><?= htmlspecialchars(DIPUL_ATTRIBUTION, ENT_QUOTES, 'UTF-8') ?>.
                </p>

                </div>
                </div>
            </section>

            <!-- ====================================================
                 Wetter am Spot

                 Bewusst immer zugeklappt: die Seite beantwortet
                 zuerst die rechtliche Frage, das Wetter ist die
                 Anschlussfrage. Der Inhalt wird erst beim ersten
                 Aufklappen per fetch geladen (siehe Skriptblock am
                 Seitenende), damit das Feature den Seitenaufbau
                 weder um einen Request noch um Renderzeit verteuert.

                 Hier wird nichts bewertet. Es gibt keine Ampel und
                 keine "gut/schlecht zum Fliegen"-Einstufung –
                 ausgegeben werden Zahlen und der Wetter-Klartext.
            ==================================================== -->
            <section class="card card-dark text-light p-4 mt-4" id="wetter" aria-labelledby="wetterTitel">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <button class="btn btn-link text-light text-decoration-none p-0 d-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse" data-bs-target="#wetterCollapse"
                            aria-expanded="false" aria-controls="wetterCollapse">
                        <h2 class="h5 mb-0" id="wetterTitel">
                            <i class="bi bi-cloud-sun me-2" aria-hidden="true"></i>Wetter am Spot
                        </h2>
                        <i class="bi bi-chevron-down small wx-chevron" aria-hidden="true"></i>
                    </button>
                    <span class="small text-secondary" id="wetterStand"></span>
                </div>

                <div class="collapse" id="wetterCollapse" data-spot-id="<?= (int)$spotId ?>">
                    <div class="mt-3" id="wetterBody" aria-live="polite" aria-busy="true">
                        <!-- Platzhalter bis zum ersten Ladevorgang; wird per JS ersetzt -->
                        <div class="wx-skeleton" style="height:5.5rem"></div>
                        <div class="wx-skeleton mt-2" style="height:5rem"></div>
                        <span class="visually-hidden">Wetterdaten werden geladen …</span>
                    </div>
                </div>
            </section>

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

                <!-- Ergebnis des letzten Uploads -->
                <div id="uploadReport">
                    <?php if ($uploadReport && (int)$uploadReport['saved'] > 0): ?>
                        <div class="alert alert-success py-2" role="status">
                            <?= (int)$uploadReport['saved'] === 1 ? '1 Foto hochgeladen.' : (int)$uploadReport['saved'] . ' Fotos hochgeladen.' ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($uploadReport && !empty($uploadReport['skipped'])): ?>
                        <div class="alert alert-warning py-2" role="alert" id="uploadSkippedAlert">
                            <strong>Nicht hochgeladen:</strong>
                            <ul class="mb-0 small" id="uploadSkippedList">
                                <?php foreach ($uploadReport['skipped'] as $sk): ?>
                                    <li><?= htmlspecialchars((string)($sk['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars((string)($sk['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_GET['photo_deleted'])): ?>
                    <div class="alert alert-success py-2" role="alert">Foto erfolgreich gelöscht.</div>
                <?php endif; ?>

                <?php if (!empty($_GET['photo_rotated'])): ?>
                    <div class="alert alert-success py-2" role="alert">Foto gedreht.</div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/upload_submit.php" enctype="multipart/form-data" class="mb-4"
                          id="photoUploadForm"
                          data-max-files="<?= (int)$uploadMaxFiles ?>"
                          data-max-size="<?= 10 * 1024 * 1024 ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <div class="d-flex align-items-center gap-2">
                            <label for="photoInput" class="visually-hidden">Fotos auswählen</label>
                            <input type="file" name="photos[]" id="photoInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple
                                   class="form-control form-control-sm bg-secondary text-light border-0" required>
                            <button type="submit" class="btn btn-success btn-sm text-nowrap" id="photoUploadBtn">Fotos hochladen</button>
                        </div>
                        <small class="text-secondary">
                            JPG/PNG, max. 10 MB pro Bild<?= $uploadMaxFiles > 0 ? ', bis zu ' . (int)$uploadMaxFiles . ' Bilder auf einmal' : '' ?>
                        </small>
                        <div id="photoSelectionInfo" class="small mt-2" aria-live="polite"></div>
                        <ul id="photoSelectionList" class="list-unstyled small mt-1 mb-0"></ul>
                        <div id="photoUploadProgress" class="mt-2 d-none">
                            <div class="progress" role="progressbar" aria-label="Upload-Fortschritt" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="progress-bar bg-success" style="width: 0%"></div>
                            </div>
                            <small class="text-secondary" id="photoUploadStatus" aria-live="polite"></small>
                        </div>
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
                        <?php foreach ($images as $img):
                            $imgFile  = '/public/uploads/imgs/' . basename((string)$img['filename']);
                            $imgSrc   = htmlspecialchars($imgFile, ENT_QUOTES, 'UTF-8');            // Original, fuer die Lightbox
                            $thumbSrc = htmlspecialchars(thumb_url($imgFile), ENT_QUOTES, 'UTF-8'); // Vorschau im Raster
                            $imgAlt = 'Foto von ' . htmlspecialchars($spot['name'], ENT_QUOTES, 'UTF-8') . ', hochgeladen von ' . htmlspecialchars($img['username'], ENT_QUOTES, 'UTF-8');
                            $canDeleteImg = $isLoggedIn && (
                                $userId === (int)($img['user_id'] ?? 0)
                                || $userId === (int)$spot['user_id']
                                || !empty($_SESSION['is_admin'])
                            );
                        ?>
                            <div class="col-6 col-md-4">
                                <button type="button"
                                        class="btn p-0 border-0 w-100"
                                        data-bs-toggle="modal"
                                        data-bs-target="#photoModal"
                                        data-img-src="<?= $imgSrc ?>"
                                        data-img-alt="<?= $imgAlt ?>"
                                        aria-label="Foto vergrößern">
                                    <img src="<?= $thumbSrc ?>"
                                         alt="<?= $imgAlt ?>"
                                         class="img-fluid rounded"
                                         loading="lazy"
                                         style="width:100%; height:160px; object-fit:cover; cursor:zoom-in;"
                                         onerror="this.closest('.col-6').style.display='none'">
                                </button>
                                <small class="text-secondary d-block mt-1">
                                    <?= htmlspecialchars($img['username']) ?> &bull; <?= $img['created_at'] ? date('d.m.Y', strtotime((string)$img['created_at'])) : 'Unbekannt' ?>
                                </small>
                                <?php if ($canDeleteImg): ?>
                                    <div class="d-flex gap-1 mt-1">
                                        <?php foreach (['left' => ['counterclockwise', 'Foto nach links drehen'], 'right' => ['clockwise', 'Foto nach rechts drehen']] as $dir => [$icon, $label]): ?>
                                            <form method="POST" action="/private/php/spots/photo_rotate_submit.php">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                                                <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                                <input type="hidden" name="direction" value="<?= $dir ?>">
                                                <input type="hidden" name="redirect" value="detail">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm"
                                                        title="<?= $label ?>" aria-label="<?= $label ?>">
                                                    <i class="bi bi-arrow-<?= $icon ?>"></i>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                        <form method="POST" action="/private/php/spots/photo_delete_submit.php"
                                              class="flex-grow-1"
                                              onsubmit="return confirm('Foto wirklich löschen?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                                            <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                            <input type="hidden" name="redirect" value="detail">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                                    title="Foto löschen">
                                                <i class="bi bi-trash3"></i> Löschen
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ====================================================
                 YouTube-Videos
            ==================================================== -->
            <div class="card card-dark text-light p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Videos</h2>
                    <span class="badge bg-primary"><?= count($videos) ?></span>
                </div>
                <?php if (empty($videos)): ?>
                    <p class="text-secondary mb-0">Noch keine Videos vorhanden.</p>
                <?php else: ?>
                    <div class="row g-3" id="videoGrid">
                        <?php foreach ($videos as $v):
                            $vid   = htmlspecialchars((string)$v['youtube_id'], ENT_QUOTES, 'UTF-8');
                            $title = htmlspecialchars((string)$v['title'],      ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="col-12 col-md-6">
                                <!-- Zwei-Klick-Loesung: Player wird erst bei Klick nachgeladen -->
                                <div class="yt-embed ratio ratio-16x9 rounded overflow-hidden"
                                     data-yt-id="<?= $vid ?>" data-yt-title="<?= $title ?>">
                                    <button type="button" class="yt-facade"
                                            aria-label="Video abspielen: <?= $title ?>">
                                        <img src="https://img.youtube.com/vi/<?= $vid ?>/mqdefault.jpg"
                                             alt="Vorschaubild: <?= $title ?>"
                                             loading="lazy"
                                             class="yt-thumb"
                                             onerror="this.style.visibility='hidden'">
                                        <span class="yt-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                                    </button>
                                </div>
                                <small class="text-light d-block mt-1 fw-semibold"><?= $title ?></small>
                                <a href="https://www.youtube.com/watch?v=<?= $vid ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="small text-secondary text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right"></i> Auf YouTube ansehen
                                </a>
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
                        $commentDate = $comment['created_at'] ? date('d.m.Y H:i', strtotime((string)$comment['created_at'])) : 'Unbekannt';
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

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

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
// Spot-Koordinaten in Zwischenablage kopieren
function copySpotCoords(btn) {
    const text = btn.dataset.lat + ', ' + btn.dataset.lng;
    const original = btn.innerHTML;
    const done = () => {
        btn.innerHTML = '<i class="bi bi-check2"></i> Kopiert!';
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(done).catch(() => { fallbackCopy(text); done(); });
    } else {
        fallbackCopy(text); done();
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

// ---------------------------------------------------------------
// Betriebsklassen-Umschalter
//
// Die Rechtstexte werden serverseitig gerendert, deshalb muss die
// Seite nach dem Wechsel neu laden. Mit JS uebernimmt das Formular
// das beim Anklicken selbst, der "Übernehmen"-Knopf entfaellt.
// ---------------------------------------------------------------
(function () {
    var form = document.getElementById('pilotClassForm');
    if (!form) return;
    var submitBtn = document.getElementById('pilotClassSubmit');
    if (submitBtn) submitBtn.classList.add('d-none');

    form.querySelectorAll('.pilot-class-cb').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) form.submit();
        });
    });
})();

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

// ============================================================
// YouTube-Einbettung (Zwei-Klick-Loesung)
// Beim Seitenaufruf wird nur das Vorschaubild geladen. Erst wenn
// der Nutzer auf den Play-Button klickt, wird der Player von
// youtube-nocookie.com nachgeladen und ersetzt das Vorschaubild.
// ============================================================
(function () {
    var grid = document.getElementById('videoGrid');
    if (!grid) return;

    var YT_ID_PATTERN = /^[A-Za-z0-9_-]{11}$/;

    grid.addEventListener('click', function (event) {
        var facade = event.target.closest('.yt-facade');
        if (!facade) return;

        var wrapper = facade.closest('.yt-embed');
        if (!wrapper) return;

        // Video-ID clientseitig erneut gegen Whitelist pruefen
        var videoId = wrapper.dataset.ytId || '';
        if (!YT_ID_PATTERN.test(videoId)) return;

        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0';
        iframe.title = wrapper.dataset.ytTitle || 'YouTube-Video';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allowFullscreen = true;
        iframe.setAttribute('frameborder', '0');

        wrapper.replaceChildren(iframe);
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

<!-- ============================================================
     Mehrfach-Foto-Upload

     Auswahl wird im Browser vorgeprüft (Typ, max. 10 MB, max. 10
     Dateien für Nicht-Admins). Gültige Bilder gehen einzeln
     nacheinander an upload_submit.php (ajax=1) – so greift das
     Gesamtlimit post_max_size nie, egal wie viele Bilder.
     Der Server prüft jede Datei erneut und sammelt das Ergebnis in
     der Session; nach dem letzten Bild lädt die Seite neu.
     Im Browser aussortierte Dateien werden per sessionStorage über
     den Reload gerettet. Dateinamen nur über textContent ausgeben.
============================================================ -->
<script>
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
</script>

<!-- ============================================================
     Foto-Popup-Modal (Lightbox)
============================================================ -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-modal="true" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h2 class="modal-title h5" id="photoModalLabel"><i class="bi bi-image me-2"></i>Foto</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImg" src="" alt="" class="img-fluid rounded mx-auto d-block">
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('photoModal');
    if (!modal) return;
    var imgEl = document.getElementById('photoModalImg');
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        imgEl.src = trigger.getAttribute('data-img-src') || '';
        imgEl.alt = trigger.getAttribute('data-img-alt') || '';
    });
    modal.addEventListener('hidden.bs.modal', function () {
        imgEl.src = '';
        imgEl.alt = '';
    });
})();
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

<!-- ============================================================
     Wetter: Lazy-Load beim ersten Aufklappen

     Der Bereich startet immer zugeklappt. Erst shown.bs.collapse
     loest den fetch aus, danach merkt sich data-loaded den Erfolg –
     wiederholtes Auf- und Zuklappen erzeugt keinen weiteren Request.
     Fuer dieses Muster gab es im Projekt bisher kein Vorbild.

     Alle Fremdwerte gehen entweder durch Number() oder durch esc();
     Icon-Klassen zusaetzlich durch eine Formatpruefung. Kein rohes
     innerHTML mit ungepruefter Antwort – dieselbe Regel wie map.js.

     Bewusst ohne Bewertung: keine Ampel, keine Schwellwerte, keine
     Aussage darueber, ob sich ein Flug lohnt. Nur Zahlen und der
     amtliche Wetter-Klartext.
============================================================ -->
<script>
(function () {
    var box = document.getElementById('wetterCollapse');
    if (!box) return;

    var body  = document.getElementById('wetterBody');
    var stand = document.getElementById('wetterStand');
    var laeuft = false;   // schuetzt vor schnellem Auf/Zu/Auf

    // ── Hilfsfunktionen ──────────────────────────────────────

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str === null || str === undefined ? '' : str));
        return d.innerHTML;
    }

    // Icon-Klassen kommen aus einer festen Tabelle auf dem Server.
    // Die Pruefung ist zweite Verteidigungslinie, kein Ersatz dafuer.
    function icon(name) {
        return /^bi-[a-z0-9-]+$/.test(name || '') ? name : 'bi-cloud';
    }

    // Zahl mit deutschem Dezimalkomma; fehlende Werte als Gedankenstrich.
    function num(wert, einheit, stellen) {
        if (wert === null || wert === undefined || isNaN(wert)) return '–';
        var s = Number(wert).toLocaleString('de-DE', {
            minimumFractionDigits: stellen || 0,
            maximumFractionDigits: stellen || 0
        });
        if (!einheit) return s;
        // Nach DIN 5008 steht vor einer Einheit ein Leerzeichen ("14,8 °C").
        // Das blosse Gradzeichen als Kurzform in den Streifen bildet die
        // Ausnahme – dort waere "15 °" typografisch falsch getrennt.
        return einheit === '\u00b0' ? s + einheit : s + ' ' + einheit;
    }

    // Windpfeil: Open-Meteo nennt die Richtung, AUS DER der Wind weht.
    // Fuer einen Pfeil in Bewegungsrichtung sind das +180 Grad.
    function pfeil(grad, kuerzel) {
        if (grad === null || grad === undefined || isNaN(grad)) return '';
        return '<i class="bi bi-arrow-up wx-arrow" aria-hidden="true" style="transform:rotate('
             + (Number(grad) + 180) % 360 + 'deg)"></i> ' + esc(kuerzel || '');
    }

    function kachel(bi, titel, inhalt) {
        return '<div class="col-6 col-md-4"><div class="wx-tile h-100">'
             + '<div class="wx-tile-label"><i class="bi ' + icon(bi) + ' me-1" aria-hidden="true"></i>'
             + esc(titel) + '</div>'
             + '<div class="wx-tile-value">' + inhalt + '</div>'
             + '</div></div>';
    }

    // ── Rendern ──────────────────────────────────────────────

    function render(d) {
        var a = d.aktuell || {};
        var h = '';

        // Aktueller Zustand
        h += '<div class="d-flex align-items-center gap-3 mb-3">'
           +   '<i class="bi ' + icon(a.icon) + ' wx-now-icon" aria-hidden="true"></i>'
           +   '<div>'
           +     '<div class="wx-now-temp">' + num(a.temp, '°C', 1) + '</div>'
           +     '<div class="text-secondary small">' + esc(a.text)
           +       ' &middot; gefühlt ' + num(a.gefuehlt, '°C', 1) + '</div>'
           +   '</div>'
           + '</div>';

        // Messwert-Kacheln
        h += '<div class="row g-2 mb-3">'
           + kachel('bi-wind',            'Wind',       num(a.wind, 'km/h') + ' ' + pfeil(a.richtung, a.richtung_text))
           + kachel('bi-wind',            'Böen',       num(a.boeen, 'km/h'))
           + kachel('bi-umbrella',        'Niederschlag', num(a.niederschlag, 'mm', 1))
           + kachel('bi-clouds',          'Bewölkung',  num(a.bewoelkung, '%'))
           + kachel('bi-eye',             'Sicht',      num(a.sicht_km, 'km', 1))
           + kachel('bi-droplet-half',    'Luftfeuchte', num(a.feuchte, '%'))
           + '</div>';

        // Sonnenzeiten
        var s = d.sonne || {};
        if (s.aufgang || s.untergang) {
            h += '<p class="small text-secondary mb-2">'
               + '<i class="bi bi-sunrise me-1" aria-hidden="true"></i>' + esc(s.aufgang) + ' Uhr'
               + '<span class="mx-2">&middot;</span>'
               + '<i class="bi bi-sunset me-1" aria-hidden="true"></i>' + esc(s.untergang) + ' Uhr'
               + '</p>';
        }

        // Kp-Index – entfaellt still, wenn die NOAA nicht erreichbar war
        if (d.kp) {
            h += '<p class="small text-secondary mb-3">'
               + '<i class="bi bi-activity me-1" aria-hidden="true"></i>'
               + 'Kp-Index ' + num(d.kp.wert, '', 1) + ' (' + esc(d.kp.text) + ') '
               + '<span class="text-secondary">– geomagnetische Aktivität, '
               + 'hohe Werte können die GPS-Genauigkeit verringern.</span>'
               + '</p>';
        }

        // Naechste 24 Stunden – horizontal scrollbarer Streifen
        if (d.stunden && d.stunden.length) {
            h += '<h3 class="h6 mt-4 mb-2">Nächste 24 Stunden</h3><div class="wx-hours">';
            d.stunden.forEach(function (x) {
                h += '<div class="wx-hour">'
                   +   '<div class="wx-hour-time">' + esc(x.zeit) + '</div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-hour-temp">' + num(x.temp, '°') + '</div>'
                   +   '<div class="wx-hour-wind">' + num(x.wind) + '<span class="text-secondary">/</span>'
                   +     num(x.boeen) + '</div>'
                   +   '<div class="wx-hour-dir">' + esc(x.richtung_text || '') + '</div>'
                   +   '<div class="wx-hour-rain">' + num(x.regen_prozent, '%') + '</div>'
                   + '</div>';
            });
            h += '</div><p class="wx-legend">Zeit &middot; Wetter &middot; Temperatur &middot; '
               + 'Wind/Böen in km/h &middot; Richtung &middot; Regenwahrscheinlichkeit</p>';
        }

        // 7-Tage-Uebersicht
        if (d.tage && d.tage.length) {
            h += '<h3 class="h6 mt-4 mb-2">7 Tage</h3><div class="wx-days">';
            d.tage.forEach(function (x) {
                h += '<div class="wx-day">'
                   +   '<div class="wx-day-name">' + (x.heute ? 'Heute' : esc(x.wochentag))
                   +     '<span class="wx-day-date">' + esc(x.datum) + '</span></div>'
                   +   '<i class="bi ' + icon(x.icon) + '" title="' + esc(x.text) + '"></i>'
                   +   '<div class="wx-day-temp">' + num(x.tmax, '°') + '<span class="text-secondary"> / '
                   +     num(x.tmin, '°') + '</span></div>'
                   +   '<div class="wx-day-wind"><i class="bi bi-wind me-1" aria-hidden="true"></i>'
                   +     num(x.boeen, 'km/h') + '</div>'
                   +   '<div class="wx-day-rain"><i class="bi bi-umbrella me-1" aria-hidden="true"></i>'
                   +     num(x.regen_prozent, '%') + '</div>'
                   + '</div>';
            });
            h += '</div><p class="wx-legend">Wetter &middot; Höchst-/Tiefsttemperatur &middot; '
               + 'stärkste Böen &middot; Regenwahrscheinlichkeit</p>';
        }

        // Quellenangabe – Open-Meteo steht unter CC BY 4.0 und verlangt sie
        h += '<p class="small text-secondary border-top border-secondary pt-3 mt-4 mb-0">'
           + '<i class="bi bi-info-circle me-1" aria-hidden="true"></i>'
           + 'Prognosen sind Modellrechnungen und keine Zusage. Ob ein Flug möglich und '
           + 'zulässig ist, entscheidest du vor Ort.'
           + '<br>Wetterdaten: <a href="https://open-meteo.com/" target="_blank" rel="noopener" '
           + 'class="text-info">Open-Meteo.com</a> (CC BY 4.0), für Deutschland auf Basis von DWD ICON. '
           + 'Kp-Index: <a href="https://www.swpc.noaa.gov/" target="_blank" rel="noopener" '
           + 'class="text-info">NOAA SWPC</a>.'
           + '</p>';

        body.innerHTML = h;

        if (stand && d.stand_text) {
            stand.textContent = 'Stand: ' + d.stand_text;
        }
    }

    function renderFehler(meldung) {
        body.innerHTML =
            '<div class="alert alert-secondary mb-0">'
          +   '<p class="mb-2"><i class="bi bi-cloud-slash me-1" aria-hidden="true"></i>' + esc(meldung) + '</p>'
          +   '<button type="button" class="btn btn-outline-secondary btn-sm" id="wetterRetry">'
          +     'Erneut versuchen</button>'
          + '</div>';
        if (stand) stand.textContent = '';

        var btn = document.getElementById('wetterRetry');
        if (btn) btn.addEventListener('click', function () {
            body.innerHTML = '<div class="wx-skeleton" style="height:5.5rem"></div>'
                           + '<div class="wx-skeleton mt-2" style="height:5rem"></div>';
            laden();
        });
    }

    // ── Laden ────────────────────────────────────────────────

    function laden() {
        if (box.dataset.loaded === '1' || laeuft) return;
        laeuft = true;
        body.setAttribute('aria-busy', 'true');

        fetch('/api/weather.php?spot_id=' + encodeURIComponent(box.dataset.spotId), {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) {
            return res.json().then(function (daten) {
                if (!res.ok) throw new Error(daten && daten.error ? daten.error : 'HTTP ' + res.status);
                return daten;
            });
        })
        .then(function (daten) {
            render(daten);
            box.dataset.loaded = '1';
        })
        .catch(function (e) {
            renderFehler(e && e.message ? e.message : 'Wetterdaten konnten nicht geladen werden.');
        })
        .finally(function () {
            laeuft = false;
            body.setAttribute('aria-busy', 'false');
        });
    }

    box.addEventListener('shown.bs.collapse', laden);
})();
</script>

</body>
</html>
