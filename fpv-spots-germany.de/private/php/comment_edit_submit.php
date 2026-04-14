<?php
// =============================================================
// Kommentar bearbeiten
// =============================================================
session_start();

require_once __DIR__ . '/db.php';

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

$commentId = (int)($_POST['comment_id'] ?? 0);
$body      = trim($_POST['body'] ?? '');
$userId    = (int)$_SESSION['user_id'];

// Kommentar laden und Besitzer pruefen
$stmt = $pdo->prepare("SELECT spot_id, user_id FROM comments WHERE id = ?");
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment) {
    header('Location: /');
    exit;
}

$spotId = (int)$comment['spot_id'];

if ((int)$comment['user_id'] !== $userId) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

if (strlen($body) < 3 || strlen($body) > 1000) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE comments SET body = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$body, $commentId, $userId]);
} catch (PDOException $e) {
    error_log('comment_edit_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId");
exit;
