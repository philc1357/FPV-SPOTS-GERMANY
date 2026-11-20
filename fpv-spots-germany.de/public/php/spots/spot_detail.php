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
require_once __DIR__ . '/../../../private/php/core/avatar.php';
require_once __DIR__ . '/../../../private/php/core/affiliate.php';
require_once __DIR__ . '/../../../private/php/spots/youtube_helper.php'; // SPOT_VIDEOS_MAX

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

// Verteilung 5..1 Sterne fuer die Balken in der Bewertungskarte
$ratingDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($ratingCount > 0) {
    $stmt = $pdo->prepare("SELECT stars, COUNT(*) AS n FROM ratings WHERE spot_id = ? GROUP BY stars");
    $stmt->execute([$spotId]);
    foreach ($stmt->fetchAll() as $row) {
        $s = (int)$row['stars'];
        if (isset($ratingDist[$s])) {
            $ratingDist[$s] = (int)$row['n'];
        }
    }
}
$ratingDistMax = max(1, max($ratingDist));

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

// YouTube-Videos laden (inkl. wer sie hinzugefügt hat)
$stmt = $pdo->prepare(
    "SELECT v.id, v.user_id, v.title, v.youtube_id, u.username
     FROM spot_videos v
     JOIN users u ON v.user_id = u.id
     WHERE v.spot_id = ?
     ORDER BY v.position ASC, v.id ASC"
);
$stmt->execute([$spotId]);
$videos = $stmt->fetchAll();

// Rückmeldung vom Hinzufügen/Entfernen – nur für diesen Spot, einmalig anzeigen
$videoFlash = null;
if (is_array($_SESSION['video_flash'] ?? null)) {
    if ((int)($_SESSION['video_flash']['spot_id'] ?? 0) === $spotId) {
        $videoFlash = $_SESSION['video_flash'];
    }
    unset($_SESSION['video_flash']);
}

// Kommentare laden
$stmt = $pdo->prepare(
    "SELECT c.id, c.user_id, c.body, c.created_at, u.username, u.avatar
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
$diffColor = $diffColors[$spot['difficulty']] ?? 'secondary';
$createdDate = $spot['created_at'] ? date('d.m.Y', strtotime((string)$spot['created_at'])) : 'Unbekannt';

// -------------------------------------------------------------
// Anzeige-Helfer fuer das neue Layout
// -------------------------------------------------------------
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$hasCoords  = $spot['latitude'] !== null && $spot['longitude'] !== null;
$isAdmin    = !empty($_SESSION['is_admin']);
$canManage  = $isLoggedIn && ($userId === (int)$spot['user_id'] || $isAdmin);
$avgText    = $avgStars !== null ? number_format($avgStars, 1, ',', '') : null;
$photoCount = count($images);
$heroImages = array_slice($images, 0, 3);   // Mosaik: neueste drei Fotos
$parkingKnown = ($spot['parking_info'] ?: 'Unbekannt') !== 'Unbekannt';

// Flugstatus in Alltagssprache – Grundlage fuer Hero-Kachel und
// Urteilsbox. Bewusst nie "erlaubt": die beste Aussage bleibt
// "keine Zone gefunden".
$zoneCount = count($spotZones);
$zoneWord  = $zoneCount === 1 ? '1 Zone' : $zoneCount . ' Zonen';
if (!$inDipulArea) {
    $flight = ['tone' => 'neutral', 'icon' => 'bi-globe-europe-africa', 'short' => 'Außerhalb DE',
               'title' => 'Außerhalb Deutschlands',
               'text'  => 'Für Spots im Ausland haben wir keine amtlichen Geozonen. Bitte prüfe die Drohnenkarte des jeweiligen Landes.'];
} elseif (!$zonesChecked) {
    $flight = ['tone' => 'neutral', 'icon' => 'bi-hourglass-split', 'short' => 'Noch nicht geprüft',
               'title' => 'Noch nicht geprüft',
               'text'  => 'Dieser Spot wurde noch nicht mit den amtlichen Geozonen abgeglichen – das ist keine Entwarnung. Schau bitte selbst auf dipul.de nach.'];
} elseif ($zoneCount === 0) {
    $flight = ['tone' => 'clear', 'icon' => 'bi-check2-circle', 'short' => 'Keine Zone gefunden',
               'title' => 'Keine Geozone in der Nähe',
               'text'  => 'Im Umkreis von ' . GEOZONE_CHECK_RADIUS_M . ' m liegt keine der geprüften Zonen. Temporäre Sperren und örtliche Verbote sind darin nicht enthalten.'];
} elseif ($worstAmpel === 'rot') {
    $flight = ['tone' => 'rot', 'icon' => 'bi-x-octagon-fill', 'short' => 'Verboten · ' . $zoneWord,
               'title' => 'Hier gilt für dich ein Flugverbot',
               'text'  => 'Im Umkreis von ' . GEOZONE_CHECK_RADIUS_M . ' m liegt mindestens eine Zone, in der du mit deiner Copter-Klasse nicht fliegen darfst.'];
} elseif ($worstAmpel === 'gelb') {
    $flight = ['tone' => 'gelb', 'icon' => 'bi-exclamation-triangle-fill', 'short' => 'Eingeschränkt · ' . $zoneWord,
               'title' => 'Fliegen nur mit Auflagen',
               'text'  => 'Im Umkreis von ' . GEOZONE_CHECK_RADIUS_M . ' m gelten für deine Copter-Klasse Einschränkungen. Was genau, steht unten.'];
} else {
    $flight = ['tone' => 'info', 'icon' => 'bi-info-circle-fill', 'short' => 'Hinweise · ' . $zoneWord,
               'title' => 'Gut zu wissen',
               'text'  => 'In der Nähe liegen Zonen, die für deine Copter-Klasse keine Einschränkung bedeuten – du solltest sie aber kennen.'];
}

// Rechtsgrundlagen gesammelt statt an jeder Zone (weniger Paragrafen im Blick)
$legalRefs = [];
foreach ($spotZones as $z) {
    if (($z['paragraf'] ?? '') !== '') {
        $legalRefs[$z['label'] . ': ' . $z['paragraf']] = true;
    }
}
if ($a3Hint !== null && ($a3Hint['paragraf'] ?? '') !== '') {
    $legalRefs[$a3Hint['label'] . ': ' . $a3Hint['paragraf']] = true;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($spot['name']) ?> – FPV Spots Germany</title>
    <meta name="description" content="<?= $e(mb_substr($spot['description'] ?: $spot['name'] . ' – FPV Spot in Deutschland', 0, 155)) ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?= $e($spot['name']) ?> – FPV Spots Germany">
    <meta property="og:description" content="<?= $e(mb_substr($spot['description'] ?: 'FPV Spot in Deutschland', 0, 155)) ?>">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="de_DE">
    <link rel="canonical" href="https://fpv-spots-germany.de/spot_detail.php?id=<?= $spotId ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    <link rel="stylesheet" href="<?= asset('/public/css/spot_detail.css') ?>">
</head>
<body class="page-spot">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<!-- --sd-type: Farbe des Spot-Typs, faerbt Lichtschein im Hero und Typ-Punkt -->
<main class="sd" data-bs-theme="dark" style="--sd-type: <?= $e($typeColor) ?>">

    <!-- ====================================================
         Brotkrumen + Hinweis auf nutzergenerierte Inhalte
    ==================================================== -->
    <div class="sd-topline">
        <nav aria-label="Brotkrumen">
            <ol class="sd-crumbs">
                <li><a href="/">Karte</a></li>
                <li aria-current="page"><?= $e($spot['name']) ?></li>
            </ol>
        </nav>
        <p class="sd-note">
            <i class="bi bi-people" aria-hidden="true"></i>
            Von der Community eingetragen – vor dem Besuch Betretungs-, Luft- und Naturschutzrecht prüfen.
            <a href="/nutzungsbedingungen.php">Mehr erfahren</a>
        </p>
    </div>

    <?php if (!empty($_GET['reported'])): ?>
        <div class="alert alert-success alert-dismissible fade show sd-alert" role="alert">
            <i class="bi bi-check-circle me-2"></i>Deine Meldung wurde erfolgreich übermittelt. Vielen Dank!
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Schließen"></button>
        </div>
    <?php endif; ?>

    <!-- ====================================================
         Hero: Titel, Tags, Aktionen, Foto-Mosaik, Schnell-Infos
    ==================================================== -->
    <section class="sd-hero" aria-labelledby="spotTitel">
        <div class="sd-hero-head">
            <div class="sd-hero-text">
                <p class="sd-eyebrow">
                    <span class="sd-type-dot" aria-hidden="true"></span>
                    <?= $e($spot['spot_type'] ?: 'FPV') ?>-Spot · eingetragen am <?= $e($createdDate) ?>
                </p>
                <h1 id="spotTitel" class="sd-title"><?= $e($spot['name']) ?></h1>
                <div class="fsg-tags">
                    <span class="fsg-tag fsg-tag--diff-<?= $e($diffColor) ?>">
                        <i class="bi bi-speedometer2" aria-hidden="true"></i><?= $e($spot['difficulty'] ?: 'Schwierigkeit unbekannt') ?>
                    </span>
                    <?php if (!empty($spot['copter_size'])): ?>
                        <?php foreach (explode(',', $spot['copter_size']) as $cs): ?>
                            <?php if (trim($cs) === '') continue; ?>
                            <span class="fsg-tag"><?= $e(trim($cs)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="#bewertung" class="sd-rating-inline">
                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                        <?php if ($avgText !== null): ?>
                            <strong><?= $e($avgText) ?></strong>
                            <span>(<?= $ratingCount ?> <?= $ratingCount === 1 ? 'Bewertung' : 'Bewertungen' ?>)</span>
                        <?php else: ?>
                            <span>Noch nicht bewertet</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="sd-actions">
                <a href="/?spot=<?= $spotId ?>" class="fsg-btn fsg-btn--primary">
                    <i class="bi bi-map" aria-hidden="true"></i>Auf der Karte
                </a>
                <?php if ($hasCoords): ?>
                    <a class="fsg-btn"
                       href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode(number_format((float)$spot['latitude'], 6, '.', '') . ',' . number_format((float)$spot['longitude'], 6, '.', '')) ?>"
                       target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-signpost-split" aria-hidden="true"></i>Route
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/favorite_submit.php" class="d-inline-flex">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <input type="hidden" name="redirect" value="/spot_detail.php?id=<?= $spotId ?>">
                        <button type="submit" class="fsg-btn fsg-icon-btn<?= $isFavorite ? ' is-active' : '' ?>"
                                aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
                                title="<?= $isFavorite ? 'Aus Favoriten entfernen' : 'Als Favorit speichern' ?>"
                                aria-label="<?= $isFavorite ? 'Aus Favoriten entfernen' : 'Als Favorit speichern' ?>">
                            <i class="bi bi-heart<?= $isFavorite ? '-fill' : '' ?>" aria-hidden="true"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" class="fsg-btn fsg-icon-btn" data-bs-toggle="modal" data-bs-target="#loginModal"
                            title="Einloggen, um Favoriten zu speichern" aria-label="Einloggen, um Favoriten zu speichern">
                        <i class="bi bi-heart" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>

                <button type="button" class="fsg-btn fsg-icon-btn" data-action="copy-spot-link"
                        title="Link kopieren" aria-label="Link zum Spot kopieren">
                    <i class="bi bi-share" aria-hidden="true"></i>
                </button>

                <div class="dropdown">
                    <button class="fsg-btn fsg-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Weitere Aktionen" aria-label="Weitere Aktionen">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark fsg-menu">
                        <?php if ($hasCoords): ?>
                            <li>
                                <button class="dropdown-item" data-action="copy-spot-coords"
                                        data-lat="<?= $e(number_format((float)$spot['latitude'], 6, '.', '')) ?>"
                                        data-lng="<?= $e(number_format((float)$spot['longitude'], 6, '.', '')) ?>">
                                    <i class="bi bi-crosshair me-2"></i>Koordinaten kopieren
                                </button>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                   href="https://www.google.com/maps/search/?api=1&query=<?= urlencode(number_format((float)$spot['latitude'], 6, '.', '') . ',' . number_format((float)$spot['longitude'], 6, '.', '')) ?>"
                                   target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-google me-2"></i>In Google Maps öffnen
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($isLoggedIn): ?>
                            <li>
                                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reportModal">
                                    <i class="bi bi-flag me-2"></i>Spot melden
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php if ($canManage): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/edit_spot.php?id=<?= $spotId ?>">
                                    <i class="bi bi-pencil me-2"></i>Spot bearbeiten
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="/private/php/spots/delete_spot_submit.php"
                                      data-confirm="Spot wirklich löschen? Alle Kommentare, Bewertungen und Fotos werden ebenfalls gelöscht.">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
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

        <?php if ($heroImages): ?>
            <!-- Foto-Mosaik aus den Thumbnails (neueste zuerst). Das erste
                 Bild ist LCP-Kandidat: kein lazy, hohe Prioritaet. -->
            <div class="sd-mosaic sd-mosaic--<?= count($heroImages) ?>">
                <?php foreach ($heroImages as $i => $img):
                    $imgFile = '/public/uploads/imgs/' . basename((string)$img['filename']); ?>
                    <button type="button" class="sd-mosaic-tile"
                            data-bs-toggle="modal" data-bs-target="#photoModal"
                            data-img-src="<?= $e($imgFile) ?>"
                            data-img-alt="<?= $e('Foto von ' . $spot['name'] . ', hochgeladen von ' . $img['username']) ?>"
                            aria-label="Foto vergrößern">
                        <img src="<?= $e(thumb_url($imgFile)) ?>"
                             alt="<?= $e('Foto von ' . $spot['name'] . ', hochgeladen von ' . $img['username']) ?>"
                             <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                             decoding="async">
                    </button>
                <?php endforeach; ?>
                <a href="#fotos" class="sd-mosaic-more">
                    <i class="bi bi-images" aria-hidden="true"></i>
                    <?= $photoCount === 1 ? '1 Foto' : 'Alle ' . $photoCount . ' Fotos' ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- Schnell-Infos: ersetzt die Koordinaten-Leiste der Vorlage -->
        <ul class="sd-facts">
            <li>
                <a href="#geozonen" class="sd-fact sd-fact--<?= $e($flight['tone']) ?>">
                    <span class="sd-fact-icon"><i class="bi <?= $e($flight['icon']) ?>" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Flugstatus</span>
                        <span class="sd-fact-value"><?= $e($flight['short']) ?></span>
                    </span>
                </a>
            </li>
            <li>
                <a href="#beschreibung" class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-p-circle" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Parken</span>
                        <span class="sd-fact-value"><?= $parkingKnown ? 'Infos vorhanden' : 'Unbekannt' ?></span>
                    </span>
                </a>
            </li>
            <li>
                <a href="#fotos" class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-camera" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Fotos &amp; Videos</span>
                        <span class="sd-fact-value"><?= $photoCount ?> Fotos · <?= count($videos) ?> Videos</span>
                    </span>
                </a>
            </li>
            <li>
                <a href="/profile.php?id=<?= (int)$spot['user_id'] ?>" class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Erstellt von</span>
                        <span class="sd-fact-value"><?= $e($spot['username']) ?></span>
                    </span>
                </a>
            </li>
        </ul>
    </section>

    <!-- ====================================================
         Zweispaltig: Inhalt links, Bewertung/Ausrüstung rechts
    ==================================================== -->
    <div class="sd-layout">
        <div class="sd-main">

            <!-- ================================================
                 Beschreibung + Parkmöglichkeit
            ================================================ -->
            <section class="fsg-card" id="beschreibung" aria-labelledby="beschreibungTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker">Über den Spot</p>
                        <h2 id="beschreibungTitel" class="fsg-h2">Beschreibung</h2>
                    </div>
                </header>
                <p class="fsg-lead"><?= nl2br($e($spot['description'] ?: 'Für diesen Spot gibt es noch keine Beschreibung.')) ?></p>

                <div class="sd-parking">
                    <span class="sd-parking-icon" aria-hidden="true"><i class="bi bi-p-square"></i></span>
                    <div class="sd-parking-body">
                        <h3 class="fsg-h3">Parkmöglichkeit</h3>
                        <div id="parking-display">
                            <p class="mb-0"><?= nl2br($e($spot['parking_info'] ?: 'Unbekannt')) ?></p>
                            <?php if ($spot['parking_editor'] && $spot['parking_updated_at']): ?>
                                <p class="fsg-meta mb-0">
                                    Zuletzt ergänzt von <?= $e($spot['parking_editor']) ?> am <?= date('d.m.Y', strtotime((string)$spot['parking_updated_at'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($isLoggedIn): ?>
                        <div id="parking-edit" class="d-none mt-2">
                            <form method="POST" action="/private/php/spots/parking_info_submit.php">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="detailParkingUnknown"
                                           <?= $parkingKnown ? '' : 'checked' ?>>
                                    <label class="form-check-label" for="detailParkingUnknown">Unbekannt</label>
                                </div>
                                <label for="detailParkingInfo" class="visually-hidden">Parkmöglichkeit</label>
                                <textarea name="parking_info" id="detailParkingInfo"
                                          class="fsg-field mb-2"
                                          placeholder="z. B. Kostenloser Parkplatz direkt am Feld …"
                                          rows="2" maxlength="500"
                                          <?= $parkingKnown ? '' : 'disabled' ?>><?= $parkingKnown ? $e($spot['parking_info']) : '' ?></textarea>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="fsg-btn fsg-btn--primary fsg-btn--sm">Speichern</button>
                                    <button type="button" class="fsg-btn fsg-btn--sm" data-action="toggle-edit-parking">Abbrechen</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isLoggedIn): ?>
                        <button type="button" class="fsg-btn fsg-btn--sm fsg-btn--ghost"
                                data-action="toggle-edit-parking" aria-controls="parking-edit">
                            <i class="bi bi-pencil" aria-hidden="true"></i>Ergänzen
                        </button>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ================================================
                 Darf ich hier fliegen? – DIPUL-Geozonen

                 Reihenfolge: klare Aussage zuerst (Urteilsbox),
                 dann die Copter-Klasse, dann die Zonen in
                 Alltagssprache. Paragrafen, Pflichten und
                 Haftungsausschluss stehen gesammelt darunter.
                 Es gibt weiterhin keine gruene Freigabe.

                 Standardmaessig eingeklappt (<details>). Der
                 Flugstatus steht kurz in den Schnell-Infos oben;
                 der Anker #geozonen klappt auf (spot_detail.js).
            ================================================ -->
            <section class="fsg-card fsg-card--collapsible" id="geozonen" aria-labelledby="geozonenTitel">
                <p class="fsg-kicker">Vor dem Abheben</p>
                <details class="fsg-collapse">
                <summary><h2 id="geozonenTitel" class="fsg-h2">Darf ich hier fliegen?</h2></summary>
                <div class="fsg-collapse-body">

                <div class="sd-verdict sd-verdict--<?= $e($flight['tone']) ?>" role="status">
                    <i class="bi <?= $e($flight['icon']) ?> sd-verdict-icon" aria-hidden="true"></i>
                    <div>
                        <p class="sd-verdict-title"><?= $e($flight['title']) ?></p>
                        <p class="sd-verdict-text">
                            <?= $e($flight['text']) ?>
                            <?php if ($inDipulArea && !$zonesChecked): ?>
                                <a href="<?= $e(DIPUL_INFO_URL) ?>" target="_blank" rel="noopener">Zu dipul.de</a>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Copter-Klasse: steuert Bewertung, Texte und Pflichten -->
                <form method="POST" action="/api/save_pilot_class.php" id="pilotClassForm">
                    <input type="hidden" name="redirect" value="/spot_detail.php?id=<?= $spotId ?>#geozonen">
                    <fieldset>
                        <legend class="fsg-label">Womit fliegst du?</legend>
                        <div class="fsg-pills">
                            <?php foreach (PILOT_CLASSES as $pcKey => $pcMeta): ?>
                                <input type="radio" class="btn-check pilot-class-cb" name="pilot_class"
                                       id="detailPilotClass-<?= $e($pcKey) ?>"
                                       value="<?= $e($pcKey) ?>"
                                       autocomplete="off"<?= $pilotClass === $pcKey ? ' checked' : '' ?>>
                                <label class="fsg-pill" for="detailPilotClass-<?= $e($pcKey) ?>">
                                    <span class="fsg-pill-title"><?= $e($pcMeta['label']) ?></span>
                                    <span class="fsg-pill-hint"><?= $e($pcMeta['hint']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!-- Ohne JavaScript bleibt der Knopf sichtbar (PRG);
                             mit JS schickt sich das Formular beim Umschalten selbst ab. -->
                        <button type="submit" class="fsg-btn fsg-btn--sm mt-2" id="pilotClassSubmit">Übernehmen</button>
                    </fieldset>
                </form>

                <?php if ($spotZones): ?>
                    <ul class="sd-zones">
                        <?php foreach ($spotZones as $zone):
                            $style = $ampelStyle[$zone['ampel']] ?? $ampelStyle['info'];
                            $tone  = isset($ampelStyle[$zone['ampel']]) ? $zone['ampel'] : 'info'; ?>
                            <li class="sd-zone sd-zone--<?= $e($tone) ?>">
                                <i class="bi <?= $e($style['icon']) ?> sd-zone-icon" aria-hidden="true"></i>
                                <div class="sd-zone-body">
                                    <p class="sd-zone-title">
                                        <?= $e($zone['label']) ?>
                                        <?php if ($zone['zone_name'] !== ''): ?>
                                            <span class="sd-zone-name"><?= $e($zone['zone_name']) ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="sd-zone-text"><?= $e($zone['text']) ?></p>
                                </div>
                                <span class="sd-zone-badge"><?= $e($style['wort']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($zonesChecked): ?>
                    <p class="fsg-meta mb-0">
                        Abgeglichen mit den amtlichen Geozonen am <?= date('d.m.Y', strtotime((string)$spot['zones_checked_at'])) ?>,
                        Umkreis <?= GEOZONE_CHECK_RADIUS_M ?> m.
                    </p>
                <?php endif; ?>

                <?php if ($a3Hint !== null): ?>
                    <!-- A3-Abstandsregel: steht in keiner amtlichen Karte, deshalb immer zeigen -->
                    <div class="sd-callout">
                        <i class="bi bi-rulers" aria-hidden="true"></i>
                        <div>
                            <p class="sd-callout-title"><?= $e($a3Hint['label']) ?></p>
                            <p class="mb-0"><?= $e($a3Hint['text']) ?></p>
                        </div>
                        <a href="/?spot=<?= $spotId ?>" class="fsg-btn fsg-btn--sm">
                            <i class="bi bi-bullseye" aria-hidden="true"></i>150-m-Kreis zeigen
                        </a>
                    </div>
                <?php endif; ?>

                <div class="sd-disclosures">
                    <details class="fsg-details">
                        <summary>Deine Pflichten als Pilot (<?= $e(PILOT_CLASSES[$pilotClass]['label']) ?>)</summary>
                        <p class="fsg-meta"><?= $e(PILOT_CLASSES[$pilotClass]['eu']) ?></p>
                        <ul>
                            <?php foreach (PILOT_CHECKLIST[$pilotClass] as $duty): ?>
                                <li><?= $e($duty) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php if ($legalRefs): ?>
                        <details class="fsg-details">
                            <summary>Rechtsgrundlagen</summary>
                            <ul>
                                <?php foreach (array_keys($legalRefs) as $ref): ?>
                                    <li><?= $e($ref) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>

                <!-- Haftungsausschluss bewusst direkt hier, nicht im Footer -->
                <p class="fsg-fineprint">
                    Diese Auswertung ist ein Hinweis, <strong>keine Rechtsberatung und keine Freigabe</strong>.
                    Maßgeblich ist allein die amtliche Karte auf
                    <a href="<?= $e(DIPUL_INFO_URL) ?>" target="_blank" rel="noopener">dipul.de</a>.
                    Temporäre Sperren, NOTAM und örtliche Allgemeinverfügungen sind nicht enthalten.
                    <?= $e(DIPUL_ATTRIBUTION) ?>.
                </p>
                </div><!-- /.fsg-collapse-body -->
                </details>
            </section>

            <!-- ================================================
                 Wetter am Spot

                 Standardmaessig eingeklappt (<details>). Die Daten
                 werden erst beim ersten Aufklappen geladen (siehe
                 spot_detail_weather.js) – kein Request beim
                 Seitenaufbau. Nur Zahlen, keine Bewertung.
            ================================================ -->
            <section class="fsg-card fsg-card--collapsible" id="wetter" aria-labelledby="wetterTitel">
                <p class="fsg-kicker">Wetter am Spot</p>
                <details class="fsg-collapse" id="wetterDetails">
                <summary><h2 id="wetterTitel" class="fsg-h2">Jetzt &amp; die nächsten Tage</h2></summary>
                <div class="fsg-collapse-body">
                <span class="fsg-meta" id="wetterStand"></span>

                <div id="wetterBox" data-spot-id="<?= (int)$spotId ?>">
                    <div id="wetterBody" aria-live="polite" aria-busy="true">
                        <!-- Platzhalter bis zum ersten Ladevorgang; wird per JS ersetzt -->
                        <div class="wx-skeleton" style="height:5.5rem"></div>
                        <div class="wx-skeleton" style="height:5rem"></div>
                        <span class="visually-hidden">Wetterdaten werden geladen …</span>
                    </div>
                </div>
                </div><!-- /.fsg-collapse-body -->
                </details>
            </section>

            <!-- ================================================
                 Fotos
            ================================================ -->
            <section class="fsg-card" id="fotos" aria-labelledby="fotosTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker"><?= $photoCount === 1 ? '1 Foto' : $photoCount . ' Fotos' ?></p>
                        <h2 id="fotosTitel" class="fsg-h2">Fotos</h2>
                    </div>
                </header>

                <?php if ($uploadError): ?>
                    <div class="alert alert-danger py-2 mb-0"><?= $e($uploadError) ?></div>
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
                                    <li><?= $e($sk['name'] ?? '') ?> – <?= $e($sk['reason'] ?? '') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($_GET['photo_deleted'])): ?>
                    <div class="alert alert-success py-2 mb-0" role="alert">Foto erfolgreich gelöscht.</div>
                <?php endif; ?>
                <?php if (!empty($_GET['photo_rotated'])): ?>
                    <div class="alert alert-success py-2 mb-0" role="alert">Foto gedreht.</div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/upload_submit.php" enctype="multipart/form-data"
                          class="sd-upload"
                          id="photoUploadForm"
                          data-max-files="<?= (int)$uploadMaxFiles ?>"
                          data-max-size="<?= 10 * 1024 * 1024 ?>">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <div class="sd-upload-row">
                            <span class="sd-upload-icon" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
                            <div class="sd-upload-text">
                                <label for="photoInput" class="sd-upload-title">Warst du schon dort? Teile deine Fotos</label>
                                <span class="fsg-meta">
                                    JPG oder PNG, bis 10 MB pro Bild<?= $uploadMaxFiles > 0 ? ', bis zu ' . (int)$uploadMaxFiles . ' auf einmal' : '' ?>
                                </span>
                            </div>
                        </div>
                        <div class="sd-upload-row sd-upload-pick">
                            <input type="file" name="photos[]" id="photoInput" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple
                                   class="fsg-field sd-file" required>
                            <button type="submit" class="fsg-btn fsg-btn--primary text-nowrap" id="photoUploadBtn">Hochladen</button>
                        </div>
                        <div id="photoSelectionInfo" class="small mt-2" aria-live="polite"></div>
                        <ul id="photoSelectionList" class="list-unstyled small mt-1 mb-0"></ul>
                        <div id="photoUploadProgress" class="mt-2 d-none">
                            <div class="progress" role="progressbar" aria-label="Upload-Fortschritt" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="progress-bar" style="width: 0%"></div>
                            </div>
                            <small class="fsg-meta" id="photoUploadStatus" aria-live="polite"></small>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="fsg-empty mb-0">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Einloggen</a>, um eigene Fotos zu teilen.
                    </p>
                <?php endif; ?>

                <?php if (empty($images)): ?>
                    <p class="fsg-empty mb-0"><i class="bi bi-camera me-1" aria-hidden="true"></i>Noch keine Fotos – sei die erste Person, die hier eins teilt.</p>
                <?php else: ?>
                    <div class="sd-photos">
                        <?php foreach ($images as $img):
                            $imgFile  = '/public/uploads/imgs/' . basename((string)$img['filename']);
                            $imgAlt   = 'Foto von ' . $spot['name'] . ', hochgeladen von ' . $img['username'];
                            // Löschen/Drehen: nur Uploader oder Admin (nicht der Spot-Ersteller)
                            $canDeleteImg = $isLoggedIn && (
                                $userId === (int)($img['user_id'] ?? 0)
                                || $isAdmin
                            );
                        ?>
                            <figure class="sd-photo">
                                <button type="button" class="sd-photo-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#photoModal"
                                        data-img-src="<?= $e($imgFile) ?>"
                                        data-img-alt="<?= $e($imgAlt) ?>"
                                        aria-label="Foto vergrößern">
                                    <img src="<?= $e(thumb_url($imgFile)) ?>"
                                         alt="<?= $e($imgAlt) ?>"
                                         loading="lazy" decoding="async"
                                         data-hide-on-error=".sd-photo">
                                </button>
                                <figcaption>
                                    <span><?= $e($img['username']) ?> · <?= $img['created_at'] ? date('d.m.Y', strtotime((string)$img['created_at'])) : 'Unbekannt' ?></span>
                                    <?php if ($canDeleteImg): ?>
                                        <span class="sd-photo-tools">
                                            <?php foreach (['left' => ['counterclockwise', 'Foto nach links drehen'], 'right' => ['clockwise', 'Foto nach rechts drehen']] as $dir => [$icon, $label]): ?>
                                                <form method="POST" action="/private/php/spots/photo_rotate_submit.php">
                                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                    <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                                                    <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                                    <input type="hidden" name="direction" value="<?= $dir ?>">
                                                    <input type="hidden" name="redirect" value="detail">
                                                    <button type="submit" class="fsg-mini-btn" title="<?= $label ?>" aria-label="<?= $label ?>">
                                                        <i class="bi bi-arrow-<?= $icon ?>" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                            <form method="POST" action="/private/php/spots/photo_delete_submit.php"
                                                  data-confirm="Foto wirklich löschen?">
                                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                                                <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                                                <input type="hidden" name="redirect" value="detail">
                                                <button type="submit" class="fsg-mini-btn fsg-mini-btn--danger" title="Foto löschen" aria-label="Foto löschen">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </span>
                                    <?php endif; ?>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ================================================
                 YouTube-Videos
                 Jeder eingeloggte Nutzer kann Videos per Link
                 ergänzen (keine Uploads). Entfernen darf nur,
                 wer das Video hinzugefügt hat, oder ein Admin.
            ================================================ -->
            <?php $videoCount = count($videos); ?>
            <section class="fsg-card" id="videos" aria-labelledby="videosTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker"><?= $videoCount === 1 ? '1 Video' : $videoCount . ' Videos' ?></p>
                        <h2 id="videosTitel" class="fsg-h2">Videos vom Spot</h2>
                    </div>
                </header>

                <?php if ($videoFlash): ?>
                    <div class="alert <?= !empty($videoFlash['ok']) ? 'alert-success' : 'alert-danger' ?> py-2 mb-0" role="<?= !empty($videoFlash['ok']) ? 'status' : 'alert' ?>">
                        <?= $e($videoFlash['message'] ?? '') ?>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn && $videoCount < SPOT_VIDEOS_MAX): ?>
                    <form method="POST" action="/private/php/spots/video_add_submit.php" class="sd-upload">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <div class="sd-upload-row">
                            <span class="sd-upload-icon" aria-hidden="true"><i class="bi bi-youtube"></i></span>
                            <div class="sd-upload-text">
                                <label for="videoUrlInput" class="sd-upload-title">Hier geflogen? Teile dein YouTube-Video</label>
                                <span class="fsg-meta">Nur Links zu youtube.com oder youtu.be – der Titel wird automatisch geholt.</span>
                            </div>
                        </div>
                        <div class="sd-upload-row sd-upload-pick">
                            <input type="url" name="video_url" id="videoUrlInput"
                                   class="fsg-field sd-video-url"
                                   placeholder="https://youtube.com/watch?v=..."
                                   maxlength="300" inputmode="url" autocomplete="off" required>
                            <button type="submit" class="fsg-btn fsg-btn--primary text-nowrap">Video hinzufügen</button>
                        </div>
                    </form>
                <?php elseif ($isLoggedIn): ?>
                    <p class="fsg-empty mb-0">Für diesen Spot sind bereits <?= (int)SPOT_VIDEOS_MAX ?> Videos verlinkt – mehr geht nicht.</p>
                <?php else: ?>
                    <p class="fsg-empty mb-0">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Einloggen</a>, um eigene Videos zu teilen.
                    </p>
                <?php endif; ?>

                <?php if (empty($videos)): ?>
                    <p class="fsg-empty mb-0"><i class="bi bi-camera-video me-1" aria-hidden="true"></i>Noch keine Videos – zeig, wie es sich hier fliegt.</p>
                <?php else: ?>
                <div class="sd-videos" id="videoGrid">
                    <?php foreach ($videos as $v):
                        $vid   = $e($v['youtube_id']);
                        $title = $e($v['title']);
                        $canDeleteVideo = $isLoggedIn && ($userId === (int)$v['user_id'] || $isAdmin);
                    ?>
                        <div class="sd-video">
                            <!-- Zwei-Klick-Loesung: Player wird erst bei Klick nachgeladen -->
                            <div class="yt-embed ratio ratio-16x9"
                                 data-yt-id="<?= $vid ?>" data-yt-title="<?= $title ?>">
                                <button type="button" class="yt-facade" aria-label="Video abspielen: <?= $title ?>">
                                    <img src="https://img.youtube.com/vi/<?= $vid ?>/mqdefault.jpg"
                                         alt="Vorschaubild: <?= $title ?>"
                                         loading="lazy"
                                         class="yt-thumb"
                                         data-hide-on-error="self">
                                    <span class="yt-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                                    <span class="yt-hint">YouTube lädt erst nach Klick</span>
                                </button>
                            </div>
                            <p class="sd-video-title"><?= $title ?></p>
                            <div class="sd-video-meta">
                                <span class="sd-video-by">hinzugefügt von <?= $e($v['username']) ?></span>
                                <span class="sd-photo-tools">
                                    <a href="https://www.youtube.com/watch?v=<?= $vid ?>" target="_blank" rel="noopener noreferrer" class="sd-link-small">
                                        Auf YouTube ansehen <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                    </a>
                                    <?php if ($canDeleteVideo): ?>
                                        <form method="POST" action="/private/php/spots/video_delete_submit.php"
                                              data-confirm="Video wirklich entfernen?">
                                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                            <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                                            <input type="hidden" name="video_id" value="<?= (int)$v['id'] ?>">
                                            <button type="submit" class="fsg-mini-btn fsg-mini-btn--danger" title="Video entfernen" aria-label="Video entfernen">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- ================================================
                 Kommentare
            ================================================ -->
            <section class="fsg-card" id="kommentare" aria-labelledby="kommentareTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker"><?= count($comments) === 1 ? '1 Kommentar' : count($comments) . ' Kommentare' ?></p>
                        <h2 id="kommentareTitel" class="fsg-h2">Erfahrungen &amp; Tipps</h2>
                    </div>
                </header>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/comments/comment_submit.php" class="sd-comment-form">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <label for="commentBody" class="visually-hidden">Dein Kommentar</label>
                        <textarea name="body" id="commentBody" class="fsg-field"
                                  placeholder="Wie war's vor Ort? Wind, Leute, Zugang …" rows="3"
                                  minlength="3" maxlength="1000" required></textarea>
                        <div class="text-end">
                            <button type="submit" class="fsg-btn fsg-btn--primary">
                                <i class="bi bi-send" aria-hidden="true"></i>Senden
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="fsg-empty mb-0">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Einloggen</a>, um deine Erfahrungen zu teilen.
                    </p>
                <?php endif; ?>

                <?php if (empty($comments)): ?>
                    <p class="fsg-empty mb-0"><i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Noch keine Kommentare. Schreib den ersten!</p>
                <?php else: ?>
                    <div class="sd-comments">
                        <?php foreach ($comments as $comment):
                            $commentId   = (int)$comment['id'];
                            $commentDate = $comment['created_at'] ? date('d.m.Y, H:i', strtotime((string)$comment['created_at'])) . ' Uhr' : 'Unbekannt';
                            $isCommentOwner = $isLoggedIn && $userId === (int)$comment['user_id'];
                            $canDelete = $isCommentOwner || $isAdmin;
                        ?>
                            <article class="sd-comment" id="comment-<?= $commentId ?>">
                                <?= render_avatar((string)$comment['username'], $comment['avatar'] !== null ? (string)$comment['avatar'] : null, 'sd-avatar', 40) ?>
                                <div class="sd-comment-body">
                                    <div class="sd-comment-head">
                                        <a href="/profile.php?id=<?= (int)$comment['user_id'] ?>" class="sd-comment-user"><?= $e($comment['username']) ?></a>
                                        <span class="fsg-meta"><?= $e($commentDate) ?></span>
                                        <span class="sd-comment-tools">
                                            <?php if ($isCommentOwner): ?>
                                                <button type="button" class="fsg-mini-btn"
                                                        data-action="toggle-edit-comment" data-comment-id="<?= $commentId ?>"
                                                        title="Bearbeiten" aria-label="Kommentar bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                                <form method="POST" action="/private/php/comments/comment_delete_submit.php"
                                                      class="d-inline" data-confirm="Kommentar wirklich löschen?">
                                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                                                    <button type="submit" class="fsg-mini-btn fsg-mini-btn--danger"
                                                            title="Löschen" aria-label="Kommentar löschen"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div id="comment-display-<?= $commentId ?>">
                                        <p class="mb-0"><?= nl2br($e($comment['body'])) ?></p>
                                    </div>

                                    <?php if ($isCommentOwner): ?>
                                        <div id="comment-edit-<?= $commentId ?>" class="d-none mt-2">
                                            <form method="POST" action="/private/php/comments/comment_edit_submit.php">
                                                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                                                <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                                                <label for="commentEdit-<?= $commentId ?>" class="visually-hidden">Kommentar bearbeiten</label>
                                                <textarea name="body" id="commentEdit-<?= $commentId ?>" class="fsg-field mb-2"
                                                          rows="3" minlength="3" maxlength="1000" required><?= $e($comment['body']) ?></textarea>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="fsg-btn fsg-btn--primary fsg-btn--sm">Speichern</button>
                                                    <button type="button" class="fsg-btn fsg-btn--sm"
                                                            data-action="toggle-edit-comment" data-comment-id="<?= $commentId ?>">Abbrechen</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ================================================
             Seitenspalte: Bewertung, Ausruestung, Melden
             (auf dem Desktop mitlaufend)
        ================================================ -->
        <aside class="sd-aside" aria-label="Bewertung und mehr">
            <section class="fsg-card" id="bewertung" aria-labelledby="bewertungTitel">
                <h2 id="bewertungTitel" class="fsg-kicker mb-0">Bewertung</h2>

                <?php if ($avgText !== null): ?>
                    <div class="sd-score">
                        <span class="sd-score-num"><?= $e($avgText) ?></span>
                        <span class="sd-score-meta">
                            <span class="stars-display" aria-label="<?= $e($avgText) ?> von 5 Sternen">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= round($avgStars) ? '-fill' : '' ?>" aria-hidden="true"></i>
                                <?php endfor; ?>
                            </span>
                            <span><?= $ratingCount ?> <?= $ratingCount === 1 ? 'Bewertung' : 'Bewertungen' ?></span>
                        </span>
                    </div>
                    <ul class="sd-dist" aria-label="Verteilung der Bewertungen">
                        <?php foreach ($ratingDist as $s => $n): ?>
                            <li>
                                <span><?= $s ?> <i class="bi bi-star-fill" aria-hidden="true"></i></span>
                                <span class="sd-bar"><span style="width: <?= (int)round($n / $ratingDistMax * 100) ?>%"></span></span>
                                <span><?= $n ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="fsg-empty mb-0">Noch keine Bewertungen – wie fandest du den Spot?</p>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/spots/rate_submit.php" class="sd-rate">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                        <fieldset>
                            <legend class="fsg-label"><?= $userRating > 0 ? 'Deine Bewertung: ' . $userRating . ' von 5' : 'Deine Bewertung' ?></legend>
                            <div id="starRating" class="sd-stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="stars" value="<?= $i ?>" id="star<?= $i ?>" class="visually-hidden" <?= $userRating === $i ? 'checked' : '' ?>>
                                    <label for="star<?= $i ?>" class="star <?= $userRating >= $i ? 'active' : '' ?>" title="<?= $i ?> von 5 Sternen">
                                        <i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden"><?= $i ?> von 5 Sternen</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </fieldset>
                        <button type="submit" class="fsg-btn fsg-btn--sm w-100"><?= $userRating > 0 ? 'Bewertung ändern' : 'Bewerten' ?></button>
                    </form>
                <?php else: ?>
                    <p class="fsg-empty mb-0">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Einloggen</a>, um zu bewerten.
                    </p>
                <?php endif; ?>
            </section>

            <?php
            // Passende Ausruestung (Partnerlinks, nur wenn Produkte zur Coptergroesse konfiguriert sind)
            $affiliateItems = affiliate_for_sizes(explode(',', $spot['copter_size']), 3);
            $affiliateTitle = 'Passende Ausrüstung';
            $affiliateId    = 'ausruestung';
            include __DIR__ . '/../../includes/affiliate_block.php';
            ?>

            <section class="fsg-card fsg-card--quiet" aria-labelledby="meldenTitel">
                <h2 id="meldenTitel" class="fsg-h3 mb-0">Stimmt etwas nicht?</h2>
                <p class="mb-0">Spot existiert nicht mehr, Angaben sind falsch oder Inhalte unpassend – sag uns Bescheid.</p>
                <?php if ($isLoggedIn): ?>
                    <button type="button" class="fsg-btn fsg-btn--sm align-self-start" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-flag" aria-hidden="true"></i>Spot melden
                    </button>
                <?php else: ?>
                    <p class="fsg-meta mb-0"><a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Einloggen</a>, um den Spot zu melden.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

<script src="<?= asset('/public/js/pages/spot_detail.js') ?>"></script>

<!-- ============================================================
     Mehrfach-Foto-Upload

     Auswahl wird im Browser vorgeprüft (Typ, max. 10 MB, max. 10
     Dateien für Nicht-Admins). Gültige Bilder gehen einzeln
     nacheinander an upload_submit.php (ajax=1) – so greift das
     Gesamtlimit post_max_size nie, egal wie viele Bilder.
     Der Server prüft jede Datei erneut und sammelt das Ergebnis in
     der Session; nach dem letzten Bild lädt die Seite neu.
============================================================ -->
<script src="<?= asset('/public/js/pages/spot_detail_upload.js') ?>"></script>

<!-- ============================================================
     Foto-Popup-Modal (Lightbox)
============================================================ -->
<div class="modal fade" id="photoModal" data-bs-theme="dark" tabindex="-1" aria-labelledby="photoModalLabel" aria-modal="true" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content fsg-modal">
            <div class="modal-header">
                <h2 class="modal-title h5" id="photoModalLabel"><i class="bi bi-image me-2"></i><?= $e($spot['name']) ?></h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="photoModalImg" src="" alt="" class="img-fluid rounded mx-auto d-block">
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('/public/js/pages/spot_detail_photo_modal.js') ?>"></script>

<?php if ($isLoggedIn): ?>
<!-- ============================================================
     Spot-Melden-Modal
============================================================ -->
<div class="modal fade" id="reportModal" data-bs-theme="dark" tabindex="-1" aria-labelledby="reportModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content fsg-modal">
            <div class="modal-header">
                <h2 class="modal-title h5" id="reportModalLabel"><i class="bi bi-flag me-2"></i>Spot melden</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="POST" action="/private/php/contact/report_submit.php" id="reportForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="spot_id" value="<?= $spotId ?>">
                    <div class="mb-3">
                        <label for="reportType" class="form-label">Worum geht es?</label>
                        <select class="fsg-field" id="reportType" name="report_type" required>
                            <option value="" disabled selected>Bitte wählen …</option>
                            <option value="Spot-Allgemein">Spot-Allgemein (z.B. existiert nicht mehr)</option>
                            <option value="Spot-Info">Spot-Info (falsche oder veraltete Angaben)</option>
                            <option value="Foto">Foto (unangemessenes oder falsches Bild)</option>
                            <option value="Kommentar">Kommentar (unangemessener Kommentar)</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="reportBody" class="form-label">Beschreibung <small class="fsg-meta">(mind. 10 Zeichen)</small></label>
                        <textarea class="fsg-field" id="reportBody" name="body"
                                  rows="4" minlength="10" maxlength="1000" required
                                  placeholder="Beschreibe das Problem genauer …"></textarea>
                        <div class="fsg-meta mt-1"><span id="reportCharCount">0</span> / 1000</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="fsg-btn" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="fsg-btn fsg-btn--primary">Meldung absenden</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= asset('/public/js/pages/spot_detail_report.js') ?>"></script>
<?php endif; ?>

<!-- ============================================================
     Wetter: Lazy-Load beim ersten Aufklappen des Wetter-Bereichs.
     Alle Fremdwerte gehen durch Number() oder esc() – kein rohes
     innerHTML mit ungeprüfter Antwort. Bewusst ohne Bewertung.
============================================================ -->
<script src="<?= asset('/public/js/pages/spot_detail_weather.js') ?>"></script>

</body>
</html>
