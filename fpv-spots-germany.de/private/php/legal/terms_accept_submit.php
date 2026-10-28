<?php
declare(strict_types=1);
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /terms_accept.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('CSRF-Fehler');
}

if (($_POST['terms'] ?? '') !== '1') {
    header('Location: /terms_accept.php');
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET terms_accepted_at = NOW() WHERE id = ? AND terms_accepted_at IS NULL");
$stmt->execute([(int)$_SESSION['user_id']]);

$_SESSION['terms_ok'] = true;

$logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'TERMS_ACCEPTED', ?)");
$logStmt->execute([(int)$_SESSION['user_id'], client_ip()]);

header('Location: /');
exit;
