<?php
declare(strict_types=1);
// =============================================================
// Spot bearbeiten – Form-Submit
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

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

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId      = (int)($_POST['spot_id'] ?? 0);
$userId      = (int)$_SESSION['user_id'];
$isAdmin     = !empty($_SESSION['is_admin']);
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$spotType    = trim($_POST['spot_type'] ?? '');
$difficulty   = trim($_POST['difficulty'] ?? '');
$parkingInfo  = trim($_POST['parking_info'] ?? '');

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
// YouTube-Videos einsammeln und validieren (max. 10 URLs)
// Bestehende Titel aus DB übernehmen, neue per oEmbed holen.
// =============================================================
$rawVideoUrls = is_array($_POST['video_url'] ?? null) ? $_POST['video_url'] : [];
$videoCount   = min(count($rawVideoUrls), 10);

// Bisherige Titel pro youtube_id laden (vermeidet redundante oEmbed-Calls)
$existingTitles = [];
$rs = $pdo->prepare("SELECT youtube_id, title FROM spot_videos WHERE spot_id = ?");
$rs->execute([$spotId]);
foreach ($rs->fetchAll() as $row) {
    $existingTitles[$row['youtube_id']] = $row['title'];
}

$validVideos = [];
$seenIds     = [];
for ($i = 0; $i < $videoCount; $i++) {
    $vUrl = trim((string)($rawVideoUrls[$i] ?? ''));
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

    // Videos: alte löschen, neue komplett einfügen (Edit-Form überträgt vollständige Liste)
    $pdo->prepare("DELETE FROM spot_videos WHERE spot_id = ?")->execute([$spotId]);

    if (!empty($validVideos)) {
        $videoStmt = $pdo->prepare(
            "INSERT INTO spot_videos (spot_id, title, youtube_id, position) VALUES (?, ?, ?, ?)"
        );
        foreach ($validVideos as $idx => $v) {
            $videoStmt->execute([$spotId, $v['title'], $v['youtube_id'], $idx]);
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
