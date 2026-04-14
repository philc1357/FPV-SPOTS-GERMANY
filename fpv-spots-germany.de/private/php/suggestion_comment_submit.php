<?php
// =============================================================
// Admin-Kommentar zu Verbesserungsvorschlag erstellen
// =============================================================
session_start();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$suggestionId = (int)($_POST['suggestion_id'] ?? 0);
$body         = trim($_POST['body'] ?? '');
$userId       = (int)$_SESSION['user_id'];

if ($suggestionId <= 0 || strlen($body) < 3 || strlen($body) > 1000) {
    header('Location: /public/php/kritik.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO suggestion_comments (suggestion_id, user_id, body) VALUES (?, ?, ?)"
    );
    $stmt->execute([$suggestionId, $userId, $body]);
} catch (PDOException $e) {
    error_log('suggestion_comment_submit.php error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php#suggestion-' . $suggestionId);
exit;
