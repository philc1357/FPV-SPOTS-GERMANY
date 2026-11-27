<?php
declare(strict_types=1);
// =============================================================
// YouTube-Video zu einem Spot hinzufügen – Form-Submit
// -------------------------------------------------------------
// Jeder eingeloggte Nutzer darf Videos per YouTube-Link ergänzen
// (keine Video-Uploads). Das Video gehört dem Nutzer, der es
// hinzugefügt hat – nur er selbst oder ein Admin kann es löschen.
// Limits: SPOT_VIDEOS_MAX pro Spot, 10 pro Nutzer und Stunde
// (Admins ohne Stundenlimit).
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/youtube_helper.php';

const VIDEO_ADD_MAX_PER_HOUR = 10;
const VIDEO_URL_MAX_LENGTH   = 300;

// =============================================================
// Antwort-Helfer: Flash-Meldung setzen und zurück zur Spot-Seite
// =============================================================
function video_add_respond(int $spotId, bool $ok, string $message): never
{
    if ($spotId <= 0) {
        header('Location: /');
        exit;
    }
    $_SESSION['video_flash'] = ['spot_id' => $spotId, 'ok' => $ok, 'message' => $message];
    header('Location: /spot_detail.php?id=' . $spotId . '#videos');
    exit;
}

// =============================================================
// Grundprüfungen: Methode, Login, CSRF, Spot
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!csrf_valid(post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$spotId  = (int)($_POST['spot_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($spotId <= 0) {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
if (!$stmt->fetch()) {
    header('Location: /');
    exit;
}

// =============================================================
// URL validieren (nur YouTube, Whitelist auf die 11-stellige ID)
// =============================================================
$rawUrl = trim(post_str('video_url'));
if ($rawUrl === '' || strlen($rawUrl) > VIDEO_URL_MAX_LENGTH) {
    video_add_respond($spotId, false, 'Bitte gib eine gültige YouTube-URL ein.');
}

$youtubeId = extractYoutubeId($rawUrl);
if ($youtubeId === null) {
    video_add_respond($spotId, false, 'Das ist keine gültige YouTube-URL (youtube.com oder youtu.be).');
}

// =============================================================
// Duplikat, Gesamtlimit und Stundenlimit prüfen
// =============================================================
$stmt = $pdo->prepare("SELECT 1 FROM spot_videos WHERE spot_id = ? AND youtube_id = ?");
$stmt->execute([$spotId, $youtubeId]);
if ($stmt->fetchColumn()) {
    video_add_respond($spotId, false, 'Dieses Video ist bei diesem Spot bereits verlinkt.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM spot_videos WHERE spot_id = ?");
$stmt->execute([$spotId]);
if ((int)$stmt->fetchColumn() >= SPOT_VIDEOS_MAX) {
    video_add_respond($spotId, false, 'Für diesen Spot sind bereits ' . SPOT_VIDEOS_MAX . ' Videos verlinkt.');
}

if (!$isAdmin) {
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'VIDEO_ADDED' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$userId]);
    if ((int)$rl->fetchColumn() >= VIDEO_ADD_MAX_PER_HOUR) {
        video_add_respond($spotId, false, 'Stundenlimit erreicht (' . VIDEO_ADD_MAX_PER_HOUR . ' Videos pro Stunde). Bitte versuche es später erneut.');
    }
}

// =============================================================
// Titel holen und speichern
// =============================================================
$title = fetchYoutubeTitle($youtubeId) ?? 'YouTube-Video';

try {
    $pos = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM spot_videos WHERE spot_id = ?");
    $pos->execute([$spotId]);
    $position = min(255, (int)$pos->fetchColumn());

    $pdo->prepare(
        "INSERT INTO spot_videos (spot_id, user_id, title, youtube_id, position) VALUES (?, ?, ?, ?, ?)"
    )->execute([$spotId, $userId, $title, $youtubeId, $position]);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'VIDEO_ADDED', ?)")
        ->execute([$userId, client_ip()]);
} catch (PDOException $e) {
    // u. a. Unique-Verletzung bei gleichzeitigem Doppel-Submit
    error_log('video_add_submit.php error: ' . $e->getMessage());
    video_add_respond($spotId, false, 'Das Video konnte nicht gespeichert werden.');
}

video_add_respond($spotId, true, 'Video hinzugefügt – danke fürs Teilen!');
