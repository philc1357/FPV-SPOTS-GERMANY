<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Website-Update erstellen (Admin only)
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /updates.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /updates.php');
    exit;
}

if (empty($_SESSION['is_admin'])) {
    header('Location: /updates.php');
    exit;
}

if (!csrf_valid(post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$title       = trim(post_str('title'));
$description = trim(post_str('description'));
$userId      = (int)$_SESSION['user_id'];

if ($title === '' || mb_strlen($title) > 255) {
    header('Location: /updates.php?error=1');
    exit;
}

if ($description === '') {
    header('Location: /updates.php?error=1');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO updates (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $description]);
} catch (PDOException $e) {
    error_log('update_submit.php error: ' . $e->getMessage());
    header('Location: /updates.php?error=1');
    exit;
}

header('Location: /updates.php?success=1');
exit;
