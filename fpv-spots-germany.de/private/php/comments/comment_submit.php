<?php
// =============================================================
// Kommentar erstellen
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId = (int)($_POST['spot_id'] ?? 0);
$body   = trim($_POST['body'] ?? '');
$userId = (int)$_SESSION['user_id'];

if ($spotId <= 0 || strlen($body) < 3 || strlen($body) > 1000) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO comments (spot_id, user_id, body) VALUES (?, ?, ?)");
    $stmt->execute([$spotId, $userId, $body]);
} catch (PDOException $e) {
    error_log('comment_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId");
exit;
