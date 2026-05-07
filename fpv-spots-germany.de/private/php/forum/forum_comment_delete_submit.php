<?php
declare(strict_types=1);
// =============================================================
// Forum – Kommentar löschen (nur Admin)
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forum.php');
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /forum.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$commentId = (int)($_POST['comment_id'] ?? 0);
if ($commentId <= 0) {
    header('Location: /forum.php');
    exit;
}

$postId = 0;
try {
    $stmt = $pdo->prepare("SELECT post_id FROM forum_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $postId = (int)($stmt->fetchColumn() ?: 0);

    // Top-Level-Kommentar: Antworten mitlöschen
    $pdo->prepare("DELETE FROM forum_comments WHERE parent_id = ?")->execute([$commentId]);
    $pdo->prepare("DELETE FROM forum_comments WHERE id = ?")->execute([$commentId]);

    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'FORUM_COMMENT_DELETED', ?)"
    )->execute([(int)$_SESSION['user_id'], client_ip()]);
} catch (PDOException $e) {
    error_log('forum_comment_delete_submit.php error: ' . $e->getMessage());
    $_SESSION['forum_error'] = 'Kommentar konnte nicht gelöscht werden.';
}

if ($postId > 0) {
    header('Location: /forum.php#post-' . $postId);
} else {
    header('Location: /forum.php');
}
exit;
