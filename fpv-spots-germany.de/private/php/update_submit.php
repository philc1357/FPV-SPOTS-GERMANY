<?php
// =============================================================
// FPV Spots Germany – Website-Update erstellen (Admin only)
// =============================================================
session_start();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/updates.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/updates.php');
    exit;
}

if (empty($_SESSION['is_admin'])) {
    header('Location: /public/php/updates.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$userId      = (int)$_SESSION['user_id'];

if ($title === '' || mb_strlen($title) > 255) {
    header('Location: /public/php/updates.php?error=1');
    exit;
}

if ($description === '') {
    header('Location: /public/php/updates.php?error=1');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO updates (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $description]);
} catch (PDOException $e) {
    error_log('update_submit.php error: ' . $e->getMessage());
    header('Location: /public/php/updates.php?error=1');
    exit;
}

header('Location: /public/php/updates.php?success=1');
exit;
