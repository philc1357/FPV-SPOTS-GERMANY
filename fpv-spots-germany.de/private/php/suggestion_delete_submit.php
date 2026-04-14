<?php
// =============================================================
// Verbesserungsvorschlag löschen (nur Admin)
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

if ($suggestionId <= 0) {
    header('Location: /public/php/kritik.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM suggestions WHERE id = ?");
    $stmt->execute([$suggestionId]);
} catch (PDOException $e) {
    error_log('suggestion_delete_submit.php error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php');
exit;
