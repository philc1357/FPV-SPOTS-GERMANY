<?php
// =============================================================
// Verbesserungsvorschlag erstellen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$body   = trim($_POST['body'] ?? '');
$userId = (int)$_SESSION['user_id'];

if (strlen($body) < 3 || strlen($body) > 1000) {
    header('Location: /public/php/kritik.php');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO suggestions (user_id, body) VALUES (?, ?)");
    $stmt->execute([$userId, $body]);
} catch (PDOException $e) {
    error_log('suggestion_submit.php error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php');
exit;
