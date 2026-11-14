<?php
declare(strict_types=1);
// =============================================================
// Einzelnes Spot-Video entfernen – Form-Submit
// -------------------------------------------------------------
// Berechtigt: nur wer das Video hinzugefügt hat oder ein Admin.
// Spot-Ersteller dürfen Videos anderer Nutzer NICHT entfernen.
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$videoId = (int)($_POST['video_id'] ?? 0);
$spotId  = (int)($_POST['spot_id']  ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($videoId <= 0 || $spotId <= 0) {
    header('Location: /');
    exit;
}

// =============================================================
// Video laden und Berechtigung prüfen
// =============================================================
$stmt = $pdo->prepare("SELECT id, user_id FROM spot_videos WHERE id = ? AND spot_id = ?");
$stmt->execute([$videoId, $spotId]);
$row = $stmt->fetch();

if (!$row || ((int)$row['user_id'] !== $userId && !$isAdmin)) {
    header('Location: /spot_detail.php?id=' . $spotId . '#videos');
    exit;
}

// =============================================================
// Löschen + Audit-Log
// =============================================================
try {
    $pdo->prepare("DELETE FROM spot_videos WHERE id = ?")->execute([$videoId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'VIDEO_DELETED', ?)")
        ->execute([$userId, client_ip()]);
    $_SESSION['video_flash'] = ['spot_id' => $spotId, 'ok' => true, 'message' => 'Video entfernt.'];
} catch (PDOException $e) {
    error_log('video_delete_submit.php error: ' . $e->getMessage());
    $_SESSION['video_flash'] = ['spot_id' => $spotId, 'ok' => false, 'message' => 'Das Video konnte nicht entfernt werden.'];
}

header('Location: /spot_detail.php?id=' . $spotId . '#videos');
exit;
