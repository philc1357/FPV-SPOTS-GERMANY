<?php
// =============================================================
// FPV Spots Germany – Spot-Meldung speichern
// =============================================================
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/login.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

require_once __DIR__ . '/../core/db.php';

$spotId     = (int)($_POST['spot_id'] ?? 0);
$userId     = (int)$_SESSION['user_id'];
$reportType = trim($_POST['report_type'] ?? '');
$body       = trim($_POST['body'] ?? '');

$allowedTypes = ['Kommentar', 'Foto', 'Spot-Info', 'Spot-Allgemein'];

if ($spotId <= 0
    || !in_array($reportType, $allowedTypes, true)
    || strlen($body) < 10
    || strlen($body) > 1000
) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

// Spot-Existenz prüfen
$stmt = $pdo->prepare("SELECT id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
if (!$stmt->fetch()) {
    header('Location: /');
    exit;
}

// Rate-Limit: max. 10 Meldungen pro User in 1h; zusätzlich: pro (spot, user,
// report_type) max. 1 Meldung pro Tag (verhindert Report-Spam gegen fremde Spots).
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM spot_reports
     WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 10) {
    header("Location: /public/php/spot_detail.php?id=$spotId&reported=rl");
    exit;
}

$dup = $pdo->prepare(
    "SELECT COUNT(*) FROM spot_reports
     WHERE user_id = ? AND spot_id = ? AND report_type = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)"
);
$dup->execute([$userId, $spotId, $reportType]);
if ((int)$dup->fetchColumn() >= 1) {
    header("Location: /public/php/spot_detail.php?id=$spotId&reported=dup");
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO spot_reports (spot_id, user_id, report_type, body)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$spotId, $userId, $reportType, $body]);
} catch (PDOException $e) {
    error_log('report_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId&reported=1");
exit;
