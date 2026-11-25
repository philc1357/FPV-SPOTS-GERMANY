<?php
declare(strict_types=1);
// =============================================================
// Spot erstellen – klassisches Form-POST aus index.php
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/youtube_helper.php';
require_once __DIR__ . '/../core/geozones.php';
require_once __DIR__ . '/upload_helper.php';
require_once __DIR__ . '/highlight_helper.php';
require_once __DIR__ . '/../core/discord.php';

// Nur POST akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// post_max_size überschritten: PHP verwirft dann den kompletten Body
// ($_POST und $_FILES leer) – ohne diese Prüfung endete das als CSRF-Fehler.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['spot_error'] = 'Die Fotos sind zusammen zu groß. Bitte wähle kleinere Bilder (max. 10 MB pro Foto).';
    header('Location: /');
    exit;
}

// Authentifizierung pruefen
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token pruefen
if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

// Eingaben holen und bereinigen
$name        = trim(post_str('name'));
$description = trim(post_str('description'));
$spotType    = trim(post_str('spot_type'));
$difficulty   = trim(post_str('difficulty'));
$parkingInfo  = trim(post_str('parking_info'));
$latitude     = post_str('latitude');
$longitude    = post_str('longitude');

// Parkinfo: leer oder nicht gesendet => "Unbekannt"
if ($parkingInfo === '') {
    $parkingInfo = 'Unbekannt';
}

// Coptergröße: Whitelist-Filterung der Mehrfachauswahl
$allowedSizes  = ['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'];
$rawSizes      = is_array($_POST['copter_size'] ?? null) ? $_POST['copter_size'] : [];
$copterSize    = implode(',', array_values(array_intersect($rawSizes, $allowedSizes)));

// =============================================================
// YouTube-Videos einsammeln und validieren (max. SPOT_VIDEOS_MAX URLs)
// Titel wird automatisch über YouTubes oEmbed-API geholt.
// =============================================================
$rawVideoUrls = is_array($_POST['video_url'] ?? null) ? $_POST['video_url'] : [];
$videoCount   = min(count($rawVideoUrls), SPOT_VIDEOS_MAX);

$validVideos = [];
$videoError  = null;
$seenIds     = [];
for ($i = 0; $i < $videoCount; $i++) {
    $vUrl = is_string($rawVideoUrls[$i] ?? null) ? trim($rawVideoUrls[$i]) : '';
    if ($vUrl === '') {
        continue;
    }
    $youtubeId = extractYoutubeId($vUrl);
    if ($youtubeId === null) {
        $videoError = 'Ungültige YouTube-URL: ' . htmlspecialchars($vUrl, ENT_QUOTES, 'UTF-8');
        break;
    }
    if (isset($seenIds[$youtubeId])) {
        continue;
    }
    $seenIds[$youtubeId] = true;

    $title = fetchYoutubeTitle($youtubeId);
    if ($title === null) {
        $title = 'YouTube-Video';
    }
    $validVideos[] = ['title' => $title, 'youtube_id' => $youtubeId];
}

// =============================================================
// Highlight-Fotos (optional, max. SPOT_HIGHLIGHTS_MAX)
//
// Die Dateien selbst prüft upload_store_files() erst nach dem
// Spot-INSERT (braucht die spot_id). Hier nur Anzahl und die
// gewünschte Reihenfolge: highlight_order = kommagetrennte
// Dateiindizes, erster Eintrag = Titelbild. Ungültige, doppelte
// oder fehlende Indizes werden ignoriert bzw. in Upload-Reihenfolge
// ergänzt – eine kaputte Reihenfolge verhindert den Spot nicht.
// =============================================================
$photoFiles = upload_collect_files('photos');
$photoCount = count($photoFiles);

$photoOrder = [];
$rawOrder   = post_str('highlight_order');
if ($rawOrder !== '' && strlen($rawOrder) <= 20) {
    foreach (explode(',', $rawOrder) as $part) {
        $part = trim($part);
        if ($part === '' || !ctype_digit($part)) {
            continue;
        }
        $idx = (int)$part;
        if ($idx < $photoCount && !in_array($idx, $photoOrder, true)) {
            $photoOrder[] = $idx;
        }
    }
}
foreach (array_keys($photoFiles) as $idx) {
    if (!in_array($idx, $photoOrder, true)) {
        $photoOrder[] = $idx;
    }
}

// Erlaubte Enum-Werte (muessen mit Formular UND DB-Schema uebereinstimmen)
$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
$allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

// Validierung
if (empty($name) || strlen($name) > 100 || strlen($name) < 5) {
    $error = 'Name ist erforderlich. Muss mindestens 5 und darf maximal 100 Zeichen lang sein.';
}
elseif (empty($description) || strlen($description) < 10) {
    $error = 'Schreibe bitte eine kurze Beschreibung zu deinem Spot.';
}
elseif (strlen($description) > 2000) {
    $error = 'Beschreibung darf maximal 2000 Zeichen lang sein.';
}
elseif (!in_array($spotType, $allowedTypes, true)) {
    $error = 'Ungültiger Spot-Typ.';
}
elseif (!in_array($difficulty, $allowedDiff, true)) {
    $error = 'Ungültige Schwierigkeit.';
}
elseif (strlen($parkingInfo) > 500) {
    $error = 'Parkinfo darf maximal 500 Zeichen lang sein.';
}
elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
    $error = 'Ungültige Koordinaten.';
}
elseif ($videoError !== null) {
    $error = $videoError;
}
elseif ($photoCount > SPOT_HIGHLIGHTS_MAX) {
    $error = 'Beim Erstellen sind höchstens ' . SPOT_HIGHLIGHTS_MAX . ' Fotos möglich. Weitere kannst du danach auf der Spot-Seite hochladen.';
}
else {
    $lat = (float)$latitude;
    $lng = (float)$longitude;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $error = 'Koordinaten ausserhalb des gültigen Bereichs.';
    }
}

// Bei Fehler: zurueck zur Karte
if (isset($error)) {
    // Fehler in Session speichern fuer Anzeige
    $_SESSION['spot_error'] = $error;
    header('Location: /');
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "INSERT INTO spots (user_id, name, description, latitude, longitude, spot_type, difficulty, parking_info, copter_size)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $name, $description, $lat, $lng, $spotType, $difficulty, $parkingInfo, $copterSize]);
    $newSpotId = (int)$pdo->lastInsertId();

    // YouTube-Videos einfügen (Reihenfolge per position)
    if ($newSpotId > 0 && !empty($validVideos)) {
        $videoStmt = $pdo->prepare(
            "INSERT INTO spot_videos (spot_id, user_id, title, youtube_id, position) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($validVideos as $idx => $v) {
            $videoStmt->execute([$newSpotId, $userId, $v['title'], $v['youtube_id'], $idx]);
        }
    }

    // Audit-Log
    $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'SPOT_CREATED', ?)";
    $pdo->prepare($logSql)->execute([$userId, client_ip()]);

    // Legende-Cookies aktualisieren: Typ und Schwierigkeit des neuen Spots aktivieren,
    // damit der Marker nach dem Redirect nicht durch den Filter versteckt wird.
    $allLegendTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
    $allLegendDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];
    $legendTypes = isset($_COOKIE['legend_types'])
        ? array_values(array_intersect(explode(',', $_COOKIE['legend_types']), $allLegendTypes))
        : $allLegendTypes;
    $legendDiffs = isset($_COOKIE['legend_diffs'])
        ? array_values(array_intersect(explode(',', $_COOKIE['legend_diffs']), $allLegendDiffs))
        : $allLegendDiffs;

    if (!in_array($spotType, $legendTypes, true)) {
        $legendTypes[] = $spotType;
    }
    if (!in_array($difficulty, $legendDiffs, true)) {
        $legendDiffs[] = $difficulty;
    }

    $cookieExpires = !empty($_COOKIE['remember_me']) ? time() + 30 * 86400 : 0;
    $legendCookieOpts = [
        'expires'  => $cookieExpires,
        'path'     => '/',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ];
    setcookie('legend_types', implode(',', $legendTypes), $legendCookieOpts);
    setcookie('legend_diffs', implode(',', $legendDiffs), $legendCookieOpts);

    // -------------------------------------------------------------
    // DIPUL-Zonencheck fuer den neuen Spot
    //
    // Synchron, weil der Ersteller das Ergebnis sofort sehen soll.
    // Faellt die DFS aus, bleibt zones_checked_at NULL – die Detailseite
    // zeigt dann "noch nicht geprueft" (bewusst KEINE Entwarnung) und
    // refresh_spot_zones.php holt den Check per Cron nach.
    // -------------------------------------------------------------
    if ($newSpotId > 0) {
        refresh_spot_zones($pdo, $newSpotId, (float)$lat, (float)$lng);
    }

} catch (PDOException $e) {
    error_log('spot_submit.php error: ' . $e->getMessage());
    $_SESSION['spot_error'] = 'Datenbankfehler. Bitte erneut versuchen.';
    header('Location: /');
    exit;
}

if ($newSpotId <= 0) {
    $_SESSION['spot_error'] = 'Datenbankfehler. Bitte erneut versuchen.';
    header('Location: /');
    exit;
}

// =============================================================
// Highlight-Fotos speichern und als Highlights festlegen
//
// Der Spot existiert jetzt. Fehler beim Foto-Teil verwerfen den
// Spot nicht – sie landen im Upload-Bericht auf der Detailseite,
// fehlende Fotos lassen sich dort nachreichen.
// =============================================================
if ($photoCount > 0) {
    try {
        $remaining = upload_remaining_quota($pdo, $userId, !empty($_SESSION['is_admin']));
        $result    = upload_store_files($pdo, $newSpotId, $userId, $photoFiles, $remaining);

        // Dateiindizes in gewünschter Reihenfolge → gespeicherte Bild-IDs
        $highlightIds = [];
        foreach ($photoOrder as $idx) {
            if (isset($result['ids'][$idx])) {
                $highlightIds[] = $result['ids'][$idx];
            }
        }
        if ($highlightIds !== []) {
            spot_set_highlights($pdo, $newSpotId, $highlightIds);
        }

        upload_store_report($newSpotId, $result['saved'], $result['skipped']);
    } catch (Throwable $e) {
        error_log('spot_submit.php photo error: ' . $e->getMessage());
        upload_store_report($newSpotId, 0, [['name' => 'Fotos', 'reason' => 'Fehler beim Speichern']]);
    }
}

// Neuen Spot im Discord ankündigen (nach Request-Ende, best effort)
discord_notify_new_spot_deferred($pdo, $newSpotId);

// Immer zur neuen Spot-Seite
header('Location: /spot_detail.php?id=' . $newSpotId);
exit;
