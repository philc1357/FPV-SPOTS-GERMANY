<?php
declare(strict_types=1);
// =============================================================
// Spot bearbeiten – Form-Submit
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/youtube_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/dashboard.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/dashboard.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$spotId      = (int)($_POST['spot_id'] ?? 0);
$userId      = (int)$_SESSION['user_id'];
$isAdmin     = !empty($_SESSION['is_admin']);
$name        = trim(post_str('name'));
$description = trim(post_str('description'));
$spotType    = trim(post_str('spot_type'));
$difficulty   = trim(post_str('difficulty'));
$parkingInfo  = trim(post_str('parking_info'));

// Parkinfo: leer oder nicht gesendet => "Unbekannt"
if ($parkingInfo === '') {
    $parkingInfo = 'Unbekannt';
}

// Coptergröße: Whitelist-Filterung der Mehrfachauswahl
$allowedSizes = ['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'];
$rawSizes     = is_array($_POST['copter_size'] ?? null) ? $_POST['copter_size'] : [];
$copterSize   = implode(',', array_values(array_intersect($rawSizes, $allowedSizes)));

if ($spotId <= 0) {
    header('Location: /public/php/dashboard.php');
    exit;
}

// Besitzer-/Admin-Check
$stmt = $pdo->prepare("SELECT user_id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
$row = $stmt->fetch();

if (!$row || ((int)$row['user_id'] !== $userId && !$isAdmin)) {
    header('Location: /public/php/dashboard.php');
    exit;
}
// Videos im Formular gehören immer dem Spot-Ersteller – auch wenn ein Admin speichert
$spotOwnerId = (int)$row['user_id'];

// Validierung
$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
$allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

if (empty($name) || strlen($name) > 100) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (strlen($description) > 2000) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (strlen($parkingInfo) > 500) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (!in_array($spotType, $allowedTypes, true) || !in_array($difficulty, $allowedDiff, true)) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}

// =============================================================
// YouTube-Videos des Spot-Erstellers einsammeln und validieren
// Das Formular verwaltet nur die Videos des Erstellers; Videos
// anderer Nutzer bleiben unangetastet und zählen zum Gesamtlimit.
// Bestehende Titel aus DB übernehmen, neue per oEmbed holen.
// =============================================================
$rawVideoUrls = is_array($_POST['video_url'] ?? null) ? $_POST['video_url'] : [];

// Bisherige Titel pro youtube_id laden (vermeidet redundante oEmbed-Calls)
// und Videos anderer Nutzer merken (dürfen nicht doppelt angelegt werden)
$existingTitles = [];
$foreignIds     = [];
$rs = $pdo->prepare("SELECT user_id, youtube_id, title FROM spot_videos WHERE spot_id = ?");
$rs->execute([$spotId]);
foreach ($rs->fetchAll() as $vr) {
    $existingTitles[$vr['youtube_id']] = $vr['title'];
    if ((int)$vr['user_id'] !== $spotOwnerId) {
        $foreignIds[$vr['youtube_id']] = true;
    }
}

$maxOwnerVideos = max(0, SPOT_VIDEOS_MAX - count($foreignIds));
$videoCount     = min(count($rawVideoUrls), SPOT_VIDEOS_MAX);

$validVideos = [];
$seenIds     = $foreignIds; // bereits von anderen verlinkt → überspringen
for ($i = 0; $i < $videoCount && count($validVideos) < $maxOwnerVideos; $i++) {
    $vUrl = is_string($rawVideoUrls[$i] ?? null) ? trim($rawVideoUrls[$i]) : '';
    if ($vUrl === '') {
        continue;
    }
    $youtubeId = extractYoutubeId($vUrl);
    if ($youtubeId === null) {
        header("Location: /public/php/edit_spot.php?id=$spotId&video_error=1");
        exit;
    }
    if (isset($seenIds[$youtubeId])) {
        continue;
    }
    $seenIds[$youtubeId] = true;

    if (isset($existingTitles[$youtubeId])) {
        $title = $existingTitles[$youtubeId];
    } else {
        $title = fetchYoutubeTitle($youtubeId) ?? 'YouTube-Video';
    }
    $validVideos[] = ['title' => $title, 'youtube_id' => $youtubeId];
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE spots SET name = ?, description = ?, spot_type = ?, difficulty = ?, parking_info = ?, copter_size = ? WHERE id = ?"
    );
    $stmt->execute([$name, $description, $spotType, $difficulty, $parkingInfo, $copterSize, $spotId]);

    // Videos des Erstellers: alte löschen, neue komplett einfügen
    // (Edit-Form überträgt dessen vollständige Liste; fremde Videos bleiben)
    $pdo->prepare("DELETE FROM spot_videos WHERE spot_id = ? AND user_id = ?")->execute([$spotId, $spotOwnerId]);

    if (!empty($validVideos)) {
        $videoStmt = $pdo->prepare(
            "INSERT INTO spot_videos (spot_id, user_id, title, youtube_id, position) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($validVideos as $idx => $v) {
            $videoStmt->execute([$spotId, $spotOwnerId, $v['title'], $v['youtube_id'], $idx]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('edit_spot_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/edit_spot.php?id=$spotId&success=1");
exit;
