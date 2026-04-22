<?php
declare(strict_types=1);
// =============================================================
// Admin-Kommentar zu Verbesserungsvorschlag löschen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$commentId = (int)($_POST['comment_id'] ?? 0);

if ($commentId <= 0) {
    header('Location: /public/php/kritik.php');
    exit;
}

// suggestion_id für Redirect-Anker auslesen
$stmt = $pdo->prepare("SELECT suggestion_id FROM suggestion_comments WHERE id = ?");
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment) {
    header('Location: /public/php/kritik.php');
    exit;
}

$suggestionId = (int)$comment['suggestion_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM suggestion_comments WHERE id = ?");
    $stmt->execute([$commentId]);
} catch (PDOException $e) {
    error_log('suggestion_comment_delete_submit.php error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php#suggestion-' . $suggestionId);
exit;
