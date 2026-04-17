<?php
// =============================================================
// Verbesserungsvorschlag voten (einmal pro Nutzer)
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$suggestionId = (int)($_POST['suggestion_id'] ?? 0);
$userId       = (int)$_SESSION['user_id'];

if ($suggestionId <= 0) {
    header('Location: /public/php/kritik.php');
    exit;
}

try {
    // INSERT IGNORE verhindert Doppel-Vote über den zusammengesetzten PK
    $stmt = $pdo->prepare("INSERT IGNORE INTO suggestion_votes (suggestion_id, user_id) VALUES (?, ?)");
    $stmt->execute([$suggestionId, $userId]);
} catch (PDOException $e) {
    error_log('suggestion_vote_submit.php error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php');
exit;
