<?php
// =============================================================
// Bewertung abgeben (INSERT oder UPDATE bei erneutem Bewerten)
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId = (int)($_POST['spot_id'] ?? 0);
$stars  = (int)($_POST['stars'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($spotId <= 0 || $stars < 1 || $stars > 5) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

try {
    // INSERT oder UPDATE falls der User bereits bewertet hat
    $stmt = $pdo->prepare(
        "INSERT INTO ratings (spot_id, user_id, stars) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE stars = VALUES(stars)"
    );
    $stmt->execute([$spotId, $userId, $stars]);
} catch (PDOException $e) {
    error_log('rate_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId");
exit;
