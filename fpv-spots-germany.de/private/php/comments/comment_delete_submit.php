<?php
// =============================================================
// Kommentar loeschen
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

$commentId = (int)($_POST['comment_id'] ?? 0);
$userId    = (int)$_SESSION['user_id'];

// Kommentar laden und Besitzer pruefen
$stmt = $pdo->prepare("SELECT spot_id, user_id FROM comments WHERE id = ?");
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment) {
    header('Location: /');
    exit;
}

$spotId  = (int)$comment['spot_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ((int)$comment['user_id'] !== $userId && !$isAdmin) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
} catch (PDOException $e) {
    error_log('comment_delete_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId");
exit;
