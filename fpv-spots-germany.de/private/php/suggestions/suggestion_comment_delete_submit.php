<?php
declare(strict_types=1);
// =============================================================
// Admin-Kommentar zu Verbesserungsvorschlag löschen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /kritik.php');
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /kritik.php');
    exit;
}

if (!csrf_valid(post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$commentId = (int)($_POST['comment_id'] ?? 0);

if ($commentId <= 0) {
    header('Location: /kritik.php');
    exit;
}

// suggestion_id für Redirect-Anker auslesen
$stmt = $pdo->prepare("SELECT suggestion_id FROM suggestion_comments WHERE id = ?");
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment) {
    header('Location: /kritik.php');
    exit;
}

$suggestionId = (int)$comment['suggestion_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM suggestion_comments WHERE id = ?");
    $stmt->execute([$commentId]);
} catch (PDOException $e) {
    error_log('suggestion_comment_delete_submit.php error: ' . $e->getMessage());
}

header('Location: /kritik.php#suggestion-' . $suggestionId);
exit;
