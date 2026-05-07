<?php
declare(strict_types=1);
// =============================================================
// Forum – Beitrag löschen (nur Admin)
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

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    header('Location: /forum.php');
    exit;
}

$uploadDir = __DIR__ . '/../../../public/uploads/forum/';

try {
    // Bilder zum Löschen einsammeln
    $imgStmt = $pdo->prepare("SELECT filename FROM forum_post_images WHERE post_id = ?");
    $imgStmt->execute([$postId]);
    $files = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM forum_comments    WHERE post_id = ?")->execute([$postId]);
    $pdo->prepare("DELETE FROM forum_post_images WHERE post_id = ?")->execute([$postId]);
    $pdo->prepare("DELETE FROM forum_posts       WHERE id = ?")->execute([$postId]);
    $pdo->commit();

    foreach ($files as $filename) {
        // Pfad-Traversal-Schutz: nur Basename verwenden
        $safe = basename((string)$filename);
        if ($safe !== '') {
            @unlink($uploadDir . $safe);
        }
    }

    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'FORUM_POST_DELETED', ?)"
    )->execute([(int)$_SESSION['user_id'], client_ip()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('forum_post_delete_submit.php error: ' . $e->getMessage());
    $_SESSION['forum_error'] = 'Beitrag konnte nicht gelöscht werden.';
}

header('Location: /forum.php');
exit;
