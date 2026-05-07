<?php
declare(strict_types=1);
// =============================================================
// Forum – Kommentar erstellen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forum.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /forum.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$userId   = (int)$_SESSION['user_id'];
$postId   = (int)($_POST['post_id'] ?? 0);
$parentId = (int)($_POST['parent_id'] ?? 0);
$body     = trim((string)($_POST['body'] ?? ''));

if ($postId <= 0) {
    header('Location: /forum.php');
    exit;
}

$bodyLen = mb_strlen($body);
if ($bodyLen < 2 || $bodyLen > 2000) {
    $_SESSION['forum_error'] = 'Der Kommentar muss zwischen 2 und 2000 Zeichen lang sein.';
    header('Location: /forum.php#post-' . $postId);
    exit;
}

// Beitrag existiert?
$exists = $pdo->prepare("SELECT id FROM forum_posts WHERE id = ?");
$exists->execute([$postId]);
if (!$exists->fetch()) {
    $_SESSION['forum_error'] = 'Beitrag nicht gefunden.';
    header('Location: /forum.php');
    exit;
}

// Parent-Kommentar prüfen und auf Root normalisieren (max. 2 Ebenen)
$parentDb = null;
if ($parentId > 0) {
    $pStmt = $pdo->prepare("SELECT id, post_id, parent_id FROM forum_comments WHERE id = ?");
    $pStmt->execute([$parentId]);
    $parent = $pStmt->fetch();
    if (!$parent || (int)$parent['post_id'] !== $postId) {
        $_SESSION['forum_error'] = 'Übergeordneter Kommentar nicht gefunden.';
        header('Location: /forum.php#post-' . $postId);
        exit;
    }
    // Wenn Parent selbst eine Antwort ist → auf dessen Root normalisieren
    $parentDb = $parent['parent_id'] !== null ? (int)$parent['parent_id'] : (int)$parent['id'];
}

// Rate-Limit: max. 30 Kommentare pro Stunde
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'FORUM_COMMENT_CREATED' AND user_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 30) {
    $_SESSION['forum_error'] = 'Kommentar-Limit erreicht (30 pro Stunde).';
    header('Location: /forum.php#post-' . $postId);
    exit;
}

try {
    $ins = $pdo->prepare("INSERT INTO forum_comments (post_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)");
    $ins->execute([$postId, $userId, $parentDb, $body]);

    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'FORUM_COMMENT_CREATED', ?)"
    )->execute([$userId, client_ip()]);
} catch (PDOException $e) {
    error_log('forum_comment_submit.php error: ' . $e->getMessage());
    $_SESSION['forum_error'] = 'Kommentar konnte nicht gespeichert werden.';
    header('Location: /forum.php#post-' . $postId);
    exit;
}

header('Location: /forum.php#post-' . $postId);
exit;
