<?php
declare(strict_types=1);
// =============================================================
// Verbesserungsvorschlag erstellen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /kritik.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /kritik.php');
    exit;
}

if (!csrf_valid(post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$body   = trim(post_str('body'));
$userId = (int)$_SESSION['user_id'];

if (strlen($body) < 3 || strlen($body) > 1000) {
    header('Location: /kritik.php');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO suggestions (user_id, body) VALUES (?, ?)");
    $stmt->execute([$userId, $body]);
} catch (PDOException $e) {
    error_log('suggestion_submit.php error: ' . $e->getMessage());
}

header('Location: /kritik.php');
exit;
